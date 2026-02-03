<?php

namespace App\Services\Xero;

use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;

class XeroApiService
{
    public function get(XeroConnection $connection, string $endpoint)
    {
        $connection = app(XeroTokenService::class)
            ->refreshIfNeeded($connection);

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $connection->access_token,
            'Xero-Tenant-Id'=> $connection->tenant_id,
            'Accept'        => 'application/json',
        ])->get($endpoint)->json();
    }

    public function post(XeroConnection $connection, string $endpoint, array $data)
    {
        $connection = app(XeroTokenService::class)
            ->refreshIfNeeded($connection);

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $connection->access_token,
            'Xero-Tenant-Id'=> $connection->tenant_id,
            'Accept'        => 'application/json',
        ])->post($endpoint, $data)->json();
    }
}
