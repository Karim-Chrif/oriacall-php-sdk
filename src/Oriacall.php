<?php

namespace Oriacall;

class Oriacall
{
    /** @param array<string, mixed> $options */
    public static function client(array $options): Client
    {
        return Client::make($options);
    }

    public static function verifyWebhookSignature(string $body, string $secret, string $signature, string $timestamp, int $toleranceSeconds = 300, ?int $now = null): bool
    {
        $timestampSeconds = filter_var($timestamp, FILTER_VALIDATE_INT);
        if ($timestampSeconds === false) {
            return false;
        }

        $currentTime = $now ?? time();
        if (abs($currentTime - $timestampSeconds) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        $provided = str_starts_with($signature, 'v1=') ? substr($signature, 3) : $signature;

        return hash_equals($expected, $provided);
    }
}
