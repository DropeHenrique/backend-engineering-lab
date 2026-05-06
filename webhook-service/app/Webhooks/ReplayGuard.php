<?php

namespace App\Webhooks;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReplayGuard
{
    public function assert(Request $request, string $providerSlug): void
    {
        $ts = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');

        if (! is_string($ts) || $ts === '' || ! is_string($nonce) || $nonce === '') {
            throw new HttpResponseException(response()->json([
                'error' => 'Unauthorized',
                'reason' => 'Missing X-Timestamp or X-Nonce',
            ], 401));
        }

        if (! ctype_digit($ts)) {
            throw new HttpResponseException(response()->json([
                'error' => 'Unauthorized',
                'reason' => 'Invalid X-Timestamp',
            ], 401));
        }

        $skew = config('lab_events.replay_window_seconds');
        $delta = abs(time() - (int) $ts);

        if ($delta > $skew) {
            throw new HttpResponseException(response()->json([
                'error' => 'Unauthorized',
                'reason' => 'Stale X-Timestamp',
            ], 401));
        }

        $key = 'webhook:nonce:'.$providerSlug.':'.$nonce;
        $ttl = config('lab_events.nonce_ttl_seconds');
        $stored = Cache::add($key, '1', now()->addSeconds($ttl));

        if (! $stored) {
            throw new HttpResponseException(response()->json([
                'error' => 'Nonce replay',
                'reason' => 'X-Nonce already processed',
            ], 409));
        }
    }
}
