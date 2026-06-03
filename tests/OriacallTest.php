<?php

namespace Oriacall\Tests;

use Oriacall\Oriacall;
use PHPUnit\Framework\TestCase;

class OriacallTest extends TestCase
{
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
