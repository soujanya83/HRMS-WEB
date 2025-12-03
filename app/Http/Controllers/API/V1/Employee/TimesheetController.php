<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Attendance;
use App\Models\Employee\Employee;
use App\Models\Employee\OvertimeRequest;
use App\Models\Employee\Timesheet;
use App\Models\OrganizationAttendanceRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;



class TimesheetController extends Controller
{
   public function store(Request $request): JsonResponse
{
    try {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:submitted,approved,rejected',
            'work_date' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // ✅ Get employee
        $employee = Employee::where('user_id', Auth::user()->id)->first();
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee not found for this user.',
            ], 404);
        }

        // ✅ Get attendance for the date
        $attendance = Attendance::whereDate('date', $data['work_date'])
            ->where('employee_id', $employee->id)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'No attendance record found for the selected date.',
            ], 404);
        }

        // ✅ Attendance rule (for working hours)
        $attendanceRule = OrganizationAttendanceRule::where('organization_id', $employee->organization_id)->first();

        // Convert to Carbon for time difference calculation
        $checkIn = Carbon::parse($attendance->check_in);
        $checkOut = Carbon::parse($attendance->check_out);
        $ruleCheckOut = $attendanceRule ? Carbon::parse($attendanceRule->check_out) : null;

        // Calculate regular working hours
        $totalHoursWorked = $checkOut->diffInMinutes($checkIn) / 60;

        // ✅ Prepare data for saving
        $data['employee_id'] = $employee->id;
        $data['attendance_id'] = $attendance->id;
        $data['work_date'] = $data['work_date'];
        $data['regular_hours'] = round($totalHoursWorked, 2);
        $data['overtime_hours'] = 0;
        $data['is_overtime'] = false;
        $data['hours_worked'] =    $totalHoursWorked;

        // ✅ If overtime exists
        $overtimeRequest = OvertimeRequest::where('attendance_id', $attendance->id)
            ->whereDate('work_date', $data['work_date'])
            ->first();

        if ($ruleCheckOut && $checkOut->greaterThan($ruleCheckOut)) {
            $data['is_overtime'] = true;
            $data['overtime_hours'] = $overtimeRequest
                ? $overtimeRequest->actual_overtime_hours
                : round(($checkOut->diffInMinutes($ruleCheckOut) / 60), 2);
        }

        // dd($data);

        // ✅ Create the timesheet
        $timesheet = Timesheet::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Timesheet created successfully.',
            'data' => $timesheet,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}

    // ✅ List all timesheets
    public function index(): JsonResponse
    {
        try {
            $timesheets = Timesheet::with([
                'project:id,name',
                'task:id,title',
                'employee:id,first_name,last_name,employee_code',
            ])->latest()->get();

            return response()->json([
                'status' => true,
                'data' => $timesheets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Show specific timesheet
    public function show($id): JsonResponse
    {
        try {
            $timesheet = Timesheet::with([
                'project:id,name',
                'task:id,title',
                'employee:id,first_name,last_name,employee_code',
            ])->find($id);

            if (!$timesheet) {
                return response()->json([
                    'status' => false,
                    'message' => 'Timesheet not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $timesheet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Update a timesheet
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $timesheet = Timesheet::find($id);

            if (!$timesheet) {
                return response()->json([
                    'status' => false,
                    'message' => 'Timesheet not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'project_id' => 'sometimes|exists:projects,id',
                'task_id' => 'nullable|exists:tasks,id',
                'employee_id' => 'sometimes|exists:employees,id',
                'date' => 'nullable|date',
                'hours' => 'nullable|numeric|min:0|max:24',
                'description' => 'nullable|string',
                'status' => 'nullable|in:submitted,approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $timesheet->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Timesheet updated successfully.',
                'data' => $timesheet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Delete timesheet
    public function destroy($id): JsonResponse
    {
        try {
            $timesheet = Timesheet::find($id);

            if (!$timesheet) {
                return response()->json([
                    'status' => false,
                    'message' => 'Timesheet not found.',
                ], 404);
            }

            $timesheet->delete();

            return response()->json([
                'status' => true,
                'message' => 'Timesheet deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
