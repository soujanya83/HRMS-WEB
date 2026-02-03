<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee\Attendance;
use App\Models\Employee\Employee;
use App\Models\Employee\Timesheet;
use App\Models\Employee\WorkOnHoliday;
use App\Models\HolidayModel;
use App\Models\manually_adjusted_attendance;
use App\Models\OrganizationAttendanceRule;
use App\Models\Rostering\Roster;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Rostering\Shift;
use App\Models\XeroConnection;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroTimesheet;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\API\V1\Employee\TimesheetController;
use App\Models\Payrolls;

class AttendanceController extends Controller
{


    // public function index(Request $request): JsonResponse
    // {
    //     $employee = Employee::where('user_id', Auth::id())->firstOrFail();
    //     $organizationId = $employee->organization_id;

    //     $query = Attendance::query()
    //         ->with([
    //             'employee:id,first_name,last_name,personal_email,phone_number,organization_id,department_id',
    //             'employee.department:id,name',
    //             'employee.organization:id,name',
    //             'employee.organization.attendanceRule:id,organization_id,shift_name,check_in,check_out,break_start,break_end,late_grace_minutes,half_day_after_minutes'
    //         ])
    //         ->whereHas('employee', function ($q) use ($organizationId) {
    //             $q->where('organization_id', $organizationId);
    //         })
    //         ->orderBy('date', 'desc')
    //         ->orderBy('employee_id');

    //     // ---------------------------------------------------------
    //     // 1️⃣ Filter by STATUS
    //     // ---------------------------------------------------------
    //     if ($request->filled('status') && $request->status !== "all") {
    //         $query->where('status', $request->status);
    //     }

    //     // ---------------------------------------------------------
    //     // 2️⃣ Filter by EMPLOYEE NAME (supports partial search)
    //     // ---------------------------------------------------------
    //     if ($request->filled('employee_name')) {
    //         $name = $request->employee_name;

    //         $query->whereHas('employee', function ($q) use ($name) {
    //             $q->where('first_name', 'LIKE', "%{$name}%")
    //                 ->orWhere('last_name', 'LIKE', "%{$name}%");
    //         });
    //     }

    //     // ---------------------------------------------------------
    //     // 3️⃣ Filter by DEPARTMENT
    //     // ---------------------------------------------------------
    //     if ($request->filled('department') && $request->department !== "all") {
    //         $query->whereHas('employee', function ($q) use ($request) {
    //             $q->where('department_id', $request->department);
    //         });
    //     }

    //     // ---------------------------------------------------------
    //     // 4️⃣ Filter by DATE RANGE
    //     // ---------------------------------------------------------



    //     if ($request->filled('startDate')) {
    //         $startDate = $this->parseDMY($request->startDate);
    //         if (!$startDate) {
    //             return response()->json(['success' => false, 'message' => 'Invalid start date. Format: d-m-Y'], 422);
    //         }
    //         $query->whereDate('date', '>=', $startDate);
    //     }

    //     if ($request->filled('endDate')) {
    //         $endDate = $this->parseDMY($request->endDate);
    //         if (!$endDate) {
    //             return response()->json(['success' => false, 'message' => 'Invalid end date. Format: d-m-Y'], 422);
    //         }
    //         $query->whereDate('date', '<=', $endDate);
    //     }

    //     // ---------------------------------------------------------
    //     // Final Pagination
    //     // ---------------------------------------------------------
    //     $attendances = $query->paginate(20);

    //     return response()->json([
    //         'success' => true,
    //         'data' => $attendances,
    //         'message' => 'Attendances retrieved successfully.'
    //     ]);
    // }

    public function index(Request $request)
    {
        $query = Attendance::with('employee');

        /**
         * Filter by organization
         */
        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        /**
         * Filter by employee
         */
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        /**
         * Filter by status
         */
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /**
         * Filter by single date
         */
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        /**
         * Filter by date range
         */
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('date', [
                $request->from_date,
                $request->to_date
            ]);
        }

        /**
         * Sorting latest first
         */
        $query->orderBy('date', 'desc');

        /**
         * Pagination
         */
        $attendances = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    public function EmployeeAttendanceSummary(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();
        $organizationId = $employee->organization_id;

        // ---------------------------------------------------------
        // BASE QUERY – Apply filters later
        // ---------------------------------------------------------
        $query = Attendance::whereHas('employee', function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId);
        });

        // ---------------------------------------------------------
        // DATE RANGE FILTERS (d-m-Y → Y-m-d)
        // ---------------------------------------------------------
        if ($request->filled('startDate') || $request->filled('start_date')) {

            $rawStart = $request->startDate ?? $request->start_date;

            $startDate = $this->parseDMY($rawStart);

            if (!$startDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid start date. Allowed format: d-m-Y or d/m/Y'
                ], 422);
            }

            $query->whereDate('date', '>=', $startDate);
        }


        if ($request->filled('endDate') || $request->filled('end_date')) {

            $rawEnd = $request->endDate ?? $request->end_date;

            $endDate = $this->parseDMY($rawEnd);

            if (!$endDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid end date. Allowed format: d-m-Y or d/m/Y'
                ], 422);
            }

            $query->whereDate('date', '<=', $endDate);
        }


        // ---------------------------------------------------------
        // COUNTS
        // ---------------------------------------------------------
        $totalAttendances = $query->count(); // Number of days having attendance records

        // Clone query for specific status-based counts
        $presentCount = (clone $query)->where('status', 'present')->count();
        $absentCount  = (clone $query)->where('status', 'absent')->count();
        $leaveCount   = (clone $query)->where('status', 'on_leave')->count();

        // Get late arrivals & on-time arrivals
        $lateArrivals = (clone $query)
            ->where('status', 'present')
            ->where('is_late', 1)
            ->count();

        $onTimeArrivals = (clone $query)
            ->where('status', 'present')
            ->where('is_late', 0)
            ->count();

        // ---------------------------------------------------------
        // TOTAL EMPLOYEES IN ORG
        // ---------------------------------------------------------
        $totalEmployees = Employee::where('organization_id', $organizationId)->count();

        // ---------------------------------------------------------
        // PERCENTAGE CALCULATIONS (SAFE)
        // ---------------------------------------------------------
        $presentRate = ($totalAttendances > 0)
            ? round(($presentCount / $totalAttendances) * 100, 2)
            : 0;

        $absenceRate = ($totalAttendances > 0)
            ? round(($absentCount / $totalAttendances) * 100, 2)
            : 0;

        $leaveRate = ($totalAttendances > 0)
            ? round(($leaveCount / $totalAttendances) * 100, 2)
            : 0;

        $lateArrivalRate = ($presentCount > 0)
            ? round(($lateArrivals / $presentCount) * 100, 2)
            : 0;

        $onTimeRate = ($presentCount > 0)
            ? round(($onTimeArrivals / $presentCount) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                // Raw numbers
                'total_employees'     => $totalEmployees,
                'total_attendances'   => $totalAttendances,
                'present_count'       => $presentCount,
                'absent_count'        => $absentCount,
                'on_leave_count'      => $leaveCount,
                'late_arrivals'       => $lateArrivals,
                'on_time_arrivals'    => $onTimeArrivals,

                // Percentages
                'present_rate'        => $presentRate . '%',
                'absence_rate'        => $absenceRate . '%',
                'leave_rate'          => $leaveRate . '%',
                'late_arrival_rate'   => $lateArrivalRate . '%',
                'on_time_rate'        => $onTimeRate . '%',
            ],
            'message' => 'Attendance summary retrieved successfully.'
        ]);
    }




    private function parseDMY($date)
    {
        try {
            // remove trailing spaces
            $date = trim($date);

            // convert / to -
            $date = str_replace('/', '-', $date);

            return Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; // return null if invalid
        }
    }

    /**
     * Store a newly created attendance record.
     */
   public function store(Request $request, TimesheetController $Timesheet): JsonResponse
{
    try {
        /* ============================
         | 1. VALIDATION
         ============================ */
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date'        => ['required', 'date'],
            'check_in'    => ['nullable', 'date_format:H:i'],
            'check_out'   => ['nullable', 'date_format:H:i', 'after_or_equal:check_in'],
            'status'      => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        /* ============================
         | 2. EMPLOYEE + TIMEZONE
         ============================ */
        $employee = Employee::with('organization:id,timezone')
            ->findOrFail($validated['employee_id']);

        $timezone = $employee->organization->timezone ?? 'Australia/Sydney';

        /* ============================
         | 3. DATE (TIMEZONE SAFE)
         ============================ */
        $dateInTz = Carbon::parse($validated['date'])
            ->setTimezone($timezone)
            ->format('Y-m-d');

        /* ============================
         | 4. CHECK-IN / CHECK-OUT
         ============================ */
        $checkInTime = $validated['check_in']
            ? Carbon::createFromFormat('Y-m-d H:i', "{$dateInTz} {$validated['check_in']}")
                ->setTimezone($timezone)
                ->format('H:i')
            : null;

        $checkOutTime = $validated['check_out']
            ? Carbon::createFromFormat('Y-m-d H:i', "{$dateInTz} {$validated['check_out']}")
                ->setTimezone($timezone)
                ->format('H:i')
            : null;

        /* ============================
         | 5. LATE CALCULATION (SAFE)
         ============================ */
        $is_late = 0;

        if ($checkInTime) {

            // Attendance rule (optional)
            $attendanceRule = OrganizationAttendanceRule::where(
                'organization_id',
                $employee->organization_id
            )->first();

            // Roster (optional)
            $rosterShiftIds = Roster::where('employee_id', $employee->id)
                ->whereDate('roster_date', $dateInTz) // ⚠ timezone safe
                ->pluck('shift_id');

            // Shift (optional)
            $shift = $rosterShiftIds->isNotEmpty()
                ? Shift::whereIn('id', $rosterShiftIds)->first()
                : null;

            // Run late logic ONLY if everything exists
            if ($attendanceRule && $shift && $shift->start_time) {

                $scheduledCheckIn = Carbon::parse($shift->start_time);
                $actualCheckIn    = Carbon::parse($checkInTime);

                $graceMinutes = $attendanceRule->late_grace_minutes ?? 0;
                $graceEnd     = $scheduledCheckIn->copy()->addMinutes($graceMinutes);

                if ($actualCheckIn->greaterThan($graceEnd)) {
                    $is_late = 1;
                }
            }
        }

        /* ============================
         | 6. TOTAL WORK HOURS
         ============================ */

   

        /* ============================
         | 7. DUPLICATE CHECK
         ============================ */

        
       $attendance = Attendance::where('employee_id', $employee->id)
    ->where('date', $dateInTz)
    ->first();

    if ($attendance) {
    $checkInTime = $checkInTime ?? $attendance->check_in;
        }   

         $totalWorkingHours = ($checkInTime && $checkOutTime)
            ? Carbon::parse($checkInTime)
                ->diffInMinutes(Carbon::parse($checkOutTime)) / 60
            : 0;

    

     if (!$attendance) {

      // ❌ Check-out without check-in
    if ($checkOutTime) {
        return response()->json([
            'success' => false,
            'message' => 'Check-in is required before check-out.',
        ], 422);
    }

    $attendance = Attendance::create([
        'employee_id'      => $employee->id,
        'organization_id'  => $employee->organization_id,
        'date'             => $dateInTz,
        'check_in'         => $checkInTime,
        'status'           => $validated['status'],
        'notes'            => $validated['notes'] ?? null,
        'is_late'          => $is_late,
        'total_work_hours' => 0,
    ]);

    } else {

        // Prevent double check-out
        if ($attendance->check_out) {
            return response()->json([
                'success' => false,
                'message' => 'Check-out already recorded for today.',
            ], 422);
        }

          if (!$attendance->check_in) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid attendance state. Check-in missing.',
        ], 422);
    }

        // Update check-out
        $attendance->update([
            'check_out'         => $checkOutTime,
            'total_work_hours'  => $totalWorkingHours,
            'notes'             => $validated['notes'] ?? $attendance->notes,
        ]);
    }



        /* ============================
         | 8. SAVE ATTENDANCE
         ============================ */
        // $attendance = Attendance::create([
        //     'employee_id'       => $employee->id,
        //     'date'              => $dateInTz,
        //     'check_in'          => $checkInTime,
        //     'check_out'         => $checkOutTime,
        //     'total_work_hours'  => $totalWorkingHours,
        //     'status'            => $validated['status'],
        //     'notes'             => $validated['notes'] ?? null,
        //     'is_late'           => $is_late,
        // ]);

        /* ============================
         | 9. PAYROLL / XERO (FUTURE)
         ============================ */
        // Payroll + Xero sync intentionally commented
        // Logic can be safely enabled later

            // $payroll = Payrolls::where('employee_id', $employee->id)
            //     ->where('payment_status', 'processed')
            //     ->orderBy('to_date', 'desc')
            //     ->first();

            // if ($payroll) {
            //     $lastPayrollDate = Carbon::parse($payroll->to_date);
            // } else {
            //     // No payroll ever → use JOINING DATE
            //     $lastPayrollDate = Carbon::parse($employee->joining_date);
            // }

            // if ($this->shouldTriggerPayroll($employee, $lastPayrollDate)) {
            //     // ⚠ FIXED: You were incorrectly passing $employee → must pass $attendance
            //     $this->syncTimesheetToXero($attendance, $lastPayrollDate);
            // }


        /* ============================
         | 10. RESPONSE
         ============================ */
        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data'    => $attendance->load('employee:id,first_name,last_name,personal_email'),
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {

        Log::error('Attendance Store Error', [
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong while recording attendance.',
        ], 500);
    }
}



    public function shouldTriggerPayroll($employee, $lastPayroll)
    {

        $today = Carbon::today();

        switch (strtolower($employee->payfrequency)) {


            case 'weekly':
                $nextPeriodEnd = $lastPayroll->copy()->addDays(7);
                break;

            case 'fortnightly':
                $nextPeriodEnd = $lastPayroll->copy()->addDays(14);

                break;

            case 'monthly':
                // If last to_date = 2025-01-31 → next = 2025-02-28 (or 29 leap year)
                $nextPeriodEnd = $lastPayroll->copy()->addMonthNoOverflow()->endOfMonth();
                break;

            default:
                return true;
        }

        // Trigger payroll if today matches next period end

        return $today->isSameDay($nextPeriodEnd);
    }

    // public function syncTimesheetToXero($attendance, $lastPayroll)
    // {
    //     try {

    //         $employee = Employee::find($attendance->employee_id);

    //         $xeroConnection = XeroConnection::where('organization_id', $employee->organization_id)
    //             ->where('is_active', 1)
    //             ->first();

    //         if (!$xeroConnection) {
    //             throw new \Exception("No active Xero connection");
    //         }

    //         $employeeXeroConnection = EmployeeXeroConnection::where('employee_id', $employee->id)
    //             ->where('xero_connection_id', $xeroConnection->id)
    //             ->first();

    //         if (!$employeeXeroConnection || !$employeeXeroConnection->xero_employee_id) {
    //             throw new \Exception("Xero employee mapping not found");
    //         }


    //         $calendarId = $this->getPayrollCallender($employeeXeroConnection);
    //         dd($calendarId['calendar']);

    //         // ----------------------------------------------
    //         // DETERMINE PAYFREQUENCY
    //         // ----------------------------------------------
    //         $payFreq = strtolower($employee->payfrequency);
    //         $lastPeriodEnd = Carbon::parse($lastPayroll);

    //         if ($payFreq === 'weekly') {

    //             $startPeriod = $lastPeriodEnd->copy()->addDay();          // next day after last payroll
    //             $endPeriod   = $startPeriod->copy()->addDays(6);           // 7 days total
    //             $totalDays   = 7;
    //         } elseif ($payFreq === 'fortnightly') {

    //             $startPeriod = $lastPeriodEnd->copy()->addDay();
    //             $endPeriod   = $startPeriod->copy()->addDays(13);          // 14 days
    //             $totalDays   = 14;
    //         } elseif ($payFreq === 'monthly') {

    //             $startPeriod = $lastPeriodEnd->copy()->addDay();
    //             $endPeriod   = $startPeriod->copy()->endOfMonth();         // last day of next month
    //             $totalDays   = $startPeriod->daysInMonth;
    //         } else {
    //             throw new \Exception("Invalid payfrequency for employee.");
    //         }

    //         // ----------------------------------------------
    //         // INITIALIZE TIMESHEET HOURS ARRAY
    //         // ----------------------------------------------
    //         $units = array_fill(0, $totalDays, 0);

    //         // ----------------------------------------------
    //         // MAP ATTENDANCE DATE TO INDEX
    //         // ----------------------------------------------
    //         $attendanceDate = Carbon::parse($attendance->date);

    //         // Example: Find where attendance date falls in the period
    //         // $dayIndex = $attendanceDate->diffInDays($startPeriod);
    //         $dayIndex = $startPeriod->diffInDays($attendanceDate);


    //         if ($dayIndex >= 0 && $dayIndex < $totalDays) {
    //             $units[$dayIndex] = $attendance->total_work_hours;   // Fill worked hours
    //         }

    //         // ----------------------------------------------
    //         // FETCH EARNINGS RATE
    //         // ----------------------------------------------
    //         $xeroData = json_decode($employeeXeroConnection->xero_data, true);

    //         $earningRateId =
    //             $xeroData['Employees'][0]['OrdinaryEarningsRateID']
    //             ?? ($xeroData['Employees'][0]['PayTemplate']['EarningsLines'][0]['EarningsRateID'] ?? null);

    //         if (!$earningRateId) {
    //             throw new \Exception("EarningsRateID not found in Xero employee data.");
    //         }

    //         // dd($earningRateId);

    //         if (!$earningRateId) {
    //             throw new \Exception("EarningsRateID missing for employee.");
    //         }

    //         // ----------------------------------------------
    //         // BUILD PAYLOAD (BASED ON PAYFREQUENCY)
    //         // ----------------------------------------------
    //         $payload = [
    //             [
    //                 "EmployeeID" => $employeeXeroConnection->xero_employee_id,
    //                 "StartDate"  => "/Date(" . ($startPeriod->timestamp * 1000) . "+0000)/",
    //                 "EndDate"    => "/Date(" . ($endPeriod->timestamp * 1000) . "+0000)/",
    //                 "Status"     => "DRAFT",
    //                 "TimesheetLines" => [
    //                     [
    //                         "EarningsRateID" => $earningRateId,
    //                         "NumberOfUnits"  => $units
    //                     ]
    //                 ]
    //             ]
    //         ];

    //         // ----------------------------------------------
    //         // SEND TO XERO
    //         // ----------------------------------------------
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $xeroConnection->access_token,
    //             'xero-tenant-id' => $xeroConnection->tenant_id,
    //             'Accept' => 'application/json'
    //         ])->post(
    //             "https://api.xero.com/payroll.xro/1.0/Timesheets",
    //             $payload
    //         );

    //         dd($response->body());

    //         $data = $response->json();

    //         if ($response->failed()) {
    //             Log::error('Xero Timesheet Error', ['response' => $data, 'payload' => $payload]);
    //             throw new \Exception("Xero Timesheet API failed: " . json_encode($data));
    //         }

    //         // ----------------------------------------------
    //         // SAVE TIMESHEET IN LOCAL DB
    //         // ----------------------------------------------
    //         $timesheetId = $data['Timesheets'][0]['TimesheetID'] ?? null;

    //         XeroTimesheet::updateOrCreate(
    //             [
    //                 'employee_id' => $employee->id,
    //                 'start_period' => $startPeriod->toDateString(),
    //             ],
    //             [
    //                 'end_period'   => $endPeriod->toDateString(),
    //                 'xero_timesheet_id' => $timesheetId,
    //                 'xero_data'    => json_encode($data),
    //                 'hours_json'   => json_encode($units),
    //                 'payfrequency' => $payFreq
    //             ]
    //         );

    //         return $data;
    //     } catch (\Exception $e) {
    //         Log::error("Timesheet Sync Failed", ['error' => $e->getMessage()]);
    //         return false;
    //     }
    // }

    public function syncTimesheetToXero($attendance)
    {
        try {
            $employee = Employee::find($attendance->employee_id);

            // --------------------
            // 1. Xero Connection
            $xeroConnection = XeroConnection::where('organization_id', $employee->organization_id)
                ->where('is_active', 1)
                ->first();

            if (!$xeroConnection) {
                throw new \Exception("No active Xero connection");
            }

            $employeeXeroConnection = EmployeeXeroConnection::where('employee_id', $employee->id)
                ->where('xero_connection_id', $xeroConnection->id)
                ->first();

            if (!$employeeXeroConnection || !$employeeXeroConnection->xero_employee_id) {
                throw new \Exception("Xero employee mapping not found");
            }

            // ------------------------------
            // 2. Fetch Employee's Calendar
            // ------------------------------
            $calendar = $this->getPayrollCallender($employeeXeroConnection);
            $calendar = $calendar['calendar'][0];  // extract first

            $calendarType = strtolower($calendar["CalendarType"]);
            $refDate      = $this->convertXeroDate($calendar["ReferenceDate"]);

            // ------------------------------
            // 3. Determine Pay Period
            // ------------------------------
            if ($calendarType === "weekly") {

                $startPeriod = $refDate->copy();
                while ($startPeriod->gt(Carbon::parse($attendance->date))) {
                    $startPeriod->subWeek();
                }
                while ($startPeriod->lte(Carbon::parse($attendance->date)->subWeek())) {
                    $startPeriod->addWeek();
                }

                $endPeriod = $startPeriod->copy()->addDays(6);
                $totalDays = 7;
            } elseif ($calendarType === "fortnightly") {

                $startPeriod = $refDate->copy();
                while ($startPeriod->gt(Carbon::parse($attendance->date))) {
                    $startPeriod->subDays(14);
                }
                while ($startPeriod->lte(Carbon::parse($attendance->date)->subDays(14))) {
                    $startPeriod->addDays(14);
                }

                $endPeriod = $startPeriod->copy()->addDays(13);
                $totalDays = 14;
            } elseif ($calendarType === "monthly") {

                // Monthly is based on ReferenceDate day
                $refDay = $refDate->day;
                $date   = Carbon::parse($attendance->date);

                // Determine start period
                if ($date->day >= $refDay) {
                    $startPeriod = Carbon::create($date->year, $date->month, $refDay);
                } else {
                    $startPeriod = Carbon::create($date->subMonth()->year, $date->subMonth()->month, $refDay);
                }

                // End period = next month day - 1
                $endPeriod = $startPeriod->copy()->addMonth()->subDay();

                $totalDays = $endPeriod->diffInDays($startPeriod) + 1;
            } else {
                throw new \Exception("Unsupported calendar type: $calendarType");
            }

            // ----------------------------------------------
            // 4. INIT EMPTY TIMESHEET ARRAY
            // ----------------------------------------------
            $units = array_fill(0, $totalDays, 0);

            // ----------------------------------------------
            // 5. Map Attendance Day → Timesheet Index
            // ----------------------------------------------
            $attendanceDate = Carbon::parse($attendance->date);

            $dayIndex = $startPeriod->diffInDays($attendanceDate);

            if ($dayIndex >= 0 && $dayIndex < $totalDays) {
                $units[$dayIndex] = $attendance->total_work_hours;
            }

            // ----------------------------------------------
            // 6. Get Earnings Rate
            // ----------------------------------------------
            $xeroData = json_decode($employeeXeroConnection->xero_data, true);
            $earningRateId = $xeroData["OrdinaryEarningsRateID"]
                ?? ($xeroData["PayTemplate"]["EarningsLines"][0]["EarningsRateID"] ?? null);

            if (!$earningRateId) {
                throw new \Exception("EarningsRateID missing.");
            }

            // ----------------------------------------------
            // 7. Build Timesheet Payload
            // ----------------------------------------------
            $payload = [
                [
                    "EmployeeID" => $employeeXeroConnection->xero_employee_id,
                    "StartDate"  => "/Date(" . ($startPeriod->timestamp * 1000) . "+0000)/",
                    "EndDate"    => "/Date(" . ($endPeriod->timestamp * 1000) . "+0000)/",
                    "Status"     => "DRAFT",
                    "TimesheetLines" => [
                        [
                            "EarningsRateID" => $earningRateId,
                            "NumberOfUnits"  => $units
                        ]
                    ]
                ]
            ];
            dd( $payload);

            // ----------------------------------------------
            // 8. API CALL TO XERO
            // ----------------------------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $xeroConnection->access_token,
                'xero-tenant-id' => $xeroConnection->tenant_id,
                'Accept' => 'application/json'
            ])->post("https://api.xero.com/payroll.xro/1.0/Timesheets", $payload);
            dd($response->body());

            $data = $response->json();

            if ($response->failed()) {
                Log::error("Xero error", ['response' => $data, 'payload' => $payload]);
                throw new \Exception("Xero API failed");
            }

            // ----------------------------------------------
            // 9. Save Locally
            // ----------------------------------------------
            $timesheetId = $data['Timesheets'][0]['TimesheetID'] ?? null;

            XeroTimesheet::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'start_period' => $startPeriod->toDateString(),
                ],
                [
                    'end_period'   => $endPeriod->toDateString(),
                    'xero_timesheet_id' => $timesheetId,
                    'xero_data'    => json_encode($data),
                    'hours_json'   => json_encode($units),
                    'calendar_type' => $calendarType
                ]
            );

            return $data;
        } catch (\Exception $e) {
            Log::error("Timesheet Sync Failed", ['error' => $e->getMessage()]);
            return false;
        }
    }


    public function convertXeroDate($dateString)
    {
        preg_match('/\d+/', $dateString, $match);
        return Carbon::createFromTimestamp($match[0] / 1000);
    }


    public function getPayrollCallender($employeeXeroConnection)
    {
        try {

            // -----------------------------------------
            // 1. Parse the stored Xero employee JSON
            // -----------------------------------------
            $xeroData = json_decode($employeeXeroConnection->xero_data, true);


            if (!$xeroData || empty($xeroData['Employees'][0]['PayrollCalendarID'])) {
                throw new \Exception("PayrollCalendarID missing in employee Xero data.");
            }

            $calendarId = $xeroData['Employees'][0]['PayrollCalendarID'];

            // -----------------------------------------
            // 2. Get Xero connection (tenant + token)
            // -----------------------------------------
            $xeroConnection = XeroConnection::find($employeeXeroConnection->xero_connection_id);

            if (!$xeroConnection) {
                throw new \Exception("Xero connection not found.");
            }

            $accessToken = $xeroConnection->access_token;
            $tenantId = $xeroConnection->tenant_id;

            // -----------------------------------------
            // 3. Call Xero API to fetch this calendar
            // -----------------------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'xero-tenant-id' => $tenantId,
                'Accept' => 'application/json'
            ])->get("https://api.xero.com/payroll.xro/1.0/PayrollCalendars/{$calendarId}");

            if ($response->failed()) {
                throw new \Exception("Failed to fetch payroll calendar: " . $response->body());
            }
           

            $data = $response->json();

            return [
                'status' => true,
                'calendar' => $data['PayrollCalendars'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
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
    // public function update(Request $request, Attendance $attendance): JsonResponse
    // {
    //     try {
    //         // Validate input first (basic rules; uniqueness handled manually after TZ adjustment)
    //         $validated = $request->validate([
    //             'employee_id' => ['sometimes', 'exists:employees,id'],
    //             'date' => ['sometimes', 'date'], // Remove unique; handle post-TZ
    //             'original_check_in' => ['sometimes', 'date_format:H:i'],
    //             'original_check_out' => ['sometimes', 'date_format:H:i', 'after_or_equal:original_check_out'], // Note: This assumes check_in is provided if check_out is; custom logic if needed
    //             'status' => ['sometimes', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
    //             'reason' => ['nullable', 'string', 'max:500'],
    //               'adjusted_check_in' => ['sometimes', 'date_format:H:i'],
    //             'adjusted_check_out' => ['sometimes', 'date_format:H:i', 'after_or_equal:check_in'], // Note: This assumes check_in is provided if check_out is; custom logic if needed
    //         ]);


    //         // Fetch employee and determine timezone (Australian default if not set)
    //         $employeeId = $request->input('employee_id', $attendance->employee_id); // Use new or existing employee_id
    //         // dd($employeeId);
    //         $employee = Employee::with('organization:id')
    //             ->findOrFail($employeeId);

    //             dd($employee);

    //         $timezone = $employee->organization->timezone ?? 'Australia/Sydney'; // Default to Sydney (AEST/AEDT)

    //         // Prepare update data with TZ-adjusted values
    //         $updateData = [
    //             'status' => $validated['status'] ?? $attendance->status,
    //             'notes' => $validated['notes'] ?? $attendance->notes,
    //         ];

    //         $newDate = $attendance->date; // Default to existing date
    //         if (isset($validated['date'])) {
    //             // Adjust new date to Australian timezone
    //             $newDate = Carbon::parse($validated['date'])->setTimezone($timezone)->format('Y-m-d');
    //             $updateData['date'] = $newDate;
    //         }

    //         // Adjust times if provided (combine with date - new or existing)
    //         $baseDate = $newDate; // Use new date if provided, else existing
    //         if (isset($validated['check_in'])) {
    //             $checkInFull = Carbon::createFromFormat('Y-m-d H:i', "{$baseDate} {$validated['check_in']}")->setTimezone($timezone);
    //             $updateData['check_in'] = $checkInFull->format('H:i');
    //         }

    //         if (isset($validated['check_out'])) {
    //             $checkOutFull = Carbon::createFromFormat('Y-m-d H:i', "{$baseDate} {$validated['check_out']}")->setTimezone($timezone);
    //             $updateData['check_out'] = $checkOutFull->format('H:i');
    //         }

    //         // Update employee_id if provided
    //         if (isset($validated['employee_id'])) {
    //             $updateData['employee_id'] = $validated['employee_id'];
    //         }

    //         // Manual uniqueness check if date is changing (ignore current record)
    //         if (isset($validated['date']) || isset($validated['employee_id'])) {
    //             $checkEmployeeId = $updateData['employee_id'] ?? $attendance->employee_id;
    //             $checkDate = $updateData['date'] ?? $attendance->date;

    //             $exists = Attendance::where('employee_id', $checkEmployeeId)
    //                 ->where('date', $checkDate)
    //                 ->where('id', '!=', $attendance->id) // Ignore current record
    //                 ->exists();

    //             if ($exists) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Attendance record already exists for this employee on the specified date.',
    //                     'errors' => ['date' => ['Attendance record already exists for this employee on the specified date.']],
    //                 ], 422);
    //             }
    //         }

    //         $attendance->update($updateData);


    //         return response()->json([
    //             'success' => true,
    //             'data' => $attendance->fresh()->load('employee:id,name,personal_email'),
    //             'message' => 'Attendance updated successfully.'
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         // Validation errors (422 response)
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed.',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Illuminate\Database\QueryException $e) {
    //         // Database-specific errors
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Database error occurred while updating attendance.',
    //             'errors' => ['database' => $e->getMessage()] // Sanitize in production
    //         ], 500);
    //     } catch (\Exception $e) {
    //         // Catch-all for unexpected errors
    //         // \Log::error('Attendance update error: ' . $e->getMessage(), ['request' => $request->all(), 'attendance_id' => $attendance->id]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An unexpected error occurred while updating attendance.',
    //             'errors' => $e->getMessage() // Generic; log details separately
    //         ], 500);
    //     }
    // }

    public function getAttendancechangeRequests()
    {

        $employee = Employee::where('user_id', Auth::id())->first();
        $organizationId = $employee->organization_id;
        // get all the employee of the same organization
        // then get attendance change requests of that employee
        $employees = Employee::where('organization_id', $organizationId)->get();

        $attendanceChangeRequests = manually_adjusted_attendance::whereIn('employee_id', $employees->pluck('id'))
            ->with('employee.department:id,name', 'employee:id,first_name,last_name,department_id', 'creator:id,first_name,last_name', 'approver:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attendanceChangeRequests,
            'message' => 'Attendance change requests retrieved successfully.'
        ]);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        try {

            $validated = $request->validate([
                'employee_id'       => ['sometimes', 'exists:employees,id'],
                'date'              => ['sometimes', 'date', 'date_format:Y/m/d'],
                'adjusted_check_in'  => ['sometimes', 'date_format:H:i'],
                'adjusted_check_out' => ['sometimes', 'date_format:H:i', 'after_or_equal:adjusted_check_in'],
                'reason'             => ['required', 'string', 'max:500'],
                'type' => ['required']
            ]);

            // ✅ Prevent multiple pending requests
            $alreadyPending = manually_adjusted_attendance::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();
            // dd($request->all());

            $attendance = Attendance::where('employee_id', $request->employee_id)->whereDate('date', $request->date)->first();
            // dd($attendance);

            $employee = Employee::where('user_id', Auth::id())->first();

            if ($alreadyPending) {
                return response()->json([
                    'success' => false,
                    'message' => 'A change request is already pending for this attendance.'
                ], 422);
            }

            $requestData = manually_adjusted_attendance::create([
                'attendance_id'     => $attendance->id,
                'employee_id'       => $attendance->employee_id,
                'date'              => $attendance->date,
                'original_check_in' => $attendance->check_in,
                'original_check_out' => $attendance->check_out,
                'adjusted_check_in' => $validated['adjusted_check_in'] ?? null,
                'adjusted_check_out' => $validated['adjusted_check_out'] ?? null,
                'reason'            => $validated['reason'],
                'created_by'        => $employee->id,
                'created_at'        => Carbon::now(),
                'type'              => $validated['type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance change request submitted for approval.',
                'data'    => $requestData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * requesting for the change in the attendance record
     */

    public function approveAttendanceChange(Request $r, $id): JsonResponse
    {
        try {
            $request = manually_adjusted_attendance::with('attendance')->findOrFail($id);

            if ($request->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This request is already processed.'
                ], 422);
            }

            $attendance = $request->attendance;

            // ✅ APPLY APPROVED TIMES
            $emplyee = Employee::where('user_id', Auth::id())->first();
            // dd($emplyee);

            if ($r->status == 'approved') {
                $attendance->update([
                    'check_in'  => $request->adjusted_check_in ?? $attendance->check_in,
                    'check_out' => $request->adjusted_check_out ?? $attendance->check_out,
                ]);

                $request->update([
                    'status' => 'approved',
                    'approved_by' => $emplyee->id,
                    'approved_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attendance updated successfully after approval.',
                    'attendance' => $attendance
                ]);
            } else {
                // If rejected, just update the request status
                $request->update(['status' => 'rejected']);

                return response()->json([
                    'success' => true,
                    'message' => 'Attendance change request has been rejected.',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
            $nowTime = Carbon::now();

            // ✅ Check if today is a holiday
            $checkHoliday = HolidayModel::where('organization_id', $employee->organization_id)
                ->whereDate('holiday_date', $todayDate)
                ->first();

            // ✅ Get working day / shift info
            $attendanceRule = OrganizationAttendanceRule::where([
                'organization_id' => $employee->organization_id,
            ])->first();

            $roster = Roster::where('employee_id', $employee->id)
                ->pluck('shift_id');

            $shiftDetails = Shift::whereIn('id', $roster)->first();
            // dd($shiftDetails);

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

            if ($attendanceRule) {
                $scheduledCheckIn = $attendanceRule->check_in;
                $actualCheckIn = $attendance->check_in;

                $lateGraceMinutes = $attendanceRule->late_grace_minutes ?? 0;
                $gracePeriodEnd = $scheduledCheckIn->copy()->addMinutes($lateGraceMinutes);

                if ($actualCheckIn->greaterThan($gracePeriodEnd)) {
                    // Mark as late
                    $attendance->update(['is_late' => 1]);
                } else {
                    // On time
                    $attendance->update(['is_late' => 0]);
                }
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


    public function getEmployeeAttendance($employeeId, $date): JsonResponse
    {
        try {
            $attendance = Attendance::where('employee_id', $employeeId)
                ->where('date', $date)
                ->first();
            // dd($attendance);

            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attendance record found for the specified employee and date.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'message' => 'Attendance record retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByEmployeeAndDate(Request $request): JsonResponse
{
    try {
        /* ============================
         | 1. VALIDATION
         ============================ */
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date'        => ['required', 'date'],
        ]);

        /* ============================
         | 2. EMPLOYEE + TIMEZONE
         ============================ */
        $employee = Employee::with('organization:id,timezone')
            ->findOrFail($validated['employee_id']);

        $timezone = $employee->organization->timezone ?? 'Australia/Sydney';

        /* ============================
         | 3. DATE (TIMEZONE SAFE)
         ============================ */
        $dateInTz = Carbon::parse($validated['date'])
            ->setTimezone($timezone)
            ->format('Y-m-d');

        /* ============================
         | 4. FETCH ATTENDANCE
         ============================ */
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $dateInTz)
            ->with([
                'employee:id,first_name,last_name,personal_email'
            ])
            ->first();

        /* ============================
         | 5. RESPONSE
         ============================ */
        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance not found for this employee on the given date.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance fetched successfully.',
            'data'    => $attendance,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {

        \Log::error('Get Attendance Error', [
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong while fetching attendance.',
        ], 500);
    }
}

}
