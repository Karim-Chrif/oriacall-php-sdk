<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;

class CustomFieldsResource
{
    public function __construct(
        private readonly Client $client,
        private readonly string $path,
    ) {}

    /** @param array<string, mixed> $options */
    public function list(array $options = []): ApiResponse
    {
        return $this->client->get($this->path, $options);
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): ApiResponse
    {
        return $this->client->json('POST', $this->path, $input);
    }

    /** @param array<string, mixed> $input */
    public function update(string $key, array $input): ApiResponse
    {
        return $this->client->json('PATCH', "{$this->path}/{$this->client->encodePath($key)}", $input);
    }
}
