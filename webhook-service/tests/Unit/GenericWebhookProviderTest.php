<?php

namespace Tests\Unit;

use App\Webhooks\GenericWebhookProvider;
use Illuminate\Http\Request;
use Tests\TestCase;

class GenericWebhookProviderTest extends TestCase
{
    public function test_validate_uses_hash_equals(): void
    {
        config(['webhooks.secrets.generic' => 'lab_secret']);

        $raw = '{"type":"ORDER_CONFIRMED","payload":{"order_id":"1"},"metadata":{"correlation_id":"550e8400-e29b-41d4-a716-446655440000","timestamp":"2026-05-06T12:00:00+00:00"}}';
        $sig = hash_hmac('sha256', $raw, 'lab_secret');

        $good = Request::create('/webhook/generic', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $sig,
        ], $raw);
        $good->attributes->set('raw_body', $raw);

        $bad = Request::create('/webhook/generic', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => str_repeat('a', 64),
        ], $raw);
        $bad->attributes->set('raw_body', $raw);

        $p = new GenericWebhookProvider;

        $this->assertTrue($p->validate($good));
        $this->assertFalse($p->validate($bad));
    }
}
