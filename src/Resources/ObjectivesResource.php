<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\Resources\Concerns\Paginates;

class ObjectivesResource
{
    use Paginates;

    public function __construct(private readonly Client $client) {}

    /** @param array<string, mixed> $options */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get('/v1/objectives', $options);
    }

    /** @param array<string, mixed> $input */
    public function update(string $objectiveId, array $input): ApiResponse
    {
        return $this->client->json('PATCH', "/v1/objectives/{$this->client->encodePath($objectiveId)}", $input);
    }
}
