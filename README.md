# oriacall/sdk

PHP SDK for the Oriacall Developer API.

## Install

```bash
composer require oriacall/sdk
```

Requirements:

- PHP 8.2 or newer.
- PHP `curl` and `json` extensions.
- An Oriacall Developer API client ID and secret.
- Server-side usage only. Do not expose `clientSecret` in browser code.

## Quickstart

```php
use Oriacall\Oriacall;

$oriacall = Oriacall::client([
    'clientId' => getenv('ORIACALL_CLIENT_ID'),
    'clientSecret' => getenv('ORIACALL_CLIENT_SECRET'),
    'scope' => ['hello:read', 'objectives:read', 'calls:read'],
]);

$hello = $oriacall->hello->get();
echo $hello->data['message'].' '.$hello->requestId.PHP_EOL;

$calls = $oriacall->calls->list(['limit' => 50]);
print_r($calls->data['data']);
```

The SDK requests and caches a short-lived access token using client credentials, then sends it as a bearer token for API calls.

## Client Options

```php
$oriacall = Oriacall::client([
    'baseUrl' => 'https://api.oriacall.com',
    'clientId' => getenv('ORIACALL_CLIENT_ID'),
    'clientSecret' => getenv('ORIACALL_CLIENT_SECRET'),
    'scope' => ['calls:read'],
    'retries' => 2,
    'retryBaseDelayMs' => 250,
    'retryMaxDelayMs' => 2000,
    'timeoutSeconds' => 30,
    'onResponse' => function (array $event): void {
        error_log(json_encode($event));
    },
]);
```

Options:

| Option | Type | Required | Description |
| --- | --- | --- | --- |
| `clientId` | `string` | Yes | Developer API client ID. |
| `clientSecret` | `string` | Yes | Developer API client secret. Keep this server-side. |
| `baseUrl` | `string` | No | API base URL. Defaults to `https://api.oriacall.com`. |
| `scope` | `string|array|null` | No | Space-separated string or array of requested token scopes. If omitted, the token request uses all scopes granted to the client. |
| `onResponse` | `callable|null` | No | Called for every SDK-managed HTTP response, including token requests. |
| `retries` | `int` | No | Retry count for token requests and GET endpoints. Defaults to `0`. |
| `retryBaseDelayMs` | `int` | No | Initial retry delay. Defaults to `250`. |
| `retryMaxDelayMs` | `int` | No | Maximum retry delay. Defaults to `2000`. |
| `timeoutSeconds` | `int` | No | cURL timeout. Defaults to `30`. |

## Response Envelope

Every endpoint method returns `Oriacall\ApiResponse`:

```php
$response->data;      // decoded JSON array, or null for delete responses
$response->status;    // HTTP status code
$response->requestId; // X-Request-Id when provided
```

## Methods

```php
$oriacall->getAccessToken();
$oriacall->raw('GET', '/v1/hello');
$oriacall->hello->get();

$oriacall->objectives->list(['limit' => 50]);
$oriacall->objectives->update('objective-id', ['customFields' => ['region' => 'north']]);
$oriacall->objectives->paginate(['limit' => 50]);

$oriacall->objectiveCustomFields->list();
$oriacall->objectiveCustomFields->create(['key' => 'region', 'label' => 'Region', 'type' => 'text']);
$oriacall->objectiveCustomFields->update('region', ['label' => 'Sales Region']);

$oriacall->agents->list(['objectiveId' => 'objective-id']);
$oriacall->agents->paginate(['limit' => 50]);

$oriacall->calls->list(['limit' => 50]);
$oriacall->calls->get('call-id');
$oriacall->calls->upload([...]);
$oriacall->calls->queueAnalysis('call-id');
$oriacall->calls->waitForAnalysis('call-id', ['timeoutMs' => 120000]);
$oriacall->calls->paginate(['limit' => 50]);

$oriacall->leads->list(['customFields' => ['crm_stage' => 'qualified']]);
$oriacall->leads->get('lead-id');
$oriacall->leads->update('lead-id', ['customFields' => ['crm_stage' => 'won']]);
$oriacall->leads->upsertByExternalId('crm-lead-id', ['firstName' => 'Ada', 'lastName' => 'Lovelace']);
$oriacall->leads->paginate(['limit' => 50]);

$oriacall->leadCustomFields->list();
$oriacall->leadCustomFields->create(['key' => 'crm_stage', 'label' => 'CRM Stage', 'type' => 'text']);
$oriacall->leadCustomFields->update('crm_stage', ['label' => 'CRM Stage']);

$oriacall->webhooks->endpoints->list();
$oriacall->webhooks->endpoints->create(['url' => 'https://example.com/oriacall/webhooks', 'events' => ['analysis.completed']]);
$oriacall->webhooks->endpoints->update('endpoint-id', ['isActive' => false]);
$oriacall->webhooks->endpoints->rotateSecret('endpoint-id');
$oriacall->webhooks->endpoints->test('endpoint-id');
$oriacall->webhooks->endpoints->delete('endpoint-id');
$oriacall->webhooks->endpoints->paginate(['limit' => 50]);
```

## Upload A Call

```php
$response = $oriacall->calls->upload([
    'idempotencyKey' => 'crm-call-123',
    'externalId' => 'crm-call-123',
    'recordedAt' => '2026-06-10T14:30:00Z',
    // Optional hint. Oriacall may override it during audio analysis.
    'objectiveId' => 'objective-id',
    'queueAnalysis' => true,
    'agent' => [
        'externalId' => 'agent-1',
        'name' => 'Morgan Agent',
        'email' => 'morgan@example.com',
    ],
    'lead' => [
        'externalId' => 'lead-1',
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'phone' => '+15555550100',
        'customFields' => [
            'crm_stage' => 'qualified',
        ],
    ],
    'audio' => [
        'path' => storage_path('app/calls/call.mp3'),
        'filename' => 'call.mp3',
        'contentType' => 'audio/mpeg',
    ],
]);

echo $response->data['data']['id'];
echo $response->data['data']['recordedAt'];
```

To upload in-memory audio, use `contents` instead of `path`:

```php
'audio' => [
    'contents' => $audioBytes,
    'filename' => 'call.mp3',
    'contentType' => 'audio/mpeg',
]
```

Required scope: `calls:write`.

`objectiveId` is optional. When provided, it is treated as a hint for the first audio analysis pass. If Oriacall cannot identify an objective confidently, the organization's superadmin-configured fallback objective is used.

Call responses include objective selection metadata: `objectiveHint`, `identifiedObjective`, `objectiveSelectionSource`, `objectiveIdentificationConfidence`, and `analysisStage`. `analysisStatus` is one of `pending`, `queued`, `processing`, `completed`, or `failed`. `queueStatus` is `queued`, `processing`, `completed`, `failed`, or `null` when analysis has not been queued. `analysisStage` is `audio_pass`, `objective_pass`, `publishing`, `completed`, or `null` when analysis has not been queued. Internal dead-letter and cancelled runs are exposed as `failed`. Call detail analysis includes user-visible organization detections in `organizationDetectedTags` and `organizationDetectedParams`. Hidden global detections are never exposed by the API or SDK.

## Pagination

List endpoints use cursor pagination.

```php
$firstPage = $oriacall->calls->list(['limit' => 50]);

if ($cursor = $firstPage->data['pagination']['nextCursor']) {
    $secondPage = $oriacall->calls->list(['limit' => 50, 'cursor' => $cursor]);
}

foreach ($oriacall->calls->paginate(['limit' => 50]) as $call) {
    echo $call['id'].PHP_EOL;
}
```

Pagination helpers are available for `objectives`, `agents`, `calls`, `leads`, and `webhooks->endpoints`.

## Custom Field Filters

Use SDK option names that match the TypeScript SDK. The PHP SDK maps them to the public API query parameters:

```php
$oriacall->objectives->list([
    'objectiveCustomFields' => [
        'region' => 'north',
        'priority' => ['gte' => 5],
    ],
]);

$oriacall->calls->list([
    'leadCustomFields' => [
        'crm_stage' => 'qualified',
    ],
]);

$oriacall->leads->list([
    'customFields' => [
        'crm_stage' => 'qualified',
    ],
]);
```

## Errors

Failed API calls throw `Oriacall\ApiError`:

```php
use Oriacall\ApiError;

try {
    $oriacall->calls->get('call-id');
} catch (ApiError $error) {
    logger()->error('Oriacall failed', [
        'status' => $error->status,
        'code' => $error->errorCode,
        'message' => $error->getMessage(),
        'request_id' => $error->requestId,
        'details' => $error->details,
        'retry_after' => $error->retryAfter,
    ]);
}
```

## Webhook Signature Verification

```php
use Oriacall\Oriacall;

$valid = Oriacall::verifyWebhookSignature(
    body: $request->getContent(),
    secret: config('services.oriacall.webhook_secret'),
    signature: $request->header('Oriacall-Signature'),
    timestamp: $request->header('Oriacall-Timestamp'),
);
```

## Scopes

Available scopes:

```text
hello:read
objectives:read
objectives:write
objective_custom_fields:manage
agents:read
calls:read
calls:write
leads:read
leads:write
lead_custom_fields:manage
webhooks:read
webhooks:write
```

The token request can only request scopes that were granted to that API client.
