<?php

namespace Oriacall\Tests;

use Oriacall\ApiResponse;
use Oriacall\Client;
use Oriacall\ClientOptions;
use Oriacall\Oriacall;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OriacallTest extends TestCase
{
    public function test_calls_list_serializes_external_id_query_option(): void
    {
        $client = new class extends Client
        {
            public ?string $requestedUrl = null;

            public function __construct()
            {
                parent::__construct(new ClientOptions(
                    baseUrl: 'https://api.example.test',
                    clientId: 'client-id',
                    clientSecret: 'client-secret',
                ));
            }

            public function get(string $path, array $query = []): ApiResponse
            {
                $buildUrl = new ReflectionMethod(Client::class, 'buildUrl');
                $this->requestedUrl = $buildUrl->invoke($this, $path, $query);

                return new ApiResponse(['data' => [], 'pagination' => ['nextCursor' => null]], 200);
            }
        };

        $client->calls->list([
            'externalId' => 'crm-call/123 + west',
            'limit' => 25,
        ]);

        $this->assertSame(
            'https://api.example.test/v1/calls?externalId=crm-call%2F123%20%2B%20west&limit=25',
            $client->requestedUrl,
        );
    }

    public function test_calls_lookup_by_external_ids_posts_expected_path_and_json_body(): void
    {
        $client = new class extends Client
        {
            public ?string $requestedMethod = null;

            public ?string $requestedPath = null;

            /** @var array<string, mixed>|null */
            public ?array $requestedBody = null;

            public function __construct()
            {
                parent::__construct(new ClientOptions(
                    baseUrl: 'https://api.example.test',
                    clientId: 'client-id',
                    clientSecret: 'client-secret',
                ));
            }

            public function json(string $method, string $path, array $body): ApiResponse
            {
                $this->requestedMethod = $method;
                $this->requestedPath = $path;
                $this->requestedBody = $body;

                return new ApiResponse(['data' => []], 200);
            }
        };

        $client->calls->lookupByExternalIds(['crm-call-123', 'crm-call-456']);

        $this->assertSame('POST', $client->requestedMethod);
        $this->assertSame('/v1/calls/lookup', $client->requestedPath);
        $this->assertSame([
            'externalIds' => ['crm-call-123', 'crm-call-456'],
        ], $client->requestedBody);
    }

    public function test_verifies_webhook_signature(): void
    {
        $body = '{"event":"analysis.completed"}';
        $secret = 'whsec_test';
        $timestamp = '1710000000';
        $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->assertTrue(Oriacall::verifyWebhookSignature($body, $secret, $signature, $timestamp, now: 1710000000));
        $this->assertFalse(Oriacall::verifyWebhookSignature($body, $secret, 'v1=bad', $timestamp, now: 1710000000));
        $this->assertFalse(Oriacall::verifyWebhookSignature($body, $secret, $signature, $timestamp, now: 1710001000));
    }
}
