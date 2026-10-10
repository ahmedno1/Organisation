<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendVerificationCode(string $code): bool
    {
        if (! config('services.whatsapp.enabled')) {
            return false;
        }

        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $accessToken = (string) config('services.whatsapp.access_token');
        $recipientNumber = (string) config('services.whatsapp.recipient_number');
        $recipientNumber = preg_replace('/\D+/', '', $recipientNumber) ?? '';
        $apiVersion = (string) config('services.whatsapp.api_version', 'v22.0');

        if ($phoneNumberId === '' || $accessToken === '' || $recipientNumber === '') {
            Log::warning('WhatsApp 2FA is enabled but not fully configured.');
            return false;
        }

        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";
        $message = "رمز التحقق للدخول هو: {$code}. صلاحية الرمز 10 دقائق.";

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to' => $recipientNumber,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Failed to send WhatsApp 2FA message.', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        }

        return true;
    }
}

