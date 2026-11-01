<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramProcessor;
use Illuminate\Http\Request;

/**
 * Webhook Instagram (daftarkan di Meta App Dashboard):
 *   Callback URL : https://domainmu/api/webhooks/instagram
 *   Verify token : INSTAGRAM_WEBHOOK_VERIFY_TOKEN
 *   Fields       : messages, messaging_postbacks, messaging_referral
 */
class InstagramWebhookController extends Controller
{
    public function verify(Request $request, InstagramClient $ig)
    {
        // PHP mengubah "hub.mode" menjadi "hub_mode" di query string.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $ig->verifyToken() && hash_equals((string) $ig->verifyToken(), $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, InstagramClient $ig, InstagramProcessor $processor)
    {
        if (! $ig->validSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            return response('Invalid signature', 403);
        }

        $saved = $processor->ingest((array) $request->json()->all());

        // Jawab Meta secepatnya; proses event dan kirim balasan setelah respons terkirim.
        // Bila server tidak mendukung ini, cron tiap menit (ig:process, ig:send) yang menyusul.
        if ($saved > 0) {
            app()->terminating(function () use ($processor) {
                $processor->processPending();
                $processor->sendOutbox();
            });
        }

        return response('EVENT_RECEIVED', 200);
    }
}
