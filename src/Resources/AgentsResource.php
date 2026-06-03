<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\Resources\Concerns\Paginates;

class AgentsResource
{
    use Paginates;

    public function __construct(private readonly Client $client) {}

    /** @param array<string, mixed> $options */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get('/v1/agents', $options);
    }
}
