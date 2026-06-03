<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\Resources\Concerns\Paginates;

class WebhookEndpointsResource
{
    use Paginates;

    public function __construct(private readonly Client $client) {}

    /** @param array<string, mixed> $options */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get('/v1/webhooks/endpoints', $options);
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): ApiResponse
    {
        return $this->client->json('POST', '/v1/webhooks/endpoints', $input);
    }

    /** @param array<string, mixed> $input */
    public function update(string $endpointId, array $input): ApiResponse
    {
        return $this->client->json('PATCH', "/v1/webhooks/endpoints/{$this->client->encodePath($endpointId)}", $input);
    }

    public function delete(string $endpointId): ApiResponse
    {
        return $this->client->delete("/v1/webhooks/endpoints/{$this->client->encodePath($endpointId)}");
    }

    public function rotateSecret(string $endpointId): ApiResponse
    {
        return $this->client->json('POST', "/v1/webhooks/endpoints/{$this->client->encodePath($endpointId)}/rotate-secret", []);
    }

    public function test(string $endpointId): ApiResponse
    {
        return $this->client->json('POST', "/v1/webhooks/endpoints/{$this->client->encodePath($endpointId)}/test", []);
    }
}
