<?php

namespace Oriacall\Resources;

use Oriacall\ApiError;
use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\Resources\Concerns\Paginates;

class CallsResource
{
    use Paginates;

    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *     limit?: int,
     *     cursor?: string,
     *     objectiveId?: string,
     *     leadId?: string,
     *     agentId?: string,
     *     externalId?: string,
     *     createdAfter?: string,
     *     createdBefore?: string,
     *     recordedAfter?: string,
     *     recordedBefore?: string,
     *     sortBy?: 'createdAt'|'recordedAt',
     *     leadCustomFields?: array<string, mixed>
     * } $options
     */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get('/v1/calls', $options);
    }

    public function get(string $callId): ApiResponse
    {
        return $this->client->get("/v1/calls/{$this->client->encodePath($callId)}");
    }

    /**
     * Look up calls by exact, case-sensitive external IDs.
     *
     * @param  list<string>  $externalIds  One to 100 distinct external call IDs.
     */
    public function lookupByExternalIds(array $externalIds): ApiResponse
    {
        return $this->client->json('POST', '/v1/calls/lookup', [
            'externalIds' => $externalIds,
        ]);
    }

    /** @param array{recordedAt: string} $input */
    public function update(string $callId, array $input): ApiResponse
    {
        return $this->client->json('PATCH', "/v1/calls/{$this->client->encodePath($callId)}", $input);
    }

    /**
     * @param  array<string, mixed>  $input  Accepts metadata fields plus `idempotencyKey` and `audio`.
     *                                       `audio` accepts `path`, or `contents`, with optional `filename` and `contentType`.
     */
    public function upload(array $input): ApiResponse
    {
        $idempotencyKey = (string) ($input['idempotencyKey'] ?? $input['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new ApiError(0, 'invalid_sdk_input', 'calls.upload requires idempotencyKey.');
        }

        $audio = $input['audio'] ?? null;
        if (! is_array($audio)) {
            throw new ApiError(0, 'invalid_sdk_input', 'calls.upload requires an audio array.');
        }

        unset($input['idempotencyKey'], $input['idempotency_key'], $input['audio']);

        return $this->client->multipart('POST', '/v1/calls', [
            'metadata' => json_encode($input, JSON_THROW_ON_ERROR),
            'audioFile' => $audio,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    public function queueAnalysis(string $callId): ApiResponse
    {
        return $this->client->json('POST', "/v1/calls/{$this->client->encodePath($callId)}/analysis-jobs", []);
    }

    /** @param array{intervalMs?: int, timeoutMs?: int} $options */
    public function waitForAnalysis(string $callId, array $options = []): ApiResponse
    {
        $intervalMs = (int) ($options['intervalMs'] ?? $options['interval_ms'] ?? 2000);
        $timeoutMs = (int) ($options['timeoutMs'] ?? $options['timeout_ms'] ?? 120000);
        $startedAt = microtime(true);

        while (true) {
            $response = $this->get($callId);
            $status = $response->data['data']['analysisStatus'] ?? null;

            if ($status === 'completed' || $status === 'failed') {
                return $response;
            }

            if (((microtime(true) - $startedAt) * 1000) >= $timeoutMs) {
                throw new ApiError(408, 'analysis_timeout', 'Timed out waiting for call analysis.', requestId: $response->requestId);
            }

            usleep($intervalMs * 1000);
        }
    }
}
