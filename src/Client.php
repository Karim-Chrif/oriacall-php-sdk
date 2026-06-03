<?php

namespace Oriacall;

use CURLFile;
use Oriacall\Resources\AgentsResource;
use Oriacall\Resources\CallsResource;
use Oriacall\Resources\CustomFieldsResource;
use Oriacall\Resources\HelloResource;
use Oriacall\Resources\LeadsResource;
use Oriacall\Resources\ObjectivesResource;
use Oriacall\Resources\WebhooksResource;

class Client
{
    public readonly HelloResource $hello;

    public readonly ObjectivesResource $objectives;

    public readonly AgentsResource $agents;

    public readonly CallsResource $calls;

    public readonly LeadsResource $leads;

    public readonly CustomFieldsResource $leadCustomFields;

    public readonly CustomFieldsResource $objectiveCustomFields;

    public readonly WebhooksResource $webhooks;

    private ?string $accessToken = null;

    private ?int $tokenExpiresAt = null;

    /** @var array<int, string> */
    private array $tempUploadPaths = [];

    public function __construct(private readonly ClientOptions $options)
    {
        if ($options->clientId === '' || $options->clientSecret === '') {
            throw new ApiError(0, 'invalid_sdk_input', 'clientId and clientSecret are required.');
        }

        $this->hello = new HelloResource($this);
        $this->objectives = new ObjectivesResource($this);
        $this->agents = new AgentsResource($this);
        $this->calls = new CallsResource($this);
        $this->leads = new LeadsResource($this);
        $this->leadCustomFields = new CustomFieldsResource($this, '/v1/lead-custom-fields');
        $this->objectiveCustomFields = new CustomFieldsResource($this, '/v1/objective-custom-fields');
        $this->webhooks = new WebhooksResource($this);
    }

    /** @param array<string, mixed> $options */
    public static function make(array $options): self
    {
        return new self(ClientOptions::fromArray($options));
    }

    public function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 30) {
            return $this->accessToken;
        }

        $response = $this->request('POST', '/oauth/token', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $this->options->clientId,
                'client_secret' => $this->options->clientSecret,
                'scope' => $this->formatScope($this->options->scope),
            ], JSON_THROW_ON_ERROR),
            'retryable' => true,
        ]);

        $this->accessToken = (string) ($response->data['access_token'] ?? '');
        $this->tokenExpiresAt = time() + (int) ($response->data['expires_in'] ?? 0);

        if ($this->accessToken === '') {
            throw new ApiError($response->status, 'invalid_response', 'Oriacall returned an invalid token response.', requestId: $response->requestId);
        }

        return $this->accessToken;
    }

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->request('GET', $path, [
            'query' => $query,
            'headers' => $this->authHeaders(),
            'retryable' => true,
        ]);
    }

    /** @param array<string, mixed> $body */
    public function json(string $method, string $path, array $body): ApiResponse
    {
        return $this->request($method, $path, [
            'headers' => $this->authHeaders(['Content-Type' => 'application/json']),
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, mixed> $parts @param array<string, string> $headers */
    public function multipart(string $method, string $path, array $parts, array $headers = []): ApiResponse
    {
        $fields = [];

        foreach ($parts as $key => $value) {
            if ($key === 'audioFile') {
                $fields[$key] = $this->audioToCurlFile($value);

                continue;
            }

            $fields[$key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        }

        try {
            return $this->request($method, $path, [
                'headers' => $this->authHeaders($headers),
                'multipart' => $fields,
            ]);
        } finally {
            foreach ($this->tempUploadPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            $this->tempUploadPaths = [];
        }
    }

    public function delete(string $path): ApiResponse
    {
        return $this->request('DELETE', $path, [
            'headers' => $this->authHeaders(),
            'expectJson' => false,
        ]);
    }

    /**
     * Escape hatch for endpoints not yet wrapped by the SDK.
     *
     * @param  array{query?: array<string, mixed>, headers?: array<string, string>, body?: string, json?: array<string, mixed>, auth?: bool}  $options
     */
    public function raw(string $method, string $path, array $options = []): ApiResponse
    {
        $headers = $options['headers'] ?? [];
        if (($options['auth'] ?? true) !== false) {
            $headers = $this->authHeaders($headers);
        }

        $input = [
            'headers' => $headers,
            'query' => $options['query'] ?? [],
        ];

        if (array_key_exists('json', $options)) {
            $input['headers']['Content-Type'] = 'application/json';
            $input['body'] = json_encode($options['json'], JSON_THROW_ON_ERROR);
        } elseif (array_key_exists('body', $options)) {
            $input['body'] = $options['body'];
        }

        return $this->request(strtoupper($method), $path, $input);
    }

    public function encodePath(string $value): string
    {
        return rawurlencode($value);
    }

    /** @param array<string, mixed> $input */
    private function request(string $method, string $path, array $input = []): ApiResponse
    {
        $attempt = 0;

        while (true) {
            $response = $this->send($method, $path, $input);
            $requestId = $response['headers']['x-request-id'] ?? ($response['body']['error']['requestId'] ?? null);
            $retryAfter = $this->retryAfterSeconds($response['headers']['retry-after'] ?? null);

            $this->notifyResponse($method, $path, $response['status'], $requestId, $retryAfter);

            if ($response['status'] >= 200 && $response['status'] < 300) {
                if (($input['expectJson'] ?? true) === false && $response['body'] === null) {
                    return new ApiResponse(null, $response['status'], $requestId);
                }

                if (! is_array($response['body'])) {
                    throw new ApiError($response['status'], 'invalid_response', 'Oriacall returned an invalid response.', requestId: $requestId, retryAfter: $retryAfter);
                }

                return new ApiResponse($response['body'], $response['status'], $requestId);
            }

            if (($input['retryable'] ?? false) && $attempt < $this->options->retries && $this->shouldRetry($response['status'])) {
                usleep($this->retryDelayMs($attempt, $retryAfter) * 1000);
                $attempt++;

                continue;
            }

            $error = is_array($response['body']) ? ($response['body']['error'] ?? null) : null;
            throw new ApiError(
                $response['status'],
                is_array($error) ? (string) ($error['code'] ?? 'api_request_failed') : 'api_request_failed',
                is_array($error) ? (string) ($error['message'] ?? 'Oriacall API request failed.') : 'Oriacall API request failed.',
                is_array($response['body']) ? $response['body'] : null,
                $requestId,
                $retryAfter,
                is_array($error) ? ($error['details'] ?? null) : null,
            );
        }
    }

    /** @param array<string, mixed> $input @return array{status: int, headers: array<string, string>, body: mixed} */
    private function send(string $method, string $path, array $input): array
    {
        $url = $this->buildUrl($path, $input['query'] ?? []);
        $headers = $this->formatHeaders($input['headers'] ?? []);
        $responseHeaders = [];

        $curl = curl_init($url);
        if ($curl === false) {
            throw new ApiError(0, 'curl_init_failed', 'Failed to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->options->timeoutSeconds,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $position = strpos($header, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($header, 0, $position)));
                    $value = trim(substr($header, $position + 1));
                    $responseHeaders[$name] = $value;
                }

                return strlen($header);
            },
        ]);

        if (array_key_exists('body', $input)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $input['body']);
        }

        if (array_key_exists('multipart', $input)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $input['multipart']);
        }

        $rawBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($rawBody === false) {
            throw new ApiError(0, 'network_error', $error !== '' ? $error : 'Oriacall API request failed before receiving a response.');
        }

        $body = $rawBody === '' ? null : json_decode($rawBody, true);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => json_last_error() === JSON_ERROR_NONE ? $body : null,
        ];
    }

    /** @param array<string, mixed> $query */
    private function buildUrl(string $path, array $query): string
    {
        $baseUrl = rtrim($this->options->baseUrl, '/');
        $queryString = $this->buildQuery($query);

        return $baseUrl.$path.($queryString !== '' ? '?'.$queryString : '');
    }

    /** @param array<string, mixed> $query */
    private function buildQuery(array $query, ?string $prefix = null): string
    {
        $pairs = [];

        foreach ($query as $rawKey => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $key = $this->queryKey((string) $rawKey, $prefix);

            if (is_array($value)) {
                $nested = $this->buildQuery($value, $key);
                if ($nested !== '') {
                    $pairs[] = $nested;
                }

                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $pairs[] = rawurlencode($key).'='.rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function queryKey(string $key, ?string $prefix): string
    {
        $publicKey = match ($key) {
            'customFields' => 'custom',
            'leadCustomFields' => 'leadCustom',
            'objectiveCustomFields' => 'objectiveCustom',
            default => $key,
        };

        return $prefix ? "{$prefix}[{$publicKey}]" : $publicKey;
    }

    /** @param array<string, string> $extra */
    private function authHeaders(array $extra = []): array
    {
        return array_merge(['Authorization' => 'Bearer '.$this->getAccessToken()], $extra);
    }

    /** @param array<string, string> $headers */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }

        return $formatted;
    }

    private function formatScope(string|array|null $scope): ?string
    {
        if (is_array($scope)) {
            return implode(' ', $scope);
        }

        return $scope;
    }

    private function audioToCurlFile(mixed $audio): CURLFile|string
    {
        if (! is_array($audio)) {
            throw new ApiError(0, 'invalid_sdk_input', 'audioFile must be an array.');
        }

        $filename = (string) ($audio['filename'] ?? 'call-audio');
        $contentType = (string) ($audio['contentType'] ?? $audio['content_type'] ?? 'application/octet-stream');

        if (isset($audio['path'])) {
            $path = (string) $audio['path'];
            if (! is_file($path)) {
                throw new ApiError(0, 'invalid_sdk_input', "Audio file does not exist: {$path}");
            }

            return new CURLFile($path, $contentType, $filename);
        }

        if (array_key_exists('contents', $audio)) {
            $temp = tempnam(sys_get_temp_dir(), 'oriacall-upload-');
            if ($temp === false) {
                throw new ApiError(0, 'temp_file_failed', 'Failed to create a temporary upload file.');
            }

            file_put_contents($temp, (string) $audio['contents']);
            $this->tempUploadPaths[] = $temp;

            return new CURLFile($temp, $contentType, $filename);
        }

        throw new ApiError(0, 'invalid_sdk_input', 'audio must include path or contents.');
    }

    private function retryAfterSeconds(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function shouldRetry(int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504], true);
    }

    private function retryDelayMs(int $attempt, ?int $retryAfter): int
    {
        if ($retryAfter !== null) {
            return $retryAfter * 1000;
        }

        return min($this->options->retryBaseDelayMs * (2 ** $attempt), $this->options->retryMaxDelayMs);
    }

    private function notifyResponse(string $method, string $path, int $status, ?string $requestId, ?int $retryAfter): void
    {
        $callback = $this->options->onResponse;
        if (! is_callable($callback)) {
            return;
        }

        $event = [
            'method' => $method,
            'path' => $path,
            'status' => $status,
        ];

        if ($requestId !== null) {
            $event['requestId'] = $requestId;
        }

        if ($retryAfter !== null) {
            $event['retryAfter'] = $retryAfter;
        }

        $callback($event);
    }
}
