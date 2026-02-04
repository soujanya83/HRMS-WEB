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

    // Xero connection
    $connection = XeroConnection::where('organization_id', $orgId)
        ->where('is_active', 1)
        ->first();

    if (!$connection) {
        return response()->json([
            'status' => false,
            'message' => 'Xero not connected'
        ], 404);
    }

    // Token refresh
    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    try {
        // 1️⃣ Fetch payroll calendars
        $calendarResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $connection->access_token,
            'Xero-Tenant-Id' => $connection->tenant_id,
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/payroll.xro/1.0/PayrollCalendars');

        if (!$calendarResponse->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch pay calendars from Xero'
            ], 500);
        }

        $calendars = $calendarResponse->json()['PayrollCalendars'] ?? [];
        $allPayPeriods = [];

        // 2️⃣ Loop calendars
        foreach ($calendars as $calendar) {

            $calendarId   = $calendar['PayrollCalendarID'];
            $calendarName = $calendar['Name'] ?? 'Unknown Calendar';

            // 3️⃣ Fetch periods for this calendar
            $periodResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json',
            ])->get(
                "https://api.xero.com/payroll.xro/1.0/PayrollCalendarPeriods/{$calendarId}"
            );

            if (!$periodResponse->successful()) {
                continue;
            }

            $periods = $periodResponse->json()['PayrollCalendarPeriods'] ?? [];

            // 4️⃣ Loop periods
            foreach ($periods as $period) {

                $startDate = $this->parseXeroDate($period['StartDate'] ?? null);
                $endDate   = $this->parseXeroDate($period['EndDate'] ?? null);

                if (!$startDate || !$endDate) {
                    continue;
                }

                // Only current / future periods
                if ($endDate->isFuture() || $endDate->isToday()) {
                    $allPayPeriods[] = [
                        'calendar_name' => $calendarName,
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                        'start_date_formatted' => $startDate->format('d M Y'),
                        'end_date_formatted' => $endDate->format('d M Y'),
                        'period_status' => $period['PeriodStatus'] ?? 'Unknown',
                        'number_of_days' => $startDate->diffInDays($endDate) + 1,
                    ];
                }
            }
        }

        // 5️⃣ Sort latest first
        usort($allPayPeriods, function ($a, $b) {
            return strtotime($b['start_date']) - strtotime($a['start_date']);
        });

        return response()->json([
            'status' => true,
            'pay_periods' => $allPayPeriods,
            'message' => 'Select a pay period to generate timesheets'
        ]);

    } catch (\Exception $e) {

        Log::error('Error fetching pay periods', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}



private function parseXeroDate($xeroDate)
{
    if (!$xeroDate) {
        return null;
    }

    if (preg_match('/\/Date\((\d+)/', $xeroDate, $matches)) {
        return \Carbon\Carbon::createFromTimestampMs($matches[1]);
    }

    return null;
}





   }
