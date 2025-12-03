<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee\Attendance;
use App\Models\Employee\Employee;
use App\Models\Employee\WorkOnHoliday;
use App\Models\HolidayModel;
use App\Models\OrganizationAttendanceRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\Factory;
use Illuminate\Support\Facades\Auth;


class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query()
            ->with('employee:id,first_name,last_name,personal_email,phone_number') // Eager load employee details
            ->orderBy('date', 'desc')
            ->orderBy('employee_id');

        // Filter by employee if provided
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->byDateRange($startDate, $endDate);
        }

        $attendances = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $attendances,
            'message' => 'Attendances retrieved successfully.'
        ]);
    }

    /**
     * Store a newly created attendance record.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate input first (timezone handling comes after)
            $validated = $request->validate([
                'employee_id' => ['required', 'exists:employees,id'],
                'date' => ['required', 'date'], // Remove unique from here; handle manually after TZ adjustment
                'check_in' => ['nullable', 'date_format:H:i'],
                'check_out' => ['nullable', 'date_format:H:i', 'after_or_equal:check_in'],
                'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            // Fetch employee and determine timezone (Australian default if not set)
            $employee = Employee::with('organization:id,timezone') // Assuming Employee has belongsTo Organization
                ->findOrFail($validated['employee_id']);

            $timezone = $employee->organization->timezone ?? 'Australia/Sydney'; // Default to Sydney (AEST/AEDT)

            // Parse and adjust date/time to Australian timezone
            $dateInTz = Carbon::parse($validated['date'])->setTimezone($timezone)->format('Y-m-d');

            // Adjust times if provided (combine with date, apply TZ, extract H:i)
            $checkInTime = $validated['check_in']
                ? Carbon::createFromFormat('Y-m-d H:i', "{$dateInTz} {$validated['check_in']}")->setTimezone($timezone)->format('H:i')
                : null;

            $checkOutTime = $validated['check_out']
                ? Carbon::createFromFormat('Y-m-d H:i', "{$dateInTz} {$validated['check_out']}")->setTimezone($timezone)->format('H:i')
                : null;

            // Prepare data with TZ-adjusted values
            $attendanceData = [
                'employee_id' => $validated['employee_id'],
                'date' => $dateInTz,
                'check_in' => $checkInTime,
                'check_out' => $checkOutTime,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ];

            // Manual uniqueness check (after TZ adjustment)
            $exists = Attendance::where('employee_id', $validated['employee_id'])
                ->where('date', $dateInTz)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance record already exists for this employee on the specified date.',
                ], 422);
            }

            $attendance = \App\Models\Employee\Attendance::create($attendanceData);

            return response()->json([
                'success' => true,
                'data' => $attendance->load('employee:id,first_name,last_name,personal_email'),
                'message' => 'Attendance recorded successfully.'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are already handled by Laravel (422 response), but we can customize if needed
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Database-specific errors (e.g., unique constraint violation beyond validation)
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating attendance.',
                'errors' => ['database' => $e->getMessage()] // Avoid exposing full error in production
            ], 500);
        } catch (\Exception $e) {
            // Catch-all for unexpected errors
            // In production, log the full error: \Log::error('Attendance store error: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while recording attendance.',
                'errors' => [] // Keep generic; log details separately
            ], 500);
        }
    }

    /**
     * Display the specified attendance record.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load('employee:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $attendance,
            'message' => 'Attendance retrieved successfully.'
        ]);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        try {
            // Validate input first (basic rules; uniqueness handled manually after TZ adjustment)
            $validated = $request->validate([
                'employee_id' => ['sometimes', 'exists:employees,id'],
                'date' => ['sometimes', 'date'], // Remove unique; handle post-TZ
                'check_in' => ['sometimes', 'date_format:H:i'],
                'check_out' => ['sometimes', 'date_format:H:i', 'after_or_equal:check_in'], // Note: This assumes check_in is provided if check_out is; custom logic if needed
                'status' => ['sometimes', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);
            // dd($request->all());


            // Fetch employee and determine timezone (Australian default if not set)
            $employeeId = $request->input('employee_id', $attendance->employee_id); // Use new or existing employee_id
            $employee = Employee::with('organization:id')
                ->findOrFail($employeeId);

            $timezone = $employee->organization->timezone ?? 'Australia/Sydney'; // Default to Sydney (AEST/AEDT)

            // Prepare update data with TZ-adjusted values
            $updateData = [
                'status' => $validated['status'] ?? $attendance->status,
                'notes' => $validated['notes'] ?? $attendance->notes,
            ];

            $newDate = $attendance->date; // Default to existing date
            if (isset($validated['date'])) {
                // Adjust new date to Australian timezone
                $newDate = Carbon::parse($validated['date'])->setTimezone($timezone)->format('Y-m-d');
                $updateData['date'] = $newDate;
            }

            // Adjust times if provided (combine with date - new or existing)
            $baseDate = $newDate; // Use new date if provided, else existing
            if (isset($validated['check_in'])) {
                $checkInFull = Carbon::createFromFormat('Y-m-d H:i', "{$baseDate} {$validated['check_in']}")->setTimezone($timezone);
                $updateData['check_in'] = $checkInFull->format('H:i');
            }

            if (isset($validated['check_out'])) {
                $checkOutFull = Carbon::createFromFormat('Y-m-d H:i', "{$baseDate} {$validated['check_out']}")->setTimezone($timezone);
                $updateData['check_out'] = $checkOutFull->format('H:i');
            }

            // Update employee_id if provided
            if (isset($validated['employee_id'])) {
                $updateData['employee_id'] = $validated['employee_id'];
            }

            // Manual uniqueness check if date is changing (ignore current record)
            if (isset($validated['date']) || isset($validated['employee_id'])) {
                $checkEmployeeId = $updateData['employee_id'] ?? $attendance->employee_id;
                $checkDate = $updateData['date'] ?? $attendance->date;

                $exists = Attendance::where('employee_id', $checkEmployeeId)
                    ->where('date', $checkDate)
                    ->where('id', '!=', $attendance->id) // Ignore current record
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attendance record already exists for this employee on the specified date.',
                        'errors' => ['date' => ['Attendance record already exists for this employee on the specified date.']],
                    ], 422);
                }
            }

            $attendance->update($updateData);


            return response()->json([
                'success' => true,
                'data' => $attendance->fresh()->load('employee:id,name,personal_email'),
                'message' => 'Attendance updated successfully.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors (422 response)
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Database-specific errors
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating attendance.',
                'errors' => ['database' => $e->getMessage()] // Sanitize in production
            ], 500);
        } catch (\Exception $e) {
            // Catch-all for unexpected errors
            // \Log::error('Attendance update error: ' . $e->getMessage(), ['request' => $request->all(), 'attendance_id' => $attendance->id]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating attendance.',
                'errors' => $e->getMessage() // Generic; log details separately
            ], 500);
        }
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance deleted successfully.'
        ]);
    }

    /**
     * Bulk create attendances for multiple employees on a specific date.
     * Useful for marking absences or defaults.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['exists:employees,id'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $attendances = [];
        foreach ($validated['employee_ids'] as $employeeId) {
            // Check for existing record
            if (Attendance::where('employee_id', $employeeId)->where('date', $validated['date'])->exists()) {
                continue; // Skip if already exists
            }

            $attendances[] = Attendance::create([
                'employee_id' => $employeeId,
                'date' => $validated['date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $attendances,
            'message' => count($attendances) . ' attendances created successfully.'
        ], 201);
    }

    /**
     * Mark clock-in for an employee on the current date.
     */
    public function clockIn(Request $request): JsonResponse
    {
        try {
            $employee = Employee::where('user_id', Auth::id())->firstOrFail();
            $todayDate = Carbon::today()->toDateString();
            $nowTime = Carbon::now()->format('H:i');

            // ✅ Check if today is a holiday
            $checkHoliday = HolidayModel::where('organization_id', $employee->organization_id)
                ->whereDate('holiday_date', $todayDate)
                ->first();

            // ✅ Get working day / shift info
            $attendanceRule = OrganizationAttendanceRule::where([
                'organization_id' => $employee->organization_id,
                'shift_name' => 'Morning Shift'
            ])->first();

            $todayDayName = Carbon::today()->format('l'); // e.g. "Saturday"

            // 🔹 Case 1: If it's a holiday
            if ($checkHoliday) {
                $workOnHoliday = WorkOnHoliday::where('employee_id', $employee->id)
                    ->whereDate('work_date', $todayDate)
                    ->whereIn('status', ['hr_approved'])
                    ->first();

                if (!$workOnHoliday) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You are not approved to work on today’s holiday.',
                    ], 403);
                }

                $attendance = Attendance::where(
                    ['employee_id' => $employee->id],
                    ['status' => 'work_on_holiday']
                )->whereDate('date', $todayDate)->first();

                if (!$attendance->check_in) {
                    $attendance->update(['check_in' => $nowTime]);
                    return response()->json([
                        'status' => true,
                        'message' => "Check-in marked successfully for holiday work at {$nowTime}.",
                        'data' => $attendance->fresh(),
                    ]);
                }

                return response()->json([
                    'status' => false,
                    'message' => 'You have already clocked in today.',
                ]);
            }

            // 🔹 Case 2: Regular working day
            if ($attendanceRule && !empty($attendanceRule->weekly_off_days)) {
                $weeklyOffs = array_map('trim', explode(',', $attendanceRule->weekly_off_days));

                if (in_array($todayDayName, $weeklyOffs)) {
                    return response()->json([
                        'status' => false,
                        'message' => "Today ({$todayDayName}) is your weekly off day.",
                    ]);
                }
            }

            // Mark attendance for normal working day
            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'date' => $todayDate],
                ['status' => 'present']
            );

            if (!$attendance->check_in) {
                $attendance->update(['check_in' => $nowTime]);
                $message = "Clock-in recorded successfully at {$nowTime}.";
            } else {
                $message = 'You have already clocked in today.';
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $attendance->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to mark attendance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Mark clock-out for an employee on the current date.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', Auth::user()->id)
            ->select('id')
            ->first();

        // dd($request->all());

        $today = Carbon::today();
        $attendance = Attendance::where('employee_id',  $employee->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'No attendance record found for today. Please clock-in first.'
            ], 404);
        }

        if (!$attendance->check_out) {
            $checkIn = Carbon::parse($attendance->check_in);
            $checkOut = Carbon::now();

            // Calculate time difference as a DateInterval
            $diff = $checkOut->diff($checkIn);

            // Format total work duration as HH:MM:SS
            $totalWorkingHours = sprintf(
                '%02d:%02d:%02d',
                $diff->h + ($diff->d * 24), // total hours (in case next day)
                $diff->i,                   // minutes
                $diff->s                    // seconds
            );
           

            // Update attendance record
            $attendance->update([
                'check_out' => $checkOut->format('H:i:s'),
                'total_work_hours' => $totalWorkingHours,
            ]);

            $message = "Clock-out recorded successfully. You have worked for {$totalWorkingHours}.";
        } else {
            $message = 'You have already clocked out today.';
        }

        return response()->json([
            'success' => true,
            'data' => $attendance->fresh(),
            'message' => $message
        ]);
    }

    // both the hr or manager also can create request and also employee 
    public function RequestWorkOnHoliday(Request $request)
    {
        try {
            $authUser = Auth::user();
            $employee = $authUser->employee; // current employee if logged-in user is an employee
            $organizationId = $employee->organization_id ?? null;

            // Validate input
            $validated = $request->validate([
                'work_date' => 'required|date',
                'expected_overtime_hours' => 'required|numeric|min:0',
                'reason' => 'required|string|max:500',
                'holiday_id' => 'required|exists:organization_holidays,id',
                'employee_id' => 'nullable|exists:employees,id', // optional for HR/Manager
            ]);

            // Determine employee_id

            // HR/Manager can submit for someone else
            if (empty($validated['employee_id'])) {
                $validated['employee_id'] = $employee->id;
            }

            // Assign organization and audit info
            $validated['organization_id'] = $organizationId ?? Employee::find($validated['employee_id'])->organization_id;
            $validated['status'] = 'pending'; // default status
            $validated['created_by'] = $employee->id; // who created request

            // Create request
            $workOnHoliday = WorkOnHoliday::create($validated);

            return response()->json([
                'status' => true,
                'message' => 'Work on holiday request submitted successfully.',
                'data' => $workOnHoliday
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit work on holiday request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ShowHolidayRequests()
    {
        try {
            // Fetch all work-on-holiday requests with related employee, organization, and holiday info
            $holidayRequests = WorkOnHoliday::with([
                'employee',     // Employee who requested
                'organization', // Organization of employee
                'holiday',      // Linked holiday details
                'manager',      // Manager who approved
                'hr'            // HR who approved
            ])->get();

            return response()->json([
                'status' => true,
                'message' => 'Work on holiday requests fetched successfully.',
                'data' => $holidayRequests
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch holiday requests.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function ApproveWorkOnHoliday(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'id' => 'required|exists:work_on_holidays,id',
                'status' => 'required|in:manager_approved,hr_approved,rejected,approved,cancelled',
                'remark' => 'nullable|string|max:255',
                'overtime_hours' => 'nullable|numeric|min:0|max:24',
                'bypass_manager' => 'nullable|boolean',
            ]);

            $employee = Employee::with('designation')->where('user_id', Auth::id())->firstOrFail();
            $requestWork = WorkOnHoliday::find($validated['id']);

            $checkHoliday = HolidayModel::find($requestWork->holiday_id);

            if (!$checkHoliday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Today is not a declared holiday for this organization.'
                ], 422);
            }

            $workRequest = WorkOnHoliday::findOrFail($validated['id']);

            $remarks = $validated['remark'] ?? null;
            $status = '';
            // Manager approval
            if ($employee->designation->level === 'manager') {
                if ($validated['status'] == 'approved') {
                    $status = 'approved';
                    $workRequest->update([
                        'approved_by_manager' => $employee->id,
                        'manager_remarks' => $remarks,
                        'status' => 'approved',
                    ]);
                } else if ($validated['status'] == 'rejected') {
                    $status = 'rejected';
                    $workRequest->update([
                        'approved_by_manager' => $employee->id,
                        'manager_remarks' => $remarks,
                        'status' => 'rejected',
                    ]);
                } else if ($validated['status'] == 'cancelled') {
                    $status = 'cancelled';
                    $workRequest->update([
                        'approved_by_manager' => $employee->id,
                        'manager_remarks' => $remarks,
                        'status' => 'cancelled',
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Work on holiday request ' . $status . ' by manager successfully.',
                ]);
            }

            // HR approval
            if ($employee->designation->level === 'hr') {
                if ($workRequest->status === 'pending' && empty($validated['bypass_manager'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Manager approval required before HR approval, or use bypass_manager.',
                    ], 403);
                }
                if ($validated['bypass_manager'] == 1) {
                    if ($validated['status'] == 'approved') {
                        $status = 'hr_approved';
                    } else {
                        $status = $validated['status'];
                    }

                    $workRequest->update([
                        'approved_by_hr' => $employee->id,
                        'hr_remarks' => $remarks,
                        'status' => $status,
                    ]);
                }

                // Create attendance only when HR approves
                if ($workRequest->status === 'hr_approved') {
                    $attendance = Attendance::firstOrCreate(
                        [
                            'employee_id' => $validated['employee_id'],
                            'date' => $workRequest->work_date,
                        ],
                        [
                            'status' => 'work_on_holiday',
                            'notes' => $remarks ?? 'Worked on holiday: ' . $checkHoliday->holiday_name,
                            'total_overtime_hours' => $validated['overtime_hours'] ?? 0,
                        ]
                    );

                    return response()->json([
                        'status' => true,
                        'message' => 'Work on holiday approved by HR and attendance recorded successfully.',
                        'data' => $attendance
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Work on holiday request ' . $status . ' by HR successfully.',
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Unauthorized to approve work on holiday.',
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to approve work on holiday.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
