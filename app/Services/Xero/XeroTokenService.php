<?php

namespace App\Services\Xero;

use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XeroTokenService
{
   public function refreshIfNeeded(XeroConnection $connection): XeroConnection
{
    if (!$connection->needsRefresh()) {
        Log::info('Xero token still valid', [
            'organization_id' => $connection->organization_id,
            'expires_at' => $connection->token_expires_at,
        ]);

        return $connection;
    }

    // 🔥 REQUEST LOG
    Log::info('Refreshing Xero token - REQUEST', [
        'organization_id' => $connection->organization_id,
        'refresh_token_last_6' => substr($connection->refresh_token, -6),
        'client_id' => config('services.xero.client_id'),
        'url' => 'https://identity.xero.com/connect/token',
    ]);

    $response = Http::asForm()->post(
        'https://identity.xero.com/connect/token',
        [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id'     => config('services.xero.client_id'),
            'client_secret' => config('services.xero.client_secret'),
        ]
    );

    $json = $response->json();

    // 🔥 FULL RESPONSE LOG (SUCCESS + ERROR)
    Log::info('Xero token refresh - RESPONSE', [
        'organization_id' => $connection->organization_id,
        'http_status' => $response->status(),
        'response' => $json,
    ]);

    // ❌ ERROR CASE
    if (isset($json['error'])) {
        Log::error('Xero refresh failed', [
            'organization_id' => $connection->organization_id,
            'error' => $json['error'],
            'description' => $json['error_description'] ?? null,
            'full_response' => $json,
        ]);

        $connection->update([
            'is_active' => false,
            'disconnected_at' => now(),
        ]);

        throw new \Exception('Xero refresh token expired');
    }

    // ✅ SUCCESS CASE
    Log::info('Xero token refresh SUCCESS', [
        'organization_id' => $connection->organization_id,
        'access_token_last_6' => substr($json['access_token'], -6),
        'expires_in' => $json['expires_in'],
    ]);

    $connection->update([
        'access_token'     => $json['access_token'],
        'refresh_token'    => $json['refresh_token'],
        'token_expires_at' => now()->addSeconds($json['expires_in']),
    ]);

    return $connection->fresh();
}

}
