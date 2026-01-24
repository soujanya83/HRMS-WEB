<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class XeroRefreshAccessTokenServices
{
    public function refreshToken($connection)
    {
        $clientId     = $connection->xero_client_id;
        $clientSecret = $connection->xero_client_secret;

        $refreshToken = Crypt::decryptString($connection->refresh_token);

        $response = Http::asForm()->post('https://identity.xero.com/connect/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }
}
