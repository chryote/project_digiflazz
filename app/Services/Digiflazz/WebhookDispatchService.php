<?php

namespace App\Services\Digiflazz;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatchService
{
    /**
     * Dispatch webhook callback to the merchant's endpoint.
     */
    public function dispatch(Transaction $transaction, array $payload, ?string $callbackUrl = null): bool
    {
        $targetUrl = $callbackUrl ?: $transaction->callback_url ?: $transaction->user?->webhook_url;

        $transaction->update([
            'callback_payload' => $payload,
            'callback_url' => $targetUrl,
        ]);

        if (! $targetUrl) {
            $transaction->update(['callback_status' => 'skipped_no_url']);
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Digiflazz-Webhook/1.0',
                ])
                ->post($targetUrl, $payload);

            if ($response->successful()) {
                $transaction->update(['callback_status' => 'sent']);
                return true;
            }

            $transaction->update(['callback_status' => 'failed_http_' . $response->status()]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('Webhook dispatch error: ' . $e->getMessage(), [
                'ref_id' => $transaction->ref_id,
                'target_url' => $targetUrl,
            ]);

            $transaction->update(['callback_status' => 'failed_exception']);
            return false;
        }
    }
}
