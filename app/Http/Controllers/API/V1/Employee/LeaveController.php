<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\Leave;
use App\Models\OrganizationLeave;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class LeaveController extends Controller
{
 public function index(Request $request): JsonResponse
{
    try {
        // filter by date range

        $query = Leave::with('employee:id,first_name,last_name,personal_email')
            ->orderBy('id', 'desc');
           

              if ($request->from && $request->to) {
            $query->whereBetween('start_date', [$request->from, $request->to]);
        }
         $leaves = $query->get();

        if ($leaves->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No leave requests found',
                'data' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leaves retrieved successfully',
            'data' => $leaves
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve leaves',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function show($id): JsonResponse
{
    try {
        $leave = Leave::with('employee:id,first_name,last_name,personal_email')->find($id);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Leave retrieved successfully',
            'data' => $leave
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve leave',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function store(Request $request, $id = null): JsonResponse{
    try {
        // Validate JSON input
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'leave_type' => 'required|in:casual,sick,earned,maternity,paternity,unpaid',
            'reason' => 'nullable|string|max:500',
        ]);

        // If ID exists, it's an update
        if ($id) {
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave record not found',
                ], 404);
            }

            $leave->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Leave updated successfully',
                'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
            ], 200);
        }

        // Otherwise, create new record
        $validated['employee_id'] = Auth::id();

        $leave = Leave::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave created successfully',
            'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function approve_leave(Request $request,$id)
{
    try {
        $validated = $request->validate([
            'status' => 'required',
        ]);

        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
                'data' => []
            ], 404);
        }

        $leave->status = $validated['status'];
        $leave->approved_by = session('employee_id') ?? Auth::user()->id ?? null; // store who approved
        $leave->save();

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully',
            'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error occurred',
            'error' => $e->getMessage(),
        ], 500);

    }
}


public function destroy($id)
{
    try {
        // Find the leave record
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found',
            ], 404);
        }

        // Delete the record
        $leave->delete();

        return response()->json([
            'status' => true,
            'message' => 'Leave deleted successfully',
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete leave',
            'error' => $e->getMessage(),
        ], 500);
    }
}


// Leave Balance
public function leaveBalance(Request $request)
{
    try {
        // Step 1: Get organization_id for logged-in user
        $employee = Employee::select('organization_id')
            ->where('user_id', Auth::user()->id)
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee record not found.'
            ], 404);
        }

        $organization_id = $employee->organization_id;

        // Step 2: Get all leave types and granted counts for this organization
        $leavesGranted = OrganizationLeave::where(['organization_id'=> $organization_id,'is_active' => 1])
            ->get();

        if ($leavesGranted->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No leave policy found for this organization.'
            ], 404);
        }

        // Step 3: Get used leaves grouped by leave_type for logged-in user
        $leavesUsed = Leave::select('leave_type', DB::raw('COUNT(*) as used_count'))
            ->where('employee_id', Auth::user()->id)
            ->groupBy('leave_type')
            ->get()
            ->keyBy('leave_type'); // Makes it easy to lookup by type

        // Step 4: Calculate balance for each leave type
        $balances = $leavesGranted->map(function ($grant) use ($leavesUsed) {
            $used = $leavesUsed[$grant->leave_type]->used_count ?? 0;
            $balance = max($grant->granted_days - $used, 0);
            return [
                'paid'=>$grant->paid ,
                'carry_forward' => $grant->carry_forward,
                'description' => $grant->description,
                'leave_type' => $grant->leave_type,
                'max_carry_forward' => $grant->max_carry_forward,
                'leave_type' => $grant->leave_type,
                'granted' => $grant->granted_days,
                'used' => $used,
                'balance' => $balance,
            ];
        });

        // Step 5: Return structured response
        return response()->json([
            'status' => true,
            'message' => 'Leave balance fetched successfully.',
            'data' => $balances,
        ]);

    } catch (\Exception $e) {
        // \Log::error('Leave Balance Error: '.$e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong while fetching leave balance.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


}
