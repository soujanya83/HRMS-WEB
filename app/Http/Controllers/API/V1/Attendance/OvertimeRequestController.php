<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee\Attendance;
use Illuminate\Http\Request;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use App\Models\OrganizationAttendanceRule;
use Illuminate\Http\JsonResponse;
use App\Models\Employee\OvertimeRequest;
use App\Services\OvertimeServices;

class OvertimeRequestController extends Controller
{

    /**
     * Display all overtime requests for the logged-in user's organization.
     */
    public function index(): JsonResponse
    {
        $employee = Employee::with('designation')->where('user_id', Auth::id())->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee record not found for logged-in user.'
            ], 404);
        }

        if ($employee->designation->title == 'hr' || $employee->designation->title == 'manager') {
            $requests = OvertimeRequest::with(['employee:id,first_name', 'organization:id,name'])
                ->where('organization_id', $employee->organization_id)
                ->whereNot('status', 'cancelled')
                ->orderByDesc('id')
                ->get();
        } else {
            // if employee has logged in 
            $requests = OvertimeRequest::with(['employee:id,first_name', 'organization:id,name'])
                ->where('organization_id', $employee->organization_id)
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->get();
        }

        return response()->json([
            'status' => true,
            'message' => 'Overtime requests fetched successfully.',
            'data' => $requests
        ]);
    }

    /**
     * Store a new overtime request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->merge([
                'work_date' => str_replace('/', '-', $request->work_date)
            ]);

            $validated = $request->validate([
                'work_date' => [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) {
                        if ($value !== now()->toDateString()) {
                            $fail('You can only submit an overtime request for today.');
                        }
                    },
                ],
                'reason' => 'required',
                'expected_overtime_hours' => 'required'
            ]);

            $employee = Employee::where('user_id', Auth::id())->firstOrFail();

            // Check if a request already exists for same date
            $exists = OvertimeRequest::where('employee_id', $employee->id)
                ->whereDate('work_date', $validated['work_date'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request already exists for this date.'
                ], 409);
            }

            // check if there is attendance for the employee
            $attendance = Attendance::where('date', $validated['work_date'])->first();

            $validated['employee_id'] = $employee->id;
            $validated['organization_id'] = $employee->organization_id;
            $validated['created_by'] = $employee->id;
            $validated['attendance_id'] = $attendance->id;

            $requestData = OvertimeRequest::create($validated);

            return response()->json([
                'status' => true,
                'message' => 'Overtime request submitted successfully.',
                'data' => $requestData
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit overtime request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a single overtime request.
     */
    public function show($id): JsonResponse
    {
        $requestData = OvertimeRequest::with(['employee:id,first_name', 'organization:id,name'])
            ->find($id);

        if (!$requestData) {
            return response()->json([
                'status' => false,
                'message' => 'Overtime request not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Overtime request details fetched successfully.',
            'data' => $requestData
        ]);
    }

    /**
     * Update an overtime request (only if pending).
     */

    public function update(Request $request, $id, OvertimeServices $overtimeService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'expected_overtime_hours' => 'nullable|numeric|min:0.5|max:12',
                'reason' => 'nullable|string|max:500',
                'status' => 'nullable|in:pending,cancelled,manager_approved,rejected,hr_approved',
                'actual_overtime_hours' => 'nullable|numeric|min:0|max:12',
            ]);

            $overtime = OvertimeRequest::findOrFail($id);
            $updatedOvertime = $overtimeService->updateOvertime($overtime, $validated);

            return response()->json([
                'status' => true,
                'message' => 'Overtime request updated successfully.',
                'data' => $updatedOvertime->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete or cancel an overtime request.
     */
    public function destroy($id): JsonResponse
    {
        $overtime = OvertimeRequest::find($id);

        if (!$overtime) {
            return response()->json(['status' => false, 'message' => 'Request not found.'], 404);
        }

        if ($overtime->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Only pending requests can be deleted.'], 403);
        }

        $overtime->delete();

        return response()->json([
            'status' => true,
            'message' => 'Overtime request deleted successfully.'
        ]);
    }
}
