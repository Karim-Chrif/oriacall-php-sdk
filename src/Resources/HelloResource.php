<?php

namespace Oriacall\Resources;

use Oriacall\ApiResponse;
use Oriacall\Client;

class HelloResource
{
    public function __construct(private readonly Client $client) {}

    public function get(): ApiResponse
    {
        return $this->client->get('/v1/hello');
    }
}
