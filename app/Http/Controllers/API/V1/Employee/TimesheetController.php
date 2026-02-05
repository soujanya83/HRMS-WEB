<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\API\V1\Xero\XeroEmployeeController;
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
use Carbon\CarbonPeriod;
use App\Models\XeroConnection;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroTimesheet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payrolls;
use App\Models\XeroPayRun;
use App\Models\XeroPayRunEmployee;
use App\Models\XeroPayRunEmployeeTimesheet;
use App\Models\PayPeriod;
use App\Models\Project;



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
    // public function index(): JsonResponse
    // {
    //     try {
    //         $timesheets = Timesheet::with([
    //             'project:id,name',
    //             'task:id,title',
    //             'employee:id,first_name,last_name,employee_code',
    //         ])->latest()->get();

    //         return response()->json([
    //             'status' => true,
    //             'data' => $timesheets,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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
    // public function update(Request $request, $id): JsonResponse
    // {
    //     try {
    //         $timesheet = Timesheet::find($id);

    //         if (!$timesheet) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Timesheet not found.',
    //             ], 404);
    //         }

    //         $validator = Validator::make($request->all(), [
    //             'project_id' => 'sometimes|exists:projects,id',
    //             'task_id' => 'nullable|exists:tasks,id',
    //             'employee_id' => 'sometimes|exists:employees,id',
    //             'date' => 'nullable|date',
    //             'hours' => 'nullable|numeric|min:0|max:24',
    //             'description' => 'nullable|string',
    //             'status' => 'nullable|in:submitted,approved,rejected',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'errors' => $validator->errors(),
    //             ], 422);
    //         }

    //         $timesheet->update($validator->validated());

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Timesheet updated successfully.',
    //             'data' => $timesheet,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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

    public function syncTimesheetToXero($attendance, $lastPayroll)
    {
        try {
            dd($attendance);
            $employee = Employee::find($attendance->employee_id);

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

            // ----------------------------------------------
            // DETERMINE PAYFREQUENCY
            // ----------------------------------------------
            $payFreq = strtolower($employee->payfrequency);
            $lastPeriodEnd = Carbon::parse($lastPayroll);

            if ($payFreq === 'weekly') {

                $startPeriod = $lastPeriodEnd->copy()->addDay();          // next day after last payroll
                $endPeriod   = $startPeriod->copy()->addDays(6);           // 7 days total
                $totalDays   = 7;
            } elseif ($payFreq === 'fortnightly') {

                $startPeriod = $lastPeriodEnd->copy()->addDay();
                $endPeriod   = $startPeriod->copy()->addDays(13);          // 14 days
                $totalDays   = 14;
            } elseif ($payFreq === 'monthly') {

                $startPeriod = $lastPeriodEnd->copy()->addDay();
                $endPeriod   = $startPeriod->copy()->endOfMonth();         // last day of next month
                $totalDays   = $startPeriod->daysInMonth;
            } else {
                throw new \Exception("Invalid payfrequency for employee.");
            }

            // ----------------------------------------------
            // INITIALIZE TIMESHEET HOURS ARRAY
            // ----------------------------------------------
            $units = array_fill(0, $totalDays, 0);

            // ----------------------------------------------
            // MAP ATTENDANCE DATE TO INDEX
            // ----------------------------------------------
            $attendanceDate = Carbon::parse($attendance->date);

            // Example: Find where attendance date falls in the period
            $dayIndex = $attendanceDate->diffInDays($startPeriod);

            if ($dayIndex >= 0 && $dayIndex < $totalDays) {
                $units[$dayIndex] = $attendance->hours_worked;   // Fill worked hours
            }

            // ----------------------------------------------
            // FETCH EARNINGS RATE
            // ----------------------------------------------
            $xeroData = json_decode($employeeXeroConnection->xero_data, true);

            $earningRateId = $xeroData['EarningsRateID'] ?? null;

            if (!$earningRateId) {
                throw new \Exception("EarningsRateID missing for employee.");
            }

            // ----------------------------------------------
            // BUILD PAYLOAD (BASED ON PAYFREQUENCY)
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

            // ----------------------------------------------
            // SEND TO XERO
            // ----------------------------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $xeroConnection->access_token,
                'xero-tenant-id' => $xeroConnection->tenant_id,
                'Accept' => 'application/json'
            ])->post(
                "https://api.xero.com/payroll.xro/1.0/Timesheets",
                $payload
            );

            $data = $response->json();

            if ($response->failed()) {
                Log::error('Xero Timesheet Error', ['response' => $data, 'payload' => $payload]);
                throw new \Exception("Xero Timesheet API failed: " . json_encode($data));
            }

            // ----------------------------------------------
            // SAVE TIMESHEET IN LOCAL DB
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
                    'payfrequency' => $payFreq
                ]
            );

            return $data;
        } catch (\Exception $e) {
            Log::error("Timesheet Sync Failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // public function getpayperiod($employee_id)
    // {
    //     try {
    //         // ----------------------------------------
    //         // 1. Get Employee ↔ Xero mapping
    //         // ----------------------------------------
    //         $mapping = EmployeeXeroConnection::where('employee_id', $employee_id)->first();
    //         // dd($mapping);

    //         if (!$mapping) {
    //             throw new \Exception("Employee not linked with Xero.");
    //         }

    //         if (!$mapping->xerocalenderId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'calenderid not found '
    //             ]);
    //         }

    //         $calendarId = $mapping->xerocalenderId;
    //         // dd($calendarId);

    //         // ----------------------------------------
    //         // 2. Get Xero connection (token + tenant)
    //         // ----------------------------------------
    //         $connection = XeroConnection::find($mapping->xero_connection_id);

    //         if (!$connection) {
    //             throw new \Exception("Xero connection not found.");
    //         }

    //         // ----------------------------------------
    //         // 3. Fetch payroll calendar from Xero API
    //         // ----------------------------------------
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $connection->access_token,
    //             'xero-tenant-id' => $connection->tenant_id,
    //             'Accept' => 'application/json'
    //         ])->get("https://api.xero.com/payroll.xro/1.0/PayrollCalendars/{$calendarId}");

    //         if ($response->failed()) {
    //             throw new \Exception("Failed to fetch payroll calendar: " . $response->body());
    //         }

    //         $calendar = $response->json()["PayrollCalendars"][0];

    //         // ----------------------------------------
    //         // 4. Extract Calendar Type + ReferenceDate
    //         // ----------------------------------------
    //         $calendarType = strtolower($calendar["CalendarType"]);
    //         $refDate      = $this->convertXeroDate($calendar["ReferenceDate"]);
    //         $refDay       = $refDate->day;

    //         $today = Carbon::today();

    //         // ----------------------------------------
    //         // 5. Calculate Pay Period (Start & End)
    //         // ----------------------------------------
    //         if ($calendarType === "weekly") {

    //             // start = last refDate week before today
    //             $start = $refDate->copy();
    //             while ($start->lt($today->subWeek())) {
    //                 $start->addWeek();
    //             }

    //             $end = $start->copy()->addDays(6);
    //         } elseif ($calendarType === "fortnightly") {

    //             $start = $refDate->copy();
    //             while ($start->lt($today->subDays(14))) {
    //                 $start->addDays(14);
    //             }

    //             $end = $start->copy()->addDays(13);
    //         } elseif ($calendarType === "monthly") {

    //             // If today date ≥ reference day → period starts this month
    //             if ($today->day >= $refDay) {
    //                 $start = Carbon::create($today->year, $today->month, $refDay);
    //             } else {
    //                 $prev = $today->copy()->subMonth();
    //                 $start = Carbon::create($prev->year, $prev->month, $refDay);
    //             }

    //             $end = $start->copy()->addMonth()->subDay();
    //         } else {
    //             throw new \Exception("Unsupported calendar type: " . $calendarType);
    //         }

    //         return [
    //             'status' => true,
    //             'calendar_type' => $calendarType,
    //             'calendar_id'   => $calendarId,
    //             'pay_period_start' => $start->format('Y-m-d'),
    //             'pay_period_end'   => $end->format('Y-m-d'),
    //         ];
    //     } catch (\Exception $e) {

    //         return [
    //             'status' => false,
    //             'message' => $e->getMessage()
    //         ];
    //     }
    // }

    public function getpayperiod($employee_id)
    {
        try {
            // ----------------------------------------
            // 1. Get Employee ↔ Xero mapping
            // ----------------------------------------
            $mapping = EmployeeXeroConnection::where('employee_id', $employee_id)->first();

            if (!$mapping) {
                throw new \Exception("Employee not linked with Xero.");
            }

            if (!$mapping->xerocalenderId) {
                return [
                    'status'  => false,
                    'message' => 'Calendar ID not found for employee.'
                ];
            }

            $calendarId = $mapping->xerocalenderId;

            // ----------------------------------------
            // 2. Get Xero connection
            // ----------------------------------------
            $connection = XeroConnection::find($mapping->xero_connection_id);

            if (!$connection) {
                throw new \Exception("Xero connection not found.");
            }

            // ----------------------------------------
            // 3. Fetch payroll calendar from Xero API
            // ----------------------------------------
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $connection->access_token,
                'xero-tenant-id' => $connection->tenant_id,
                'Accept'         => 'application/json'
            ])->get("https://api.xero.com/payroll.xro/1.0/PayrollCalendars/{$calendarId}");

            if ($response->failed()) {
                throw new \Exception("Failed to fetch payroll calendar: " . $response->body());
            }

            $calendar = $response->json()["PayrollCalendars"][0];

            // ----------------------------------------
            // 4. Extract key values
            // ----------------------------------------
            $calendarType = strtolower($calendar["CalendarType"]);
            $referenceDate = $this->convertXeroDate($calendar["ReferenceDate"]);
            $paymentDate   = $this->convertXeroDate($calendar["PaymentDate"]);
            $refDay        = $referenceDate->day;

            $today = Carbon::today();
            $start = null;
            $end   = null;

            // ----------------------------------------
            // 5. Calculate Pay Period Based on Calendar Type
            // ----------------------------------------
            if ($calendarType === "weekly") {

                $start = $referenceDate->copy();
                while ($start->lte($today->subWeek())) {
                    $start->addWeek();
                }

                $end = $start->copy()->addDays(6);
            } elseif ($calendarType === "fortnightly") {

                $start = $referenceDate->copy();
                while ($start->lte($today->subDays(14))) {
                    $start->addDays(14);
                }

                $end = $start->copy()->addDays(13);
            } elseif ($calendarType === "monthly") {

                if ($today->day >= $refDay) {
                    $start = Carbon::create($today->year, $today->month, $refDay);
                } else {
                    $prev = $today->copy()->subMonth();
                    $start = Carbon::create($prev->year, $prev->month, $refDay);
                }

                $end = $start->copy()->addMonth()->subDay();
            } else {
                throw new \Exception("Unsupported calendar type: " . $calendarType);
            }

            // ----------------------------------------
            // 6. Return Full Pay Period + Payment Date
            // ----------------------------------------
            return [
                'status' => true,
                'calendar_id'   => $calendarId,
                'calendar_type' => $calendarType,

                'reference_date' => $referenceDate->format('Y-m-d'),
                'payment_date'   => $paymentDate->format('Y-m-d'),  // <-- PAY DATE

                'pay_period_start' => $start->format('Y-m-d'),
                'pay_period_end'   => $end->format('Y-m-d'),
            ];
        } catch (\Exception $e) {

            return [
                'status'  => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function convertXeroDate($dateString)
    {
        preg_match('/\d+/', $dateString, $match);
        return Carbon::createFromTimestamp($match[0] / 1000);
    }

    //    public function CreateTimeSheetManually(Request $request)
    // {
    //     try {
    //         // Validate input
    //         $validated = $request->validate([
    //             'employee_id' => 'required|exists:employees,id',
    //             'calendar_id' => 'required|string',
    //             'pay_period_start' => 'required|date',
    //             'pay_period_end' => 'required|date',
    //         ]);

    //         $employeeId = $validated['employee_id'];
    //         $start = Carbon::parse($validated['pay_period_start']);
    //         $end   = Carbon::parse($validated['pay_period_end']);

    //         // ----------------------------------------
    //         // 1. Generate array of dates within period
    //         // ----------------------------------------
    //         $periodDays = [];
    //         $cursor = $start->copy();

    //         while ($cursor->lte($end)) {
    //             $periodDays[] = $cursor->copy();
    //             $cursor->addDay();
    //         }

    //         $totalDays = count($periodDays);

    //         // ----------------------------------------
    //         // 2. Fetch Attendance for the employee
    //         // ----------------------------------------
    //         $attendances = Attendance::where('employee_id', $employeeId)
    //             ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
    //             ->get()
    //             ->keyBy('date');
    //             // dd($attendances);

    //         // ----------------------------------------
    //         // 3. Build NumberOfUnits array for Xero
    //         // ----------------------------------------
    //         $units = [];

    //         foreach ($periodDays as $day) {
    //             $date = $day->toDateString();

    //             if (isset($attendances[$date])) {
    //                 $units[] = floatval($attendances[$date]->total_work_hours ?? 0);
    //             } else {
    //                 $units[] = 0;   // no attendance → 0 hours
    //             }
    //         }

    //         // ----------------------------------------
    //         // 4. Get employee Xero mapping
    //         // ----------------------------------------
    //         $mapping = EmployeeXeroConnection::where('employee_id', $employeeId)->first();

    //         if (!$mapping) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Employee not linked with Xero.'
    //             ], 400);
    //         }

    //         $xeroEmployeeId = $mapping->xero_employee_id;

    //         $connection = XeroConnection::where('organization_id',$mapping->organization_id)->first();

    //         // ----------------------------------------
    //         // 5. Fetch EarningsRateID
    //         // ----------------------------------------
    //         // $xeroData = json_decode($mapping->xero_data, true);

    //         $earningRateId = $mapping->EarningsRateID ?? $mapping->OrdinaryEarningsRateID;

    //         if (!$earningRateId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'EarningsRateID not found.'
    //             ]);
    //         }

    //         // ----------------------------------------
    //         // 6. Build Timesheet Payload for Xero
    //         // ----------------------------------------
    //         $payload = [
    //             [
    //                 "EmployeeID" => $xeroEmployeeId,
    //                 "StartDate"  => "/Date(" . ($start->timestamp * 1000) . "+0000)/",
    //                 "EndDate"    => "/Date(" . ($end->timestamp * 1000) . "+0000)/",
    //                 "Status"     => "DRAFT",
    //                 "TimesheetLines" => [
    //                     [
    //                         "EarningsRateID" => $earningRateId,
    //                         "NumberOfUnits"  => $units
    //                     ]
    //                 ]
    //             ]
    //         ];
    //         // dd($payload);

    //          $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $connection->access_token,
    //             'xero-tenant-id' => $connection->tenant_id,
    //             'Accept' => 'application/json'
    //         ])->post("https://api.xero.com/payroll.xro/1.0/Timesheets");

    //         dd($response->body());

    //         // =============================
    //         // REMOVE dd() — Now returning!
    //         // =============================

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Timesheet payload generated successfully.',
    //             'payload' => $payload,
    //             'units' => $units
    //         ]);

    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'status' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function CreateTimeSheetManually(Request $request)
    // {
    //     try {
    //         // ----------------------------------------
    //         // 1. Validate Input
    //         // ----------------------------------------
    //         $validated = $request->validate([
    //             'employee_id'      => 'required|exists:employees,id',
    //             'calendar_id'      => 'required|string',
    //             'pay_period_start' => 'required|date',
    //             'pay_period_end'   => 'required|date',
    //         ]);

    //         $employeeId = $validated['employee_id'];
    //         $start      = Carbon::parse($validated['pay_period_start']);
    //         $end        = Carbon::parse($validated['pay_period_end']);

    //         // ----------------------------------------
    //         // 2. Generate Period Days
    //         // ----------------------------------------
    //         $periodDays = [];
    //         $cursor = $start->copy();

    //         while ($cursor->lte($end)) {
    //             $periodDays[] = $cursor->copy();
    //             $cursor->addDay();
    //         }

    //         $totalDays = count($periodDays);

    //         // ----------------------------------------
    //         // 3. Fetch Attendance (FIXED: Correct key format)
    //         // ----------------------------------------
    //         $attendances = Attendance::where('employee_id', $employeeId)
    //             ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
    //             ->get()
    //             ->mapWithKeys(function ($att) {
    //                 // FORCE key as "Y-m-d"
    //                 return [$att->date->format('Y-m-d') => $att];
    //             });

    //         // ----------------------------------------
    //         // 4. Build NumberOfUnits array (FIXED)
    //         // ----------------------------------------
    //         $units = [];

    //         foreach ($periodDays as $day) {
    //             $date = $day->toDateString(); // "2025-12-30"

    //             if ($attendances->has($date)) {
    //                 $units[] = floatval($attendances[$date]->total_work_hours ?? 0);
    //             } else {
    //                 $units[] = 0; // No attendance = 0 hours
    //             }
    //         }

    //         // ----------------------------------------
    //         // 5. Fetch employee → Xero mapping
    //         // ----------------------------------------
    //         $mapping = EmployeeXeroConnection::where('employee_id', $employeeId)->first();

    //         if (!$mapping) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Employee is not linked with Xero.'
    //             ], 400);
    //         }

    //         $xeroEmployeeId = $mapping->xero_employee_id;
    //         $xeroData = json_decode($mapping->xero_data, true);

    //         // ----------------------------------------
    //         // 6. Extract EarningsRateID (FIXED)
    //         // ----------------------------------------
    //         $earningRateId =
    //             $xeroData['Employees'][0]['OrdinaryEarningsRateID']
    //             ?? ($xeroData['Employees'][0]['PayTemplate']['EarningsLines'][0]['EarningsRateID'] ?? null);

    //         if (!$earningRateId) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'EarningsRateID not found in employee Xero data.'
    //             ], 400);
    //         }

    //         // ----------------------------------------
    //         // 7. Fetch Xero Tenant + Token
    //         // ----------------------------------------
    //         $connection = XeroConnection::find($mapping->xero_connection_id);

    //         if (!$connection) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Xero connection missing.'
    //             ], 400);
    //         }

    //         // ----------------------------------------
    //         // 8. Build Timesheet Payload (CORRECT)
    //         // ----------------------------------------
    //         $payload = [
    //             [
    //                 "EmployeeID" => $xeroEmployeeId,
    //                 "StartDate"  => "/Date(" . ($start->timestamp * 1000) . "+0000)/",
    //                 "EndDate"    => "/Date(" . ($end->timestamp * 1000) . "+0000)/",
    //                 "Status"     => "DRAFT",
    //                 "TimesheetLines" => [
    //                     [
    //                         "EarningsRateID" => $earningRateId,
    //                         "NumberOfUnits"  => $units
    //                     ]
    //                 ]
    //             ]
    //         ];

    //         // TEMP FOR DEBUG: SHOW PAYLOAD BEFORE SENDING
    //         // dd($payload);

    //         // ----------------------------------------
    //         // 9. SEND TO XERO (FIXED — payload included)
    //         // ----------------------------------------
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $connection->access_token,
    //             'xero-tenant-id' => $connection->tenant_id,
    //             'Accept' => 'application/json'
    //         ])->post(
    //             "https://api.xero.com/payroll.xro/1.0/Timesheets",
    //             $payload
    //         );

    //         // TEMP FOR DEBUG
    //         // dd($response->body());

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'status'   => false,
    //                 'message'  => 'Xero API failed',
    //                 'response' => $response->json(),
    //                 'payload'  => $payload
    //             ], 422);
    //         }

    //         $data = $response->json();

    //         return response()->json([
    //             'status'    => true,
    //             'message'   => 'Timesheet created successfully.',
    //             'payload'   => $payload,
    //             'xero_data' => $data
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'error'  => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function CreateTimeSheetManually(Request $request)
    {
        try {
            // ----------------------------------------
            // 1. Validate Input
            // ----------------------------------------
            $validated = $request->validate([
                'employee_id'      => 'required|exists:employees,id',
                'calendar_id'      => 'required|string',
                'pay_period_start' => 'required|date',
                'pay_period_end'   => 'required|date',
                'payment_date' => 'required|date'
            ]);

            $employeeId = $validated['employee_id'];
            $start      = Carbon::parse($validated['pay_period_start']);
            $end        = Carbon::parse($validated['pay_period_end']);
            $paymentDate = Carbon::parse($validated['payment_date']);

            // ----------------------------------------
            // 2. Generate Period Dates
            // ----------------------------------------
            $periodDays = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $periodDays[] = $d->copy();
            }

            // ----------------------------------------
            // 3. Fetch Attendance
            // ----------------------------------------
            $attendances = Attendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->mapWithKeys(fn($att) => [$att->date->format('Y-m-d') => $att]);

            // Build NumberOfUnits
            $units = [];
            foreach ($periodDays as $day) {
                $date = $day->toDateString();
                $units[] = isset($attendances[$date])
                    ? floatval($attendances[$date]->total_work_hours ?? 0)
                    : 0;
            }

            // ----------------------------------------
            // 4. Xero Employee Mapping
            // ----------------------------------------
            $mapping = EmployeeXeroConnection::where('employee_id', $employeeId)->firstOrFail();
            $xeroEmployeeId = $mapping->xero_employee_id;
            $xeroData = json_decode($mapping->xero_data, true);

            // Get Earnings Rate
            $earningRateId =
                $xeroData['Employees'][0]['OrdinaryEarningsRateID']
                ?? ($xeroData['Employees'][0]['PayTemplate']['EarningsLines'][0]['EarningsRateID'] ?? null);

            if (!$earningRateId) {
                return response()->json([
                    'status'  => false,
                    'message' => 'EarningsRateID not found.'
                ], 400);
            }

            // ----------------------------------------
            // 5. Fetch Xero Connection
            // ----------------------------------------
            $connection = XeroConnection::findOrFail($mapping->xero_connection_id);

            // ----------------------------------------
            // 6. Build Timesheet Payload
            // ----------------------------------------

            $status = $request->status ?? "DRAFT";
            $payload = [
                [
                    "EmployeeID" => $xeroEmployeeId,
                    "StartDate"  => "/Date(" . ($start->timestamp * 1000) . "+0000)/",
                    "EndDate"    => "/Date(" . ($end->timestamp * 1000) . "+0000)/",
                    "Status"     => $status,
                    "TimesheetLines" => [
                        [
                            "EarningsRateID" => $earningRateId,
                            "NumberOfUnits"  => $units
                        ]
                    ]
                ]
            ];

            // ----------------------------------------
            // 7. SEND TO XERO
            // ----------------------------------------
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $connection->access_token,
                'xero-tenant-id' => $connection->tenant_id,
                'Accept'         => 'application/json'
            ])->post(
                "https://api.xero.com/payroll.xro/1.0/Timesheets",
                $payload
            );

            if ($response->failed()) {
                return response()->json([
                    'status'   => false,
                    'message'  => 'Xero API failed',
                    'response' => $response->json(),
                    'payload'  => $payload,
                ], 422);
            }

            $xeroResult = $response->json();

            // Extract Xero Timesheet ID
            $xeroTimesheetId = $xeroResult['Timesheets'][0]['TimesheetID'] ?? null;

            // ----------------------------------------
            // 8. SAVE TO DATABASE (SEPARATE FUNCTION)
            // ----------------------------------------
            $saved = $this->saveXeroTimesheetToDB(
                $mapping,
                $connection,
                $xeroTimesheetId,
                $start,
                $end,
                $units,
                $xeroResult,
                $paymentDate
            );

            return response()->json([
                'status'       => true,
                'message'      => 'Timesheet created & saved successfully.',
                'xero_data'    => $xeroResult,
                'db_record'    => $saved
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error'  => $e->getMessage()
            ], 500);
        }
    }




    public function saveXeroTimesheetToDB($mapping, $connection, $xeroTimesheetId, $start, $end, $units, $xeroResult, $paymentDate)
    {
        $totalHours = array_sum($units);

        return XeroTimesheet::updateOrCreate(
            [
                'xero_timesheet_id' => $xeroTimesheetId
            ],
            [
                'organization_id'             => $mapping->organization_id,
                'employee_xero_connection_id' => $mapping->id,
                'xero_connection_id'          => $connection->id,
                'xero_employee_id'            => $mapping->xero_employee_id,
                'payment_date' => $paymentDate,

                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),

                'status'       => 'DRAFT',
                'total_hours'  => $totalHours,
                'ordinary_hours' => $totalHours,
                'overtime_hours' => 0,

                'timesheet_lines' => json_encode($units),
                'xero_data'       => json_encode($xeroResult),

                'is_synced'      => 1,
                'last_synced_at' => now(),
            ]
        );
    }


    public function reviewTimesheet(Request $request)
    {
        try {

            // ----------------------------------------
            // Validate optional filters
            // ----------------------------------------
            $validated = $request->validate([
                'start_date'      => 'nullable|date',
                'end_date'        => 'nullable|date',
                'employee_id'     => 'nullable|exists:employees,id',
                'organization_id' => 'nullable|exists:organizations,id',
            ]);

            $employeeId     = $validated['employee_id'] ?? null;
            $organizationId = $validated['organization_id'] ?? null;

            $start = $validated['start_date'] ?? null;
            $end   = $validated['end_date'] ?? null;

            // --------------------------------------------------------
            // 1️⃣ Validate employee belongs to organization (if both provided)
            // --------------------------------------------------------
            if (!empty($employeeId) && !empty($organizationId)) {
                $empOrgCheck = Employee::where('id', $employeeId)
                    ->where('organization_id', $organizationId)
                    ->first();

                if (!$empOrgCheck) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Employee does not belong to this organization.',
                    ], 400);
                }
            }

            // --------------------------------------------------------
            // 2️⃣ Fetch Xero Mapping for employee (if employee_id provided)
            // --------------------------------------------------------
            $xeroEmployeeId = null;

            if (!empty($employeeId)) {

                $mapping = EmployeeXeroConnection::where('employee_id', $employeeId)
                    ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                    ->first();

                if (!$mapping) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Employee not linked with Xero.',
                    ], 404);
                }

                $xeroEmployeeId = $mapping->xero_employee_id;
            }

            // --------------------------------------------------------
            // 3️⃣ Build Query (organization + employee + dates)
            // --------------------------------------------------------
            $query = XeroTimesheet::query();

            if (!empty($organizationId)) {
                $query->where('organization_id', $organizationId);
            }

            if (!empty($xeroEmployeeId)) {
                $query->where('xero_employee_id', $xeroEmployeeId);
            }

            if (!empty($start) && !empty($end)) {
                $query->whereDate('start_date', '>=', Carbon::parse($start))
                    ->whereDate('end_date', '<=', Carbon::parse($end));
            }

            // ALWAYS DESC ORDER (latest first)
            $timesheets = $query->orderBy('start_date', 'desc')->get();

            if ($timesheets->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No timesheets found.',
                ], 404);
            }

            // --------------------------------------------------------
            // 4️⃣ Build Response → Employee Details + Date-wise Units
            // --------------------------------------------------------
            $responseData = [];

            foreach ($timesheets as $ts) {

                $hours = json_decode($ts->timesheet_lines, true) ?? [];

                $dateWise = [];
                $cursor = Carbon::parse($ts->start_date);

                foreach ($hours as $index => $val) {
                    $dateWise[$cursor->copy()->addDays($index)->toDateString()] = $val;
                }

                // fetch employee mapped
                $empMap = EmployeeXeroConnection::where('xero_employee_id', $ts->xero_employee_id)->first();
                $employee = Employee::find($empMap->employee_id ?? null);

                $responseData[] = [
                    'timesheet_id'     => $ts->id,
                    'xero_timesheet_id' => $ts->xero_timesheet_id,

                    'employee'         => $employee ? [
                        'id'    => $employee->id,
                        'code'  => $employee->employee_code,
                        'name'  => $employee->first_name . ' ' . $employee->last_name,
                        'email' => $employee->personal_email,
                        'phone' => $employee->phone_number,
                    ] : null,

                    'period' => [
                        'start_date' => $ts->start_date,
                        'end_date'   => $ts->end_date,
                        'payment_date' => $ts->payment_date ?? ''
                    ],

                    'date_wise_hours' => $dateWise,
                    'status'          => $ts->status,
                ];
            }

            return response()->json([
                'status'  => true,
                'message' => 'Timesheets retrieved successfully.',
                'data'    => $responseData
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'error'  => $e->getMessage()
            ], 500);
        }
    }

    //   public function createPayRun(Request $request)
    // {
    //     try {

    //         // -----------------------------------------
    //         // 1. Validate
    //         // -----------------------------------------
    //         $validated = $request->validate([
    //             'timesheet_id' => 'required|string',
    //             'pay_period_start'  => 'nullable|date',
    //             'pay_period_end'    => 'nullable|date',
    //             'payment_date'      => 'nullable|date'
    //         ]);

    //         $calendarId = $validated['calendar_id'];

    //         $start   = isset($validated['pay_period_start']) ? Carbon::parse($validated['pay_period_start']) : null;
    //         $end     = isset($validated['pay_period_end'])   ? Carbon::parse($validated['pay_period_end'])   : null;
    //         $payment = isset($validated['payment_date'])     ? Carbon::parse($validated['payment_date'])     : null;

    //         // -----------------------------------------
    //         // 2. Get Xero connection
    //         // -----------------------------------------
    //         $connection = XeroConnection::where('is_active', 1)->first();
    //         //  dd($connection);

    //         if (!$connection) {
    //             throw new \Exception("No active Xero connection found.");
    //         }

    //         // -----------------------------------------
    //         // 3. Build payload (Xero requires all dates)
    //         // -----------------------------------------
    //         if (!$start || !$end || !$payment) {
    //             throw new \Exception("Start date, end date, and payment date are required to create a payrun.");
    //         }

    //         $payload = [ 
    //             [
    //             "PayrollCalendarID"       => $calendarId,
    //             "PayRunPeriodStartDate"   => "/Date(" . ($start->timestamp * 1000) . "+0000)/",
    //             "PayRunPeriodEndDate"     => "/Date(" . ($end->timestamp * 1000) . "+0000)/",
    //             "PaymentDate"             => "/Date(" . ($payment->timestamp * 1000) . "+0000)/"
    //         ] 
    //         ];
    //         // dd($payload);

    //         // -----------------------------------------
    //         // 4. API CALL → Create PayRun
    //         // -----------------------------------------
    //         $response = Http::withHeaders([
    //             'Authorization'  => 'Bearer ' . $connection->access_token,
    //             'xero-tenant-id' => $connection->tenant_id,
    //             'Accept'         => 'application/json',
    //             'Content-Type'   => 'application/json'
    //         ])->post(
    //             "https://api.xero.com/payroll.xro/1.0/PayRuns",
    //             $payload
    //         );
    //         dd($response->body());

    //         $json = $response->json();

    //         if ($response->failed()) {
    //             return [
    //                 'status'  => false,
    //                 'message' => 'Failed to create PayRun',
    //                 'error'   => $json,
    //                 'payload' => $payload
    //             ];
    //         }

    //         // Extract PayRun record from API response
    //         $payrun = $json['PayRuns'][0];

    //         // -----------------------------------------
    //         // 5. Parse fields from Xero response
    //         // -----------------------------------------
    //         $payRunId     = $payrun['PayRunID'] ?? null;
    //         $calendarName = $payrun['PayrollCalendarName'] ?? null;

    //         $periodStart  = $this->convertXeroDate1($payrun['PayRunPeriodStartDate']);
    //         $periodEnd    = $this->convertXeroDate1($payrun['PayRunPeriodEndDate']);
    //         $paymentDate  = $this->convertXeroDate1($payrun['PaymentDate']);

    //         $status       = $payrun['Status'] ?? 'DRAFT';
    //         $runType      = $payrun['PayRunType'] ?? null;
    //         $totalWages   = $payrun['TotalGross'] ?? 0;
    //         $totalTax     = $payrun['TotalTax'] ?? 0;
    //         $totalSuper   = $payrun['TotalSuper'] ?? 0;
    //         $totalReimburse = $payrun['TotalReimbursements'] ?? 0;
    //         $totalDeductions = $payrun['TotalDeductions'] ?? 0;
    //         $netPay       = $payrun['TotalNetPay'] ?? 0;
    //         $empCount     = $payrun['EmployeeCount'] ?? 0;

    //         // -----------------------------------------
    //         // 6. SAVE IN DATABASE
    //         // -----------------------------------------
    //         $saved = XeroPayRun::updateOrCreate(
    //             [
    //                 'xero_pay_run_id' => $payRunId
    //             ],
    //             [
    //                 'organization_id'          => $connection->organization_id,
    //                 'xero_connection_id'       => $connection->id,
    //                 'xero_payroll_calendar_id' => $calendarId,
    //                 'calendar_name'            => $calendarName,
    //                 'period_start_date'        => $periodStart,
    //                 'period_end_date'          => $periodEnd,
    //                 'payment_date'             => $paymentDate,
    //                 'status'                   => $status,
    //                 'pay_run_type'             => $runType,
    //                 'total_wages'              => $totalWages,
    //                 'total_tax'                => $totalTax,
    //                 'total_super'              => $totalSuper,
    //                 'total_reimbursement'      => $totalReimburse,
    //                 'total_deductions'         => $totalDeductions,
    //                 'total_net_pay'            => $netPay,
    //                 'employee_count'           => $empCount,
    //                 'xero_data'                => $json,
    //                 'is_synced'                => true,
    //                 'last_synced_at'           => now()
    //             ]
    //         );

    //         // -----------------------------------------
    //         // 7. Return Response
    //         // -----------------------------------------
    //         return [
    //             'status'  => true,
    //             'message' => "PayRun created & stored successfully",
    //             'xero_payrun' => $json,
    //             'saved_record' => $saved
    //         ];

    //     } catch (\Exception $e) {
    //         return [
    //             'status' => false,
    //             'error'  => $e->getMessage()
    //         ];
    //     }
    // }

    public function createPayRun(Request $request)
    {
        try {

            // -----------------------------------------
            // 1. Validate Input
            // -----------------------------------------
            $validated = $request->validate([
                'timesheet_id'      => 'required',
                'pay_period_start'  => 'nullable|date',
                'pay_period_end'    => 'nullable|date',
                'payment_date'      => 'nullable|date',
            ]);

            $xero_timesheet_id = $validated['timesheet_id'];


            // -----------------------------------------
            // 2. Fetch Timesheet
            // -----------------------------------------
            $timesheet = XeroTimesheet::where('xero_timesheet_id',$xero_timesheet_id)->first();

            if (!$timesheet) {
                throw new \Exception("Timesheet not found.");
            }


            $employeeXeroConnection = EmployeeXeroConnection::where('xero_employee_id',$timesheet->xero_employee_id)->first();
            // Extract Xero employee & calendar
            $calendarId     = $employeeXeroConnection->xerocalenderId;

            if (!$calendarId) {
                throw new \Exception("Payroll Calendar ID not found for this timesheet.");
            }


            // -----------------------------------------
            // 3. Fetch Xero Connection
            // -----------------------------------------
            $connection = XeroConnection::where('organization_id', $timesheet->organization_id)
                ->where('is_active', 1)
                ->first();

            if (!$connection) {
                throw new \Exception("Active Xero connection not found.");
            }


            // -----------------------------------------
            // 4. Determine Start, End, Payment Dates
            // -----------------------------------------
            $start   = isset($validated['pay_period_start'])
                ? Carbon::parse($validated['pay_period_start'])
                : Carbon::parse($timesheet->start_date);

            $end     = isset($validated['pay_period_end'])
                ? Carbon::parse($validated['pay_period_end'])
                : Carbon::parse($timesheet->end_date);

            $payment = isset($validated['payment_date'])
                ? Carbon::parse($validated['payment_date'])
                : Carbon::now(); // default today


            // -----------------------------------------
            // 5. Validate Required Dates
            // -----------------------------------------
            if (!$start || !$end || !$payment) {
                throw new \Exception("Start date, End date, and Payment date are required.");
            }


            // -----------------------------------------
            // 6. Build PayRun Payload (Xero requires ARRAY)
            // -----------------------------------------
            $payload = [
                [
                    "PayrollCalendarID"       => $calendarId,
                    "PayRunPeriodStartDate"   => "/Date(" . ($start->timestamp * 1000) . "+0000)/",
                    "PayRunPeriodEndDate"     => "/Date(" . ($end->timestamp * 1000) . "+0000)/",
                    "PaymentDate"             => "/Date(" . ($payment->timestamp * 1000) . "+0000)/"
                ]
            ];


            // -----------------------------------------
            // 7. API CALL → Create PayRun
            // -----------------------------------------
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $connection->access_token,
                'xero-tenant-id' => $connection->tenant_id,
                'Accept'         => 'application/json',
                'Content-Type'   => 'application/json'
            ])->post(
                "https://api.xero.com/payroll.xro/1.0/PayRuns",
                $payload
            );

            if ($response->failed()) {
                return [
                    'status' => false,
                    'message' => "Xero PayRun API failed",
                    'payload' => $payload,
                    'error' => $response->json()
                ];
            }

            $json = $response->json();
            $payrun = $json["PayRuns"][0];


            // -----------------------------------------
            // 8. Convert Dates from Xero format
            // -----------------------------------------
            $periodStart = $this->convertXeroDate1($payrun["PayRunPeriodStartDate"]);
            $periodEnd   = $this->convertXeroDate1($payrun["PayRunPeriodEndDate"]);
            $paymentDate = $this->convertXeroDate1($payrun["PaymentDate"]);


            // -----------------------------------------
            // 9. Save PayRun in DB
            // -----------------------------------------
            $saved = XeroPayRun::updateOrCreate(
                [
                    'xero_pay_run_id' => $payrun['PayRunID']
                ],
                [
                    'organization_id'          => $timesheet->organization_id,
                    'xero_connection_id'       => $connection->id,
                    'xero_payroll_calendar_id' => $calendarId,
                    'calendar_name'            => $payrun['PayrollCalendarName'] ?? null,
                    'period_start_date'        => $periodStart,
                    'period_end_date'          => $periodEnd,
                    'payment_date'             => $paymentDate,
                    'status'                   => $payrun['Status'] ?? 'DRAFT',
                    'pay_run_type'             => $payrun['PayRunType'] ?? null,
                    'total_wages'              => $payrun['TotalGross'] ?? 0,
                    'total_tax'                => $payrun['TotalTax'] ?? 0,
                    'total_super'              => $payrun['TotalSuper'] ?? 0,
                    'total_reimbursement'      => $payrun['TotalReimbursements'] ?? 0,
                    'total_deductions'         => $payrun['TotalDeductions'] ?? 0,
                    'total_net_pay'            => $payrun['TotalNetPay'] ?? 0,
                    'employee_count'           => $payrun['EmployeeCount'] ?? 0,
                    'xero_data'                => $json,
                    'is_synced'                => true,
                    'last_synced_at'           => now()
                ]
            );


            return [
                'status'        => true,
                'message'       => "PayRun created successfully",
                'payrun'        => $json,
                'saved_record'  => $saved
            ];
        } catch (\Exception $e) {

            return [
                'status' => false,
                'error'  => $e->getMessage()
            ];
        }
    }



    private function convertXeroDate1($xeroDate)
    {
        if (!$xeroDate) return null;

        preg_match('/\d+/', $xeroDate, $matches);

        return Carbon::createFromTimestamp($matches[0] / 1000)->toDateString();
    }



    //---------------------------------------------------------------------------



   public function generate(Request $request)
{
    $request->validate([
        'from' => 'required|date',
        'to' => 'required|date',
    ]);

    $orgId = $request->organization_id;
    $employees = Employee::where('organization_id', $orgId)->get();
    $created = 0;

    foreach ($employees as $employee) {

        // Prevent duplicate generation
        $exists = Timesheet::where('employee_id', $employee->id)
            ->where('from_date', $request->from)
            ->where('to_date', $request->to)
            ->exists(); 

        if ($exists) continue;

        // 1. Fetch Daily Attendance Records
        $attendancesRaw = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$request->from, $request->to])
            ->get();

        // ⚡ KEY FIX: Convert collection to a Key-Value pair where Key = 'Y-m-d'
        // This ensures we can find the record regardless of whether DB is datetime or model casts
        $attendanceLookup = $attendancesRaw->mapWithKeys(function ($item) {
            // Force convert DB date to strict 'Y-m-d' string
            return [\Carbon\Carbon::parse($item->date)->toDateString() => $item];
        });

        // 2. Build the JSON Structure & Calculate Total
        $dailyData = [];
        $totalHours = 0;

        $period = CarbonPeriod::create($request->from, $request->to);

        foreach ($period as $date) {
            $dateStr = $date->toDateString(); // "2026-02-01"
            
            // Check strictly by the string key we created above
            if (isset($attendanceLookup[$dateStr])) {
                $dayHours = (float) $attendanceLookup[$dateStr]->total_work_hours;
            } else {
                $dayHours = 0;
            }

            $dailyData[$dateStr] = $dayHours;
            $totalHours += $dayHours;
        }

        Timesheet::create([
            'employee_id' => $employee->id,
            'organization_id' => $orgId,
            'from_date' => $request->from,
            'to_date' => $request->to,
            'regular_hours' => $totalHours,
            'daily_breakdown' => $dailyData,
            'overtime_hours' => 0,
            'status' => 'pending',
        ]);

        $created++;
    }

    return response()->json([
        'status' => true,
        'created' => $created
    ]);
}
    /**
     * List timesheets by organization
     */
    public function index(Request $request, $organizationId)
    {
        try {
            if (!$organizationId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization ID is required.'
                ], 400);
            }

            $timesheets = Timesheet::where('organization_id', $organizationId)
                ->with([
                    'project:id,name',
                    'task:id,title',
                    'employee:id,first_name,last_name,employee_code',
                    'attendance:id,date,check_in,check_out'
                ])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Timesheets retrieved successfully',
                'data' => $timesheets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update single timesheet (manual edit)
     */
 public function update(Request $request, $id)
{
    $timesheet = Timesheet::findOrFail($id);

    $data = [
        'overtime_hours' => $request->overtime_hours,
        'remarks' => $request->remarks,
    ];

    // If frontend sends updated daily breakdown (Recommended)
    if ($request->has('daily_breakdown')) {
        $data['daily_breakdown'] = $request->daily_breakdown;
        // Recalculate total regular hours from the breakdown to keep them in sync
        $data['regular_hours'] = array_sum($request->daily_breakdown); 
    } 
    // Fallback: If they only update total hours, we can't easily update daily breakdown
    // So we assume this only happens if they don't have daily editing UI.
    elseif ($request->has('regular_hours')) {
        $data['regular_hours'] = $request->regular_hours;
    }

    $timesheet->update($data);

    return response()->json(['status' => true]);
}

    /**
     * Submit timesheets for approval
     */
   public function submit(Request $request)
{
    $request->validate([
        'timesheet_ids' => 'required|array',
        'timesheet_ids.*' => 'exists:timesheets,id',
    ]);

    Timesheet::whereIn('id', $request->timesheet_ids)
        ->update([
            'status'       => 'submitted',
            'approved_at'  => Carbon::now(),     // current time
            'approved_by'  => Auth::id(),         // logged-in user id
        ]);

    return response()->json([
        'status'  => true,
        'message' => 'Timesheets submitted successfully'
    ]);
}





}
