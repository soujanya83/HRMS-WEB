<?php

namespace App\Http\Controllers\API\V1\Xero;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee\Employee;
use App\Models\XeroConnection;
use App\Models\Employee\Timesheet;
use App\Models\EmployeeXeroConnection;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\Xero\XeroTokenService;
use App\Services\XeroEmployeeService;
use App\Services\XeroTimesheetService;
use App\Models\XeroTimesheet;
use Carbon\CarbonPeriod;


class XeroEmployeeController extends Controller
{
    public function sync(Request $request)
    {
        
        try {
            // dd('here');
            $employeeId = $request->employee_id;
            $organizationId = $request->organization_id;
            $employee = Employee::findOrFail($employeeId);
            $connection = XeroConnection::where('organization_id', $organizationId)->where('is_active', 1)->first();
            // dd('here');
            if (!$connection) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Xero connection found.'
                ], 404);
            }

            $service = new \App\Services\XeroEmployeeService();
            $result = $service->syncEmployee($employee, $connection);

            return response()->json($result);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Sync error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getAllFromXero(Request $request)
        {
            try {

                $organizationId = $request->user()->organization_id;

                $connection = XeroConnection::where('organization_id', 15)
                    ->where('is_active', 1)
                    ->firstOrFail();

                $service = new \App\Services\XeroEmployeeService();

                $employees = $service->fetchEmployeesFromXero($connection);

                return response()->json([
                    'status' => true,
                    'data' => $employees
                ]);

            } catch (\Exception $e) {

                return response()->json([
                    'status' => false,
                    'message' => 'Failed to fetch employees',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

//--------------------------------------------------------------------------------------------------------
//timesheet api controllers - 

public function push(Request $request)
    {
        try {

            $organizationId = $request->user()->organization_id;

            $connection = XeroConnection::where('organization_id', 15)
                ->where('is_active', 1)
                ->firstOrFail();

            $service = new \App\Services\XeroEmployeeService();

            $result = $service->pushTimesheets($connection, $request->date_from, $request->date_to);

            return response()->json([
                'status' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Timesheet sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //------------------------------------------------------------------------
    //timesheet api controllers

public function pushApproved(Request $request)
{
    Log::info('Xero Timesheet Push Started', [
        'request_data' => $request->all()
    ]);

    $orgId = $request->organization_id;

    if (!$orgId) {
        Log::error('Organization ID missing in request');
        return response()->json([
            'status' => false,
            'message' => 'organization_id is required'
        ], 422);
    }

    // ----------------------------------------------------
    // XERO CONNECTION
    // ----------------------------------------------------
    try {
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->firstOrFail();

        Log::info('Active Xero connection found', [
            'connection_id' => $connection->id,
            'tenant_id' => $connection->tenant_id
        ]);
    } catch (\Exception $e) {
        Log::error('Active Xero connection NOT found', [
            'organization_id' => $orgId,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Active Xero connection not found'
        ], 404);
    }

    // ----------------------------------------------------
    // TOKEN REFRESH
    // ----------------------------------------------------
    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    Log::info('Xero token checked/refreshed');

    // ----------------------------------------------------
    // FETCH TIMESHEETS
    // ----------------------------------------------------
    $timesheets = Timesheet::where('organization_id', $orgId)
        ->where('status', 'submitted')
        ->whereNull('xero_synced_at')
        ->get();

    Log::info('Timesheets fetched', [
        'total_timesheets' => $timesheets->count()
    ]);

    $timesheets = $timesheets->groupBy('employee_id');

    Log::info('Timesheets grouped by employee', [
        'employee_count' => $timesheets->count()
    ]);

    // ----------------------------------------------------
    // COUNTERS
    // ----------------------------------------------------
    $created = 0;
    $skippedNoXeroEmployee = 0;
    $xeroFailed = 0;

    // ----------------------------------------------------
    // LOOP PER EMPLOYEE
    // ----------------------------------------------------
    foreach ($timesheets as $employeeId => $rows) {

        Log::info('Processing employee timesheets', [
            'employee_id' => $employeeId,
            'timesheet_count' => $rows->count()
        ]);

        // ------------------------------------------------
        // EMPLOYEE XERO CONNECTION
        // ------------------------------------------------
        $empXero = EmployeeXeroConnection::where('employee_id', $employeeId)->first();

        if (!$empXero || !$empXero->xero_employee_id || !$empXero->OrdinaryEarningsRateID) {
            Log::warning('Employee Xero data missing/incomplete', [
                'employee_id' => $employeeId
            ]);
            $skippedNoXeroEmployee++;
            continue;
        }

        // ------------------------------------------------
        // DATE RANGE (PAY PERIOD)
        // ------------------------------------------------
        $startDate = Carbon::parse($rows->first()->from_date)->toDateString();
        $endDate   = Carbon::parse($rows->first()->to_date)->toDateString();

        // ------------------------------------------------
        // BUILD DAILY HOURS ARRAY (MANDATORY FOR XERO)
        // ------------------------------------------------
        $period = CarbonPeriod::create($startDate, $endDate);
        $dailyHours = [];

        foreach ($period as $date) {
            // Example logic: weekdays = hours, weekends = 0
            $hours = $rows->sum('regular_hours') / $period->count();

            // Round to quarter hours (safe for Xero)
            $hours = round($hours * 4) / 4;

            $dailyHours[] = $date->isWeekday() ? $hours : 0;
        }

        Log::info('Daily hours calculated', [
            'employee_id' => $employeeId,
            'days' => count($dailyHours),
            'hours_array' => $dailyHours
        ]);

        // ------------------------------------------------
        // PAYLOAD
        // ------------------------------------------------
        // $payload = [
        //     "Timesheets" => [[
        //         "EmployeeID" => $empXero->xero_employee_id,
        //         "StartDate" => $startDate,
        //         "EndDate" => $endDate,
        //         "TimesheetLines" => [[
        //             "EarningsRateID" => $empXero->OrdinaryEarningsRateID,
        //             "NumberOfUnits" => $dailyHours
        //         ]]
        //     ]]
        // ];

        $payload = [
    [
        'EmployeeID' => $empXero->xero_employee_id,
        'StartDate' => $startDate,
        'EndDate' => $endDate,
        'TimesheetLines' => [
            [
                'EarningsRateID' => $empXero->OrdinaryEarningsRateID,
                'NumberOfUnits' => $dailyHours,
            ]
        ]
    ]
];
        

        Log::info('Xero timesheet payload prepared', [
            'employee_id' => $employeeId,
            'payload' => $payload
        ]);

        // ------------------------------------------------
        // XERO API CALL
        // ------------------------------------------------
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $connection->access_token,
            'Xero-Tenant-Id' => $connection->tenant_id,
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/payroll.xro/1.0/Timesheets', $payload);

        Log::info('Xero API response received', [
            'employee_id' => $employeeId,
            'status' => $response->status(),
            'response' => $response->json()
        ]);

        if (!$response->successful()) {
            Log::error('Xero API timesheet creation failed', [
                'employee_id' => $employeeId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            $xeroFailed++;
            continue;
        }

        // ------------------------------------------------
        // SUCCESS
        // ------------------------------------------------
        $xero = $response->json()['Timesheets'][0] ?? null;

        if (!$xero || !isset($xero['TimesheetID'])) {
            Log::error('Xero response missing TimesheetID', [
                'employee_id' => $employeeId
            ]);
            $xeroFailed++;
            continue;
        }

        XeroTimesheet::create([
            'organization_id' => $orgId,
            'employee_xero_connection_id' => $empXero->id,
            'xero_connection_id' => $connection->id,
            'xero_timesheet_id' => $xero['TimesheetID'],
            'xero_employee_id' => $empXero->xero_employee_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_hours' => array_sum($dailyHours),
            'ordinary_hours' => array_sum($dailyHours),
            'status' => 'CREATED',
            'xero_data' => $xero,
            'is_synced' => true,
            'last_synced_at' => now(),
        ]);

        Timesheet::whereIn('id', $rows->pluck('id'))->update([
            'xero_synced_at' => now(),
            'xero_status' => 'pushed'
        ]);

        Log::info('Timesheet successfully pushed to Xero', [
            'employee_id' => $employeeId,
            'timesheet_id' => $xero['TimesheetID']
        ]);

        $created++;
    }

    // ----------------------------------------------------
    // FINAL RESPONSE
    // ----------------------------------------------------
    Log::info('Xero Timesheet Push Completed', [
        'organization_id' => $orgId,
        'employees_pushed' => $created,
        'skipped_no_xero_employee' => $skippedNoXeroEmployee,
        'xero_failed' => $xeroFailed
    ]);

    return response()->json([
        'status' => true,
        'employees_pushed' => $created,
        'skipped_no_xero_employee' => $skippedNoXeroEmployee,
        'xero_failed' => $xeroFailed
    ]);
}





public function getAvailablePayPeriods(Request $request)
    {
        $orgId = $request->organization_id;

        // Xero connection setup...
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->first();

        if (!$connection) {
            return response()->json(['status' => false, 'message' => 'Xero not connected'], 404);
        }

        $connection = app(\App\Services\Xero\XeroTokenService::class)->refreshIfNeeded($connection);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/payroll.xro/1.0/PayrollCalendars');

            if (!$response->successful()) {
                return response()->json(['status' => false, 'message' => 'Failed to fetch calendars'], 500);
            }

            $calendars = $response->json()['PayrollCalendars'] ?? [];
            $allPayPeriods = [];
            $today = Carbon::now();

            foreach ($calendars as $calendar) {
                $xeroStartDate = $this->parseXeroDate($calendar['StartDate'] ?? null);
                if (!$xeroStartDate) continue;

                $type = strtoupper($calendar['CalendarType'] ?? '');
                $name = $calendar['Name'] ?? 'Unknown';

                // ✨ MAGIC FIX: Project this date forward until it catches up to Today
                $currentPeriod = $this->findCurrentPeriod($xeroStartDate, $type, $today);

                if ($currentPeriod) {
                    $allPayPeriods[] = [
                        'calendar_name' => $name,
                        'calendar_type' => $type,
                        // Return the Calculated Current Period
                        'start_date' => $currentPeriod['start']->toDateString(),
                        'end_date'   => $currentPeriod['end']->toDateString(),
                        'start_date_formatted' => $currentPeriod['start']->format('d M Y'),
                        'end_date_formatted'   => $currentPeriod['end']->format('d M Y'),
                        'number_of_days' => $currentPeriod['days'],
                        'is_current' => true
                    ];

                    // Optional: If you also want the NEXT period (Future)
                    $nextPeriod = $this->calculateNextPeriod($currentPeriod['start'], $type);
                    $allPayPeriods[] = [
                        'calendar_name' => $name . ' (Next)',
                        'calendar_type' => $type,
                        'start_date' => $nextPeriod['start']->toDateString(),
                        'end_date'   => $nextPeriod['end']->toDateString(),
                        'start_date_formatted' => $nextPeriod['start']->format('d M Y'),
                        'end_date_formatted'   => $nextPeriod['end']->format('d M Y'),
                        'number_of_days' => $nextPeriod['days'],
                        'is_current' => false
                    ];
                }
            }

            return response()->json([
                'status' => true,
                'pay_periods' => $allPayPeriods,
                'message' => 'Current and future periods calculated'
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Loops forward from the anchor date until it finds the period containing Today
     */
    private function findCurrentPeriod(Carbon $anchorDate, string $type, Carbon $targetDate)
    {
        // 1. Calculate the first period duration
        $period = $this->calculateEndDate($anchorDate, $type);
        if (!$period) return null;

        $start = $period['start'];
        $end   = $period['end'];

        // 2. Loop: While the End Date is in the past (before today), jump to the next period
        // We use a safety counter to prevent infinite loops if something goes wrong
        $safety = 0;
        while ($end->lt($targetDate) && $safety < 1000) {
            
            // Advance the start date based on type
            if ($type === 'MONTHLY') {
                $start->addMonth();
            } elseif ($type === 'QUARTERLY') {
                $start->addMonths(3);
            } elseif ($type === 'WEEKLY') {
                $start->addWeeks(1);
            } elseif ($type === 'FORTNIGHTLY') {
                $start->addWeeks(2);
            } else {
                // Fallback for others
                $start->addDays($period['days']);
            }

            // Recalculate the end date for this new start date
            $period = $this->calculateEndDate($start, $type);
            $end = $period['end'];
            $safety++;
        }

        return $period;
    }

    /**
     * Simply calculates End Date from a Start Date (No Looping)
     */
    private function calculateEndDate(Carbon $startDate, string $type)
    {
        $start = $startDate->copy();
        $end   = $startDate->copy();
        $days  = 0;

        switch ($type) {
            case 'WEEKLY':
                $end->addDays(6);
                $days = 7;
                break;
            case 'FORTNIGHTLY':
                $end->addDays(13);
                $days = 14;
                break;
            case 'MONTHLY':
                // Monthly end is Start + 1 Month - 1 Day
                $end->addMonth()->subDay();
                $days = $start->diffInDays($end) + 1;
                break;
            case 'FOURWEEKLY':
                $end->addDays(27);
                $days = 28;
                break;
            case 'QUARTERLY':
                $end->addMonths(3)->subDay();
                $days = $start->diffInDays($end) + 1;
                break;
            default:
                return null;
        }

        return ['start' => $start, 'end' => $end, 'days' => $days];
    }

    /**
     * Helper to get the very next period after a given period
     */
    private function calculateNextPeriod(Carbon $currentStartDate, string $type)
    {
        $nextStart = $currentStartDate->copy();
        
        if ($type === 'MONTHLY') {
            $nextStart->addMonth();
        } elseif ($type === 'QUARTERLY') {
            $nextStart->addMonths(3);
        } elseif ($type === 'WEEKLY') {
            $nextStart->addWeeks(1);
        } elseif ($type === 'FORTNIGHTLY') {
            $nextStart->addWeeks(2);
        } elseif ($type === 'FOURWEEKLY') {
             $nextStart->addWeeks(4);
        }

        return $this->calculateEndDate($nextStart, $type);
    }

    private function parseXeroDate($xeroDate)
    {
        if (!$xeroDate) return null;
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            return Carbon::createFromTimestampMs($matches[1]);
        }
        try {
            return Carbon::parse($xeroDate);
        } catch (\Exception $e) {
            return null;
        }
    }




   }
