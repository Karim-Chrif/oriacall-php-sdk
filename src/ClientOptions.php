<?php

namespace Oriacall;

class ClientOptions
{
    /**
     * @param  string|array<int, string>|null  $scope
     * @param  callable(array{method: string, path: string, status: int, requestId?: string, retryAfter?: int}): void|null  $onResponse
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $baseUrl = 'https://api.oriacall.com',
        public readonly string|array|null $scope = null,
        public readonly mixed $onResponse = null,
        public readonly int $retries = 0,
        public readonly int $retryBaseDelayMs = 250,
        public readonly int $retryMaxDelayMs = 2000,
        public readonly int $timeoutSeconds = 30,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromArray(array $options): self
    {
        return new self(
            clientId: (string) ($options['clientId'] ?? $options['client_id'] ?? ''),
            clientSecret: (string) ($options['clientSecret'] ?? $options['client_secret'] ?? ''),
            baseUrl: (string) ($options['baseUrl'] ?? $options['base_url'] ?? 'https://api.oriacall.com'),
            scope: $options['scope'] ?? null,
            onResponse: $options['onResponse'] ?? $options['on_response'] ?? null,
            retries: (int) ($options['retries'] ?? 0),
            retryBaseDelayMs: (int) ($options['retryBaseDelayMs'] ?? $options['retry_base_delay_ms'] ?? 250),
            retryMaxDelayMs: (int) ($options['retryMaxDelayMs'] ?? $options['retry_max_delay_ms'] ?? 2000),
            timeoutSeconds: (int) ($options['timeoutSeconds'] ?? $options['timeout_seconds'] ?? 30),
        );
    }
}
