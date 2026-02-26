<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\XeroLeaveType;

class XeroLeaveTypeController extends Controller
{
     public function index(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id'],
            ]);

            $leaveTypes = XeroLeaveType::where(
                'organization_id',
                $validated['organization_id']
            )->get();

            return response()->json([
                'success' => true,
                'message' => 'Xero leave types retrieved successfully.',
                'data' => $leaveTypes,
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }
}
