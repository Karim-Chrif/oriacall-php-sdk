<?php

namespace Oriacall\Resources;

use Oriacall\Client;

class WebhooksResource
{
    public readonly WebhookEndpointsResource $endpoints;

    public function __construct(Client $client)
    {
        $this->endpoints = new WebhookEndpointsResource($client);
    }
}
