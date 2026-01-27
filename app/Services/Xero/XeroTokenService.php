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
            return $connection;
        }

        Log::info('Refreshing Xero token', [
            'organization_id' => $connection->organization_id
        ]);

        $response = Http::asForm()->post(
            'https://identity.xero.com/connect/token',
            [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
                'client_id'     => config('services.xero.client_id'),
                'client_secret' => config('services.xero.client_secret'),
            ]
        )->json();

        if (isset($response['error'])) {
            Log::error('Xero refresh failed', $response);

            // Hard fail → disconnect
            $connection->update([
                'is_active' => false,
                'disconnected_at' => now(),
            ]);

            throw new \Exception('Xero refresh token expired');
        }

        $connection->update([
            'access_token'     => $response['access_token'],
            'refresh_token'    => $response['refresh_token'], // VERY IMPORTANT
            'token_expires_at' => now()->addSeconds($response['expires_in']),
        ]);

        return $connection->fresh();
    }
}
