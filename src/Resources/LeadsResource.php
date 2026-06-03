<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\Resources\Concerns\Paginates;

class LeadsResource
{
    use Paginates;

    public function __construct(private readonly Client $client) {}

    /** @param array<string, mixed> $options */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get('/v1/leads', $options);
    }

    public function get(string $leadId): ApiResponse
    {
        return $this->client->get("/v1/leads/{$this->client->encodePath($leadId)}");
    }

    /** @param array<string, mixed> $input */
    public function update(string $leadId, array $input): ApiResponse
    {
        return $this->client->json('PATCH', "/v1/leads/{$this->client->encodePath($leadId)}", $input);
    }

    /** @param array<string, mixed> $input */
    public function upsertByExternalId(string $externalId, array $input): ApiResponse
    {
        return $this->client->json('PUT', "/v1/leads/by-external-id/{$this->client->encodePath($externalId)}", $input);
    }
}
