<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\XeroLeaveApplication;

class XeroLeaveApplicationController extends Controller
{
     public function index(Request $request): JsonResponse
    {
        try {

            /* ===============================
             | 1. VALIDATION
             =============================== */
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id'],
                'employee_xero_connection_id' => ['nullable', 'integer'],
                'start_date' => ['nullable', 'date'],
                'end_date'   => ['nullable', 'date'],
                'status'     => ['nullable', 'string'],
            ]);

            /* ===============================
             | 2. BASE QUERY (Mandatory Filter)
             =============================== */
            $query = XeroLeaveApplication::where(
                'organization_id',
                $validated['organization_id']
            );

            /* ===============================
             | 3. OPTIONAL FILTERS
             =============================== */

            // Filter by employee
            if (!empty($validated['employee_xero_connection_id'])) {
                $query->where(
                    'employee_xero_connection_id',
                    $validated['employee_xero_connection_id']
                );
            }

            // Filter by date range
            if (!empty($validated['start_date'])) {
                $query->whereDate('start_date', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->whereDate('end_date', '<=', $validated['end_date']);
            }

            // Filter by status
            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            /* ===============================
             | 4. GET DATA
             =============================== */
            $leaveApplications = $query
                ->orderByDesc('start_date')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Leave applications retrieved successfully.',
                'data'    => $leaveApplications,
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }
}
