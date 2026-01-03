<?php

namespace App\Http\Controllers\API\V1\Xero;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\XeroConnection;
use Illuminate\Support\Facades\Http;


class PayrunController extends Controller
{
    public function getPayrun(Request $request, $organizationId)
    {
        try {
            // ------------------------------------------------------
            // 1. FETCH ACTIVE XERO CONNECTION
            // ------------------------------------------------------
            // dd($organizationId);
            // $organizationId = auth()->user()->employee->organization_id;

            $connection = XeroConnection::where('organization_id', $organizationId)
                ->where('is_active', 1)
                ->first();

            if (!$connection) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Xero connection found.'
                ], 404);
            }

            // ------------------------------------------------------
            // 2. BUILD QUERY PARAMETERS (OPTIONAL)
            // Xero supports filters like:
            // ?page=1&payrollCalendarID=xxxx
            // ------------------------------------------------------
            $queryParams = [];

            if ($request->has('payroll_calendar_id')) {
                $queryParams['payrollCalendarID'] = $request->payroll_calendar_id;
            }

            if ($request->has('page')) {
                $queryParams['page'] = $request->page;
            }

            $queryString = http_build_query($queryParams);
            $endpoint = "https://api.xero.com/payroll.xro/1.0/PayRuns" . ($queryString ? "?$queryString" : "");

            // ------------------------------------------------------
            // 3. SEND REQUEST TO XERO
            // ------------------------------------------------------
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $connection->access_token,
                'xero-tenant-id' => $connection->tenant_id,
                'Accept'         => 'application/json'
            ])->get($endpoint);

            // ------------------------------------------------------
            // 4. HANDLE FAILED REQUESTS
            // ------------------------------------------------------
            if ($response->status() === 401) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: Token expired or invalid.'
                ], 401);
            }

            if ($response->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Xero API error',
                    'error'   => $response->json()
                ], 422);
            }

            // ------------------------------------------------------
            // 5. SUCCESS
            // ------------------------------------------------------
            return response()->json([
                'status' => true,
                'message' => 'PayRun data fetched successfully.',
                'data' => $response->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching PayRun.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
}
