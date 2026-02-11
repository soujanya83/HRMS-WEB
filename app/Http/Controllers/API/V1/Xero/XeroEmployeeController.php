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
use App\Models\XeroPayRun;
use App\Models\XeroPayslip;
use Carbon\CarbonPeriod;
use App\Models\PayPeriod; // Import the new model
use App\Models\XeroLeaveType;
use App\Models\XeroLeaveApplication;


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
    // 1. XERO CONNECTION
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
    // 2. TOKEN REFRESH
    // ----------------------------------------------------
    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    Log::info('Xero token checked/refreshed');

    // ----------------------------------------------------
    // 3. FETCH TIMESHEETS
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
    // 4. COUNTERS
    // ----------------------------------------------------
    $created = 0;
    $skippedNoXeroEmployee = 0;
    $xeroFailed = 0;

    // ----------------------------------------------------
    // 5. LOOP PER EMPLOYEE
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
        // GET TIMESHEET DATA
        // ------------------------------------------------
        // We take the first row (since grouped by employee) to get the dates & JSON
        $mainRecord = $rows->first(); 

        $startDate = Carbon::parse($mainRecord->from_date)->toDateString();
        $endDate   = Carbon::parse($mainRecord->to_date)->toDateString();
        
        // ------------------------------------------------
        // ⚡ BUILD DAILY HOURS ARRAY (FROM JSON)
        // ------------------------------------------------
        // 1. Get the JSON data (ensure your model casts this to array, or use json_decode)
        $savedDailyData = $mainRecord->daily_breakdown; 
        
        // Safety check: if it's a string (old Laravel versions), decode it
        if (is_string($savedDailyData)) {
            $savedDailyData = json_decode($savedDailyData, true);
        }
        if (!is_array($savedDailyData)) {
            $savedDailyData = [];
        }

        // 2. Create the period loop to ensure order is perfect (Day 1 to Day 14)
        $period = CarbonPeriod::create($startDate, $endDate);
        $dailyHours = [];

        foreach ($period as $date) {
            $dateStr = $date->toDateString();

            // Look up the exact value from the JSON
            if (isset($savedDailyData[$dateStr])) {
                $hours = (float) $savedDailyData[$dateStr];
            } else {
                $hours = 0;
            }

            // Xero needs a simple list of numbers: [8.0, 8.88, 0, ...]
            $dailyHours[] = $hours;
        }

        Log::info('Daily hours prepared for Xero', [
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'daily_hours' => $dailyHours
        ]);

        // ------------------------------------------------
        // PAYLOAD
        // ------------------------------------------------
        $payload = [
            [
                'EmployeeID' => $empXero->xero_employee_id,
                'StartDate' => $startDate,
                'EndDate' => $endDate,
                // ❌ OLD CODE: 'Status' => 'DRAFT', 
                // ✅ NEW CODE: Change to APPROVED
                // 'Status' => 'APPROVED', // 👈 Set to APPROVED to push as approved timesheet
                'Status' => 'DRAFT', // Usually safer to push as DRAFT first
                'TimesheetLines' => [
                    [
                        'EarningsRateID' => $empXero->OrdinaryEarningsRateID,
                        'NumberOfUnits' => $dailyHours, // 👈 The array we just built
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
        // SUCCESS HANDLING
        // ------------------------------------------------
        $xeroResponse = $response->json();
        
        // Xero returns { "Timesheets": [ ... ] }
        $xero = $xeroResponse['Timesheets'][0] ?? null;

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

        // Mark local timesheets as pushed
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


public function pushApprovedForEmployee(Request $request)
{
    Log::info('Xero Timesheet Push for Specific Employee Started', [
        'request_data' => $request->all()
    ]);

    $orgId = $request->organization_id;
    $employeeId = $request->employee_id;

    // ----------------------------------------------------
    // 1. VALIDATIONS
    // ----------------------------------------------------
    $request->validate([
        'organization_id' => 'required|exists:organizations,id',
        'employee_id' => 'required|exists:employees,id'
    ]);

    if (!$orgId || !$employeeId) {
        Log::error('Required parameters missing', [
            'organization_id' => $orgId,
            'employee_id' => $employeeId
        ]);
        return response()->json([
            'status' => false,
            'message' => 'organization_id and employee_id are required'
        ], 422);
    }

    // ----------------------------------------------------
    // 2. XERO CONNECTION
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
    // 3. TOKEN REFRESH
    // ----------------------------------------------------
    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    Log::info('Xero token checked/refreshed');

    // ----------------------------------------------------
    // 4. FETCH SPECIFIC EMPLOYEE TIMESHEETS
    // ----------------------------------------------------
    $timesheets = Timesheet::where('organization_id', $orgId)
        ->where('employee_id', $employeeId)
        ->where('status', 'submitted')
        ->whereNull('xero_synced_at')
        ->get();

    Log::info('Employee timesheets fetched', [
        'employee_id' => $employeeId,
        'total_timesheets' => $timesheets->count()
    ]);

    if ($timesheets->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'No pending timesheets found for this employee'
        ], 404);
    }

    // ----------------------------------------------------
    // 5. EMPLOYEE XERO CONNECTION CHECK
    // ----------------------------------------------------
    $empXero = EmployeeXeroConnection::where('employee_id', $employeeId)->first();

    if (!$empXero || !$empXero->xero_employee_id || !$empXero->OrdinaryEarningsRateID) {
        Log::warning('Employee Xero data missing/incomplete', [
            'employee_id' => $employeeId
        ]);
        return response()->json([
            'status' => false,
            'message' => 'Employee Xero connection incomplete or missing'
        ], 422);
    }

    // ----------------------------------------------------
    // 6. PREPARE TIMESHEET DATA (SAME LOGIC)
    // ----------------------------------------------------
    $mainRecord = $timesheets->first();
    $startDate = Carbon::parse($mainRecord->from_date)->toDateString();
    $endDate = Carbon::parse($mainRecord->to_date)->toDateString();

    // Build daily hours array
    $savedDailyData = $mainRecord->daily_breakdown;
    if (is_string($savedDailyData)) {
        $savedDailyData = json_decode($savedDailyData, true);
    }
    if (!is_array($savedDailyData)) {
        $savedDailyData = [];
    }

    $period = CarbonPeriod::create($startDate, $endDate);
    $dailyHours = [];

    foreach ($period as $date) {
        $dateStr = $date->toDateString();
        $hours = isset($savedDailyData[$dateStr]) ? (float) $savedDailyData[$dateStr] : 0;
        $dailyHours[] = $hours;
    }

    Log::info('Daily hours prepared for Xero', [
        'employee_id' => $employeeId,
        'start_date' => $startDate,
        'daily_hours' => $dailyHours
    ]);

    // ----------------------------------------------------
    // 7. PAYLOAD
    // ----------------------------------------------------
    $payload = [
        [
            'EmployeeID' => $empXero->xero_employee_id,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'Status' => 'DRAFT',
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

    // ----------------------------------------------------
    // 8. XERO API CALL
    // ----------------------------------------------------
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
        return response()->json([
            'status' => false,
            'message' => 'Failed to push timesheet to Xero',
            'error' => $response->json()
        ], 500);
    }

    // ----------------------------------------------------
    // 9. SUCCESS HANDLING
    // ----------------------------------------------------
    $xeroResponse = $response->json();
    $xero = $xeroResponse['Timesheets'][0] ?? null;

    if (!$xero || !isset($xero['TimesheetID'])) {
        Log::error('Xero response missing TimesheetID', [
            'employee_id' => $employeeId
        ]);
        return response()->json([
            'status' => false,
            'message' => 'Invalid Xero response'
        ], 500);
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

    // Mark local timesheets as pushed
    Timesheet::whereIn('id', $timesheets->pluck('id'))->update([
        'xero_synced_at' => now(),
        'xero_status' => 'pushed'
    ]);

    Log::info('Timesheet successfully pushed to Xero for employee', [
        'employee_id' => $employeeId,
        'timesheet_id' => $xero['TimesheetID']
    ]);

    // ----------------------------------------------------
    // 10. FINAL RESPONSE
    // ----------------------------------------------------
    Log::info('Xero Timesheet Push for Employee Completed', [
        'organization_id' => $orgId,
        'employee_id' => $employeeId,
        'xero_timesheet_id' => $xero['TimesheetID']
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Timesheet pushed successfully',
        'employee_id' => $employeeId,
        'xero_timesheet_id' => $xero['TimesheetID'],
        'total_hours' => array_sum($dailyHours),
        'timesheets_synced' => $timesheets->count()
    ]);
}





 public function get_all_pay_periods(Request $request)
    {
        $request->validate([
            'organization_id' => 'required'
        ]);

        $orgId = $request->organization_id;

        // 1. 🔄 Sync Logic: Fetch from Xero, Calculate, Update DB
        $syncResult = $this->syncXeroPayPeriods($orgId);

        if (!$syncResult['success']) {
            return response()->json([
                'status' => false, 
                'message' => $syncResult['message']
            ], $syncResult['code']);
        }

        // 2. 📦 Fetch Logic: Get the fresh data from Database
        $payPeriods = PayPeriod::where('organization_id', $orgId)
            ->orderBy('calendar_name')     // Group by Calendar Name
            ->orderBy('start_date', 'desc') // Latest dates first
            ->get();

        return response()->json([
            'status' => true,
            'data' => $payPeriods,
            'message' => 'Pay periods synced and retrieved successfully'
        ]);
    }

    /**
     * 🛠️ PRIVATE: Handles the heavy lifting (Xero API -> Calculations -> DB Sync)
     */
    private function syncXeroPayPeriods($orgId)
    {
        // A. Connection Setup
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->first();

        if (!$connection) {
            return ['success' => false, 'message' => 'Xero not connected', 'code' => 404];
        }

        $connection = app(\App\Services\Xero\XeroTokenService::class)->refreshIfNeeded($connection);

        try {
            // B. Fetch Calendars from Xero
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/payroll.xro/1.0/PayrollCalendars');

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Failed to fetch calendars from Xero', 'code' => 500];
            }

            $calendars = $response->json()['PayrollCalendars'] ?? [];
            $allPayPeriods = [];
            $today = Carbon::now();

            // C. Loop & Calculate Dates
            foreach ($calendars as $calendar) {
                $xeroStartDate = $this->parseXeroDate($calendar['StartDate'] ?? null);
                if (!$xeroStartDate) continue;

                $type = strtoupper($calendar['CalendarType'] ?? '');
                $name = $calendar['Name'] ?? 'Unknown';
                $calendarId = $calendar['PayrollCalendarID']; // 👈 1. ID Fetch की

                // 1. Find Current Period
                $currentPeriod = $this->findCurrentPeriod($xeroStartDate, $type, $today);

                if ($currentPeriod) {
                    // Current
                    $allPayPeriods[] = $this->formatPeriod($currentPeriod,  $name, $type, $calendarId, true, 'Current');

                    // Future (Next 1)
                    $nextPeriod = $this->calculateNextPeriod($currentPeriod['start'], $type);
                    $allPayPeriods[] = $this->formatPeriod($nextPeriod, $name,  $type, $calendarId, false, 'Future');

                    // Past (Last 3)
                    $tempStart = $currentPeriod['start'];
                    for ($i = 1; $i <= 3; $i++) {
                        $pastPeriod = $this->calculatePreviousPeriod($tempStart, $type);
                        $allPayPeriods[] = $this->formatPeriod($pastPeriod, $name, $type, $calendarId, false, 'Past');
                        $tempStart = $pastPeriod['start'];
                    }
                }
            }

            // D. Sync to Database
            $this->storePeriodsInDatabase($orgId, $allPayPeriods);

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('Payroll Sync Error', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Internal Error: ' . $e->getMessage(), 'code' => 500];
        }
    }

    // ------------------------------------------------------------------
    // 💾 DATABASE HELPER
    // ------------------------------------------------------------------
    private function storePeriodsInDatabase($orgId, array $periods)
    {
        // 1. Identify valid Start Dates & Calendars we just found
        $activeStartDates = array_column($periods, 'start_date');
        $activeCalendarNames = array_unique(array_column($periods, 'calendar_name'));

        // 2. Delete "Stale" Data (Older than 3 past periods)
        // Only delete for the calendars we are currently processing
        PayPeriod::where('organization_id', $orgId)
            ->whereIn('calendar_name', $activeCalendarNames)
            ->whereNotIn('start_date', $activeStartDates)
            ->delete();

        // 3. Update/Create Valid Periods
        foreach ($periods as $period) {
            PayPeriod::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'calendar_name'   => $period['calendar_name'],
                    'start_date'      => $period['start_date'],
                ],
                [
                    'calendar_id'     => $period['calendar_id'], // 👈 THIS WAS MISSING
                    'calendar_type'   => $period['calendar_type'],
                    'end_date'        => $period['end_date'],
                    'number_of_days'  => $period['number_of_days'],
                    'is_current'      => $period['is_current'],
                ]
            );
        }
    }




public function getAvailablePayPeriods(Request $request)
    {
        $orgId = $request->organization_id;

        // 1. Connection Logic
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->first();

        if (!$connection) {
            return response()->json(['status' => false, 'message' => 'Xero not connected'], 404);
        }

        $connection = app(\App\Services\Xero\XeroTokenService::class)->refreshIfNeeded($connection);

        try {
            // 2. Fetch Calendars
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
                $calendarId = $calendar['PayrollCalendarID']; // 👈 1. ID Fetch की

                // A. Find Current Period
                $currentPeriod = $this->findCurrentPeriod($xeroStartDate, $type, $today);

                if ($currentPeriod) {
                    // --- 1. Current Period ---
                    $allPayPeriods[] = $this->formatPeriod($currentPeriod, $name, $type, $calendarId, true, 'Current');

                    // --- 2. Future Period (Next 1) ---
                    $nextPeriod = $this->calculateNextPeriod($currentPeriod['start'], $type);
                    $allPayPeriods[] = $this->formatPeriod($nextPeriod, $name, $type, $calendarId, false, 'Future');
                    // --- 3. Past Periods (Last 3) ---
                    $tempStart = $currentPeriod['start']; // Start anchoring from current
                    
                    for ($i = 1; $i <= 3; $i++) {
                        $pastPeriod = $this->calculatePreviousPeriod($tempStart, $type);
                        
                        $allPayPeriods[] = $this->formatPeriod($pastPeriod, $name, $type, $calendarId, false, 'Past');
                        
                        // Update anchor for next iteration (move further back)
                        $tempStart = $pastPeriod['start'];
                    }
                }
            }

            // Sort: Future dates first, Past dates last
            usort($allPayPeriods, function ($a, $b) {
                return strtotime($b['start_date']) - strtotime($a['start_date']);
            });

            // 3. Store in Database (Sync Logic Updated)
            $this->syncPayPeriodsToDatabase($orgId, $allPayPeriods);

            return response()->json([
                'status' => true,
                'pay_periods' => $allPayPeriods,
                'message' => 'Periods (Past, Current, Future) calculated and stored'
            ]);

        } catch (\Exception $e) {
            Log::error('Payroll Error', ['msg' => $e->getMessage()]);
            return response()->json(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to format the array for response/storage
     */
    private function formatPeriod($periodData, $name, $type, $id, $isCurrent, $statusTag)
    {
        return [
            'calendar_id'   => $id, // 👈 Store here
            'calendar_name' => $name,
            'calendar_type' => $type,
            'start_date' => $periodData['start']->toDateString(),
            'end_date'   => $periodData['end']->toDateString(),
            'start_date_formatted' => $periodData['start']->format('d M Y'),
            'end_date_formatted'   => $periodData['end']->format('d M Y'),
            'number_of_days' => $periodData['days'],
            'is_current' => $isCurrent,
            'period_category' => $statusTag // Optional: helpful for debugging
        ];
    }

    /**
     * Syncs data: Keeps the specific periods we just calculated, deletes everything else for this Org.
     */
    private function syncPayPeriodsToDatabase($orgId, array $periods)
    {
        // 1. Collect all the Start Dates we are about to save
        $activeStartDates = array_column($periods, 'start_date');

        // 2. Delete "Stale" Data
        // We delete any record for this Org that matches the calendar names found
        // BUT does NOT match the start dates we just calculated.
        // This effectively removes "Old Past" data (older than 3 periods)
        // while keeping the 3 past + 1 current + 1 future valid.
        
        $calendarNames = array_unique(array_column($periods, 'calendar_name'));

        PayPeriod::where('organization_id', $orgId)
            ->whereIn('calendar_name', $calendarNames)
            ->whereNotIn('start_date', $activeStartDates)
            ->delete();

        // 3. Update or Create the valid periods
        foreach ($periods as $period) {
            PayPeriod::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'calendar_name'   => $period['calendar_name'],
                    'start_date'      => $period['start_date'],
                ],
                [
                    'calendar_id'     => $period['calendar_id'], // 👈 Saving to DB
                    'calendar_type'   => $period['calendar_type'],
                    'end_date'        => $period['end_date'],
                    'number_of_days'  => $period['number_of_days'],
                    'is_current'      => $period['is_current'],
                ]
            );
        }
    }

    // --- Date Calculation Helpers ---

    private function findCurrentPeriod(Carbon $anchorDate, string $type, Carbon $targetDate)
    {
        $period = $this->calculateEndDate($anchorDate, $type);
        if (!$period) return null;

        $start = $period['start'];
        $end   = $period['end'];
        $safety = 0;

        // Loop forward until we hit today
        while ($end->lt($targetDate) && $safety < 1000) {
            if ($type === 'MONTHLY') $start->addMonth();
            elseif ($type === 'QUARTERLY') $start->addMonths(3);
            elseif ($type === 'WEEKLY') $start->addWeeks(1);
            elseif ($type === 'FORTNIGHTLY') $start->addWeeks(2);
            else $start->addDays($period['days']);
            
            $period = $this->calculateEndDate($start, $type);
            $end = $period['end'];
            $safety++;
        }
        return $period;
    }

    private function calculateEndDate(Carbon $startDate, string $type)
    {
        $start = $startDate->copy();
        $end   = $startDate->copy();
        $days  = 0;
        switch ($type) {
            case 'WEEKLY': $end->addDays(6); $days = 7; break;
            case 'FORTNIGHTLY': $end->addDays(13); $days = 14; break;
            case 'MONTHLY': $end->addMonth()->subDay(); $days = $start->diffInDays($end) + 1; break;
            case 'FOURWEEKLY': $end->addDays(27); $days = 28; break;
            case 'QUARTERLY': $end->addMonths(3)->subDay(); $days = $start->diffInDays($end) + 1; break;
            default: return null;
        }
        return ['start' => $start, 'end' => $end, 'days' => $days];
    }

    private function calculateNextPeriod(Carbon $currentStartDate, string $type)
    {
        $nextStart = $currentStartDate->copy();
        if ($type === 'MONTHLY') $nextStart->addMonth();
        elseif ($type === 'QUARTERLY') $nextStart->addMonths(3);
        elseif ($type === 'WEEKLY') $nextStart->addWeeks(1);
        elseif ($type === 'FORTNIGHTLY') $nextStart->addWeeks(2);
        elseif ($type === 'FOURWEEKLY') $nextStart->addWeeks(4);
        
        return $this->calculateEndDate($nextStart, $type);
    }

    /**
     * 🆕 Calculates the Previous Period (Backwards)
     */
    private function calculatePreviousPeriod(Carbon $currentStartDate, string $type)
    {
        $prevStart = $currentStartDate->copy();

        if ($type === 'MONTHLY') {
            $prevStart->subMonth();
        } elseif ($type === 'QUARTERLY') {
            $prevStart->subMonths(3);
        } elseif ($type === 'WEEKLY') {
            $prevStart->subWeeks(1);
        } elseif ($type === 'FORTNIGHTLY') {
            $prevStart->subWeeks(2);
        } elseif ($type === 'FOURWEEKLY') {
             $prevStart->subWeeks(4);
        }

        return $this->calculateEndDate($prevStart, $type);
    }

    private function parseXeroDate($xeroDate)
    {
        if (!$xeroDate) return null;
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            return Carbon::createFromTimestampMs($matches[1]);
        }
        try { return Carbon::parse($xeroDate); } catch (\Exception $e) { return null; }
    }




   
    // ------------------------------------------------------------------
    // 🧮 DATE CALCULATION HELPERS
    // ------------------------------------------------------------------

    // private function formatPeriod($periodData, $name, $type, $isCurrent)
    // {
    //     return [
    //         'calendar_name' => $name,
    //         'calendar_type' => $type,
    //         'start_date' => $periodData['start']->toDateString(),
    //         'end_date'   => $periodData['end']->toDateString(),
    //         'number_of_days' => $periodData['days'],
    //         'is_current' => $isCurrent
    //     ];
    // }

    // private function findCurrentPeriod(Carbon $anchorDate, string $type, Carbon $targetDate)
    // {
    //     $period = $this->calculateEndDate($anchorDate, $type);
    //     if (!$period) return null;

    //     $start = $period['start'];
    //     $end   = $period['end'];
    //     $safety = 0;

    //     while ($end->lt($targetDate) && $safety < 1000) {
    //         if ($type === 'MONTHLY') $start->addMonth();
    //         elseif ($type === 'QUARTERLY') $start->addMonths(3);
    //         elseif ($type === 'WEEKLY') $start->addWeeks(1);
    //         elseif ($type === 'FORTNIGHTLY') $start->addWeeks(2);
    //         else $start->addDays($period['days']);
            
    //         $period = $this->calculateEndDate($start, $type);
    //         $end = $period['end'];
    //         $safety++;
    //     }
    //     return $period;
    // }

    // private function calculateNextPeriod(Carbon $currentStartDate, string $type)
    // {
    //     $nextStart = $currentStartDate->copy();
    //     if ($type === 'MONTHLY') $nextStart->addMonth();
    //     elseif ($type === 'QUARTERLY') $nextStart->addMonths(3);
    //     elseif ($type === 'WEEKLY') $nextStart->addWeeks(1);
    //     elseif ($type === 'FORTNIGHTLY') $nextStart->addWeeks(2);
    //     elseif ($type === 'FOURWEEKLY') $nextStart->addWeeks(4);
    //     return $this->calculateEndDate($nextStart, $type);
    // }

    // private function calculatePreviousPeriod(Carbon $currentStartDate, string $type)
    // {
    //     $prevStart = $currentStartDate->copy();
    //     if ($type === 'MONTHLY') $prevStart->subMonth();
    //     elseif ($type === 'QUARTERLY') $prevStart->subMonths(3);
    //     elseif ($type === 'WEEKLY') $prevStart->subWeeks(1);
    //     elseif ($type === 'FORTNIGHTLY') $prevStart->subWeeks(2);
    //     elseif ($type === 'FOURWEEKLY') $prevStart->subWeeks(4);
    //     return $this->calculateEndDate($prevStart, $type);
    // }

    // private function calculateEndDate(Carbon $startDate, string $type)
    // {
    //     $start = $startDate->copy();
    //     $end   = $startDate->copy();
    //     $days  = 0;
    //     switch ($type) {
    //         case 'WEEKLY': $end->addDays(6); $days = 7; break;
    //         case 'FORTNIGHTLY': $end->addDays(13); $days = 14; break;
    //         case 'MONTHLY': $end->addMonth()->subDay(); $days = $start->diffInDays($end) + 1; break;
    //         case 'FOURWEEKLY': $end->addDays(27); $days = 28; break;
    //         case 'QUARTERLY': $end->addMonths(3)->subDay(); $days = $start->diffInDays($end) + 1; break;
    //         default: return null;
    //     }
    //     return ['start' => $start, 'end' => $end, 'days' => $days];
    // }

    // private function parseXeroDate($xeroDate)
    // {
    //     if (!$xeroDate) return null;
    //     if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
    //         return Carbon::createFromTimestampMs($matches[1]);
    //     }
    //     try { return Carbon::parse($xeroDate); } catch (\Exception $e) { return null; }
    // }


//-----------------------------------------------------------------------------------------------------------

//payrun and payslip controllers will be added here in the future


public function create(Request $request)
    {
        // ---------------------------------------------------
        // 1. VALIDATION & SETUP
        // ---------------------------------------------------
        $request->validate([
            'organization_id' => 'required',
            'from_date'       => 'required|date',
            'to_date'         => 'required|date',
        ]);

        $orgId = $request->organization_id;

        // Get Pay Period (for Calendar ID & Name)
        $payPeriod = PayPeriod::where('organization_id', $orgId)
            ->where('start_date', $request->from_date)
            ->where('end_date', $request->to_date)
            ->first();

        if (!$payPeriod || !$payPeriod->calendar_id) {
            return response()->json([
                'status' => false, 
                'message' => 'Pay period or Calendar ID not found.'
            ], 422);
        }

        // Get Xero Connection
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->firstOrFail();

        $connection = app(\App\Services\Xero\XeroTokenService::class)
            ->refreshIfNeeded($connection);

        // ---------------------------------------------------
        // 2. CALL 1: CREATE PAY RUN (POST)
        // ---------------------------------------------------
        $payload = [[
            "PayrollCalendarID" => $payPeriod->calendar_id,
            "PayRunType"        => "Scheduled"
        ]];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json'
            ])->post('https://api.xero.com/payroll.xro/1.0/PayRuns', $payload);

            if (!$response->successful()) {
                return response()->json(['status' => false, 'message' => 'Xero Create Error', 'details' => $response->json()], $response->status());
            }

            // ---------------------------------------------------
            // 3. SAVE INITIAL DATA (Response 1)
            // ---------------------------------------------------
            $createdRun = $response->json()['PayRuns'][0];
            $payRunId   = $createdRun['PayRunID']; // We need this ID for the next call

            // Parse Dates
            $startDate   = $this->parseXeroDate($createdRun['PayRunPeriodStartDate']);
            $endDate     = $this->parseXeroDate($createdRun['PayRunPeriodEndDate']);
            $paymentDate = $this->parseXeroDate($createdRun['PaymentDate']);

            // Create or Update DB Record (Initial Save)
            $dbPayRun = XeroPayRun::updateOrCreate(
                ['xero_pay_run_id' => $payRunId],
                [
                    'organization_id'          => $orgId,
                    'xero_connection_id'       => $connection->id,
                    'xero_payroll_calendar_id' => $createdRun['PayrollCalendarID'],
                    'calendar_name'            => $payPeriod->calendar_name, // Taken from local PayPeriod
                    'period_start_date'        => $startDate,
                    'period_end_date'          => $endDate,
                    'payment_date'             => $paymentDate,
                    'status'                   => $createdRun['PayRunStatus'],
                    'pay_run_type'             => 'Scheduled',
                    'is_synced'                => false, // Not fully synced yet
                    'xero_data'                => $createdRun
                ]
            );

            // ---------------------------------------------------
            // 4. CALL 2: GET FULL DETAILS (GET)
            // ---------------------------------------------------
            // Now we call Xero again using the ID we just got to fetch Wages, Tax, etc.
            $detailsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json'
            ])->get("https://api.xero.com/payroll.xro/1.0/PayRuns/{$payRunId}");

            if ($detailsResponse->successful()) {
                $fullData = $detailsResponse->json()['PayRuns'][0];
                $payslips = $fullData['Payslips'] ?? [];

                // ---------------------------------------------------
                // 5. UPDATE DATABASE WITH FINANCIALS (Response 2)
                // ---------------------------------------------------
                $dbPayRun->update([
                    'total_wages'         => $fullData['Wages'] ?? 0,
                    'total_tax'           => $fullData['Tax'] ?? 0,
                    'total_super'         => $fullData['Super'] ?? 0,
                    'total_reimbursement' => $fullData['Reimbursement'] ?? 0,
                    'total_deductions'    => $fullData['Deductions'] ?? 0,
                    'total_net_pay'       => $fullData['NetPay'] ?? 0,
                    'employee_count'      => count($payslips),
                    'xero_data'           => $fullData, // Update with the fuller data
                    'is_synced'           => true,
                    'last_synced_at'      => now(),
                ]);
                
                // Optional: You can loop through $payslips here and save them to XeroPayslip table if needed
            }

            return response()->json([
                'status' => true,
                'message' => 'Pay Run created and synced successfully',
                'data' => $dbPayRun->fresh() // Return the updated model
            ]);

        } catch (\Exception $e) {
            Log::error('Xero PayRun Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to parse Xero /Date(123123)/ format
     */
    // private function parseXeroDate($xeroDate)
    // {
    //     if (!$xeroDate) return null;
    //     if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
    //         // matches[1] is the timestamp in milliseconds
    //         return Carbon::createFromTimestampMs($matches[1])->toDateString();
    //     }
    //     try {
    //         return Carbon::parse($xeroDate)->toDateString();
    //     } catch (\Exception $e) {
    //         return null;
    //     }
    // }
  
     
 public function show(Request $request)
{
    // 1. Validation
    $request->validate([
        'organization_id' => 'required',
        'from_date'       => 'required|date',
        'to_date'         => 'required|date',
    ]);

    // 2. Fetch from Local Database
    // We filter records where the Pay Run period falls within the requested range
    $payRuns = \App\Models\XeroPayRun::where('organization_id', $request->organization_id)
        ->where('period_start_date', '>=', $request->from_date) // Start on/after From Date
        ->where('period_end_date', '<=', $request->to_date)     // End on/before To Date
        ->orderBy('period_start_date', 'desc') // Latest first
        ->get();

    return response()->json([
        'status' => true,
        'count'  => $payRuns->count(),
        'data'   => $payRuns
    ]);
}


public function approve(Request $request, $id) // $id = Xero PayRun ID
{
    $orgId = $request->organization_id; // Frontend se bhejna jaruri hai

    // 1. Connection Logic
    $connection = \App\Models\XeroConnection::where('organization_id', $orgId)
        ->where('is_active', 1)->firstOrFail();

    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    // 2. Prepare Payload (Status change karne ke liye)
    // Xero me Approve karne ka matlab hai Status ko 'POSTED' set karna
    $payload = [
        [
            "PayRunID" => $id,
            "PayRunStatus" => "POSTED" 
        ]
    ];

    // 3. API Call (Note: URL me '/approve' nahi lagana hai)
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $connection->access_token,
        'Xero-Tenant-Id' => $connection->tenant_id,
        'Accept' => 'application/json'
    ])->post("https://api.xero.com/payroll.xro/1.0/PayRuns/$id", $payload);

    if (!$response->successful()) {
        return response()->json([
            'status' => false,
            'message' => 'Xero Approval Failed',
            'details' => $response->json()
        ], $response->status());
    }

    $data = $response->json();

    // 4. Update Local Database
    // Agar success hai, to apne table me bhi status update kar do
    $localPayRun = \App\Models\XeroPayRun::where('xero_pay_run_id', $id)->first();
    
    if ($localPayRun) {
        $localPayRun->update([
            'status' => 'POSTED',
            'is_synced' => true,
            'last_synced_at' => now()
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Pay Run Approved (Posted) successfully',
        'data' => $data
    ]);
}


  
public function payslips()
{
    $connection = XeroConnection::where('is_active',1)->firstOrFail();

    $connection = app(\App\Services\Xero\XeroTokenService::class)
        ->refreshIfNeeded($connection);

    $response = Http::withHeaders([
        'Authorization'=>'Bearer '.$connection->access_token,
        'Xero-Tenant-Id'=>$connection->tenant_id
    ])->get('https://api.xero.com/payroll.xro/1.0/Payslips');

    return response()->json($response->json());
}



// इस API को call करें जब Pay Run Create/Update हो जाए
    public function syncPayslips(Request $request)
    {
        $request->validate([
            'organization_id' => 'required',
            'xero_pay_run_id' => 'required' // Database ID or Xero ID of the PayRun
        ]);

        $orgId = $request->organization_id;
        $payRunId = $request->xero_pay_run_id;

        // 1. Fetch Local PayRun Record (to get the list of Payslip IDs)
        $payRun = XeroPayRun::where('organization_id', $orgId)
            ->where('xero_pay_run_id', $payRunId)
            ->firstOrFail();

        // Check if we have basic payslip data from the PayRun call
        $basicPayslips = $payRun->xero_data['Payslips'] ?? [];

        if (empty($basicPayslips)) {
            return response()->json(['status' => false, 'message' => 'No payslips found in this Pay Run record. Sync PayRun first.']);
        }

        // 2. Setup Connection
        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->firstOrFail();

        $connection = app(\App\Services\Xero\XeroTokenService::class)
            ->refreshIfNeeded($connection);

        $syncedCount = 0;

        // 3. Loop through each Payslip ID found in the PayRun
        foreach ($basicPayslips as $basicInfo) {
            
            $payslipId = $basicInfo['PayslipID'];
            
            // --- API CALL: Get Detailed Payslip ---
            // यह API हमें EarningsLines, DeductionLines, etc. देगी
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json'
            ])->get("https://api.xero.com/payroll.xro/1.0/Payslip/{$payslipId}");

            if (!$response->successful()) {
                Log::error("Failed to fetch Payslip {$payslipId}");
                continue;
            }

            $detail = $response->json()['Payslip'];

            // 4. Resolve Employee
            $empConnection = EmployeeXeroConnection::where('xero_employee_id', $detail['EmployeeID'])->first();
            
            // 5. Calculate Worked Hours (Sum of Earnings Units)
            $totalHours = 0;
            $overtimeHours = 0; // Logic can be added if you identify overtime specific rates
            
            if (isset($detail['EarningsLines'])) {
                foreach ($detail['EarningsLines'] as $line) {
                    // Check if it's a unit-based earning (like Hours)
                    if (isset($line['NumberOfUnits'])) {
                        $totalHours += (float) $line['NumberOfUnits'];
                    }
                }
            }

            // 6. Save to Database
            XeroPayslip::updateOrCreate(
                [
                    'xero_payslip_id' => $payslipId
                ],
                [
                    'xero_connection_id' => $connection->id,
                    'organization_id' => $orgId,
                    'xero_pay_run_id' => $payRun->id, // Linking to your local PayRun ID
                    'employee_xero_connection_id' => $empConnection ? $empConnection->id : null,
                    'xero_employee_id' => $detail['EmployeeID'],
                    
                    // Financials
                    'wages' => $detail['Wages'] ?? 0,
                    'tax_deducted' => $detail['Tax'] ?? 0,
                    'super_deducted' => $detail['Super'] ?? 0,
                    'net_pay' => $detail['NetPay'] ?? 0,
                    'total_earnings' => $detail['Wages'] ?? 0, // Usually Wages = Earnings unless reimbursements differ
                    'total_deductions' => $detail['Deductions'] ?? 0,
                    'reimbursements' => $detail['Reimbursements'] ?? 0,
                    
                    // Calculated Fields
                    'hours_worked' => $totalHours,
                    
                    // JSON Line Items (Directly saving array)
                    'earnings_lines' => $detail['EarningsLines'] ?? [],
                    'deduction_lines' => $detail['DeductionLines'] ?? [],
                    'leave_lines' => $detail['LeaveAccrualLines'] ?? [], // Xero calls it LeaveAccrualLines
                    'reimbursement_lines' => $detail['ReimbursementLines'] ?? [],
                    'super_lines' => $detail['SuperannuationLines'] ?? [],
                    
                    'xero_data' => $detail,
                    'is_synced' => true,
                    'last_synced_at' => now(),
                ]
            );

            $syncedCount++;
        }

        // Optional: Update employee count in PayRun
        $payRun->update(['employee_count' => $syncedCount]);

        return response()->json([
            'status' => true, 
            'message' => "Synced {$syncedCount} payslips successfully"
        ]);
    }




    public function payslipget(Request $request)
{
    // 1. Query Builder Start
    // "with" ka use karein taaki Employee ka naam aur PayRun ki date bhi aa jaye
    $query = XeroPayslip::with(['employeeConnection.employee', 'payRun']);

    // ----------------------------------------------------
    // CASE A: Filter by Pay Run (HR View)
    // ----------------------------------------------------
    // Example: ?xero_pay_run_id=5 (Database ID)
    if ($request->has('xero_pay_run_id')) {
        $query->where('xero_pay_run_id', $request->xero_pay_run_id);
    }

    // ----------------------------------------------------
    // CASE B: Filter by Employee (Employee History)
    // ----------------------------------------------------
    // Example: ?employee_id=12 (Local Employee ID)
    if ($request->has('employee_id')) {
        $query->whereHas('employeeConnection', function ($q) use ($request) {
            $q->where('employee_id', $request->employee_id);
        });
    }

    // ----------------------------------------------------
    // CASE C: Filter by Date Range (Optional)
    // ----------------------------------------------------
    if ($request->has('from_date') && $request->has('to_date')) {
        // Hum PayRun ki payment date check karenge
        $query->whereHas('payRun', function ($q) use ($request) {
            $q->whereBetween('payment_date', [$request->from_date, $request->to_date]);
        });
    }

    // 3. Sorting & Pagination
    $payslips = $query->orderByDesc('id')->paginate(15);

    return response()->json([
        'status' => true,
        'data' => $payslips
    ]);
}

// Single Payslip Details (Print View ke liye)
public function employeeshow($id)
{
    $payslip = XeroPayslip::with(['employeeConnection.employee', 'payRun'])
        ->findOrFail($id);

    return response()->json([
        'status' => true,
        'data' => $payslip
    ]);
}




//-----------------------------------------------------------------
//for leaves --------------------------------


// ----------------------------------------------------------------
    // 1. SYNC LEAVE TYPES (Configuration Page ke liye)
    // ----------------------------------------------------------------
    public function syncLeaveTypes(Request $request)
    {
        $request->validate(['organization_id' => 'required']);
        $orgId = $request->organization_id;

        $connection = $this->getXeroConnection($orgId);

        $response = Http::withHeaders($this->getHeaders($connection))
            ->get('https://api.xero.com/payroll.xro/1.0/LeaveTypes');

        if (!$response->successful()) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch types'], 500);
        }

        $types = $response->json()['LeaveTypes'] ?? [];

        foreach ($types as $type) {
            XeroLeaveType::updateOrCreate(
                ['xero_leave_type_id' => $type['LeaveTypeID']],
                [
                    'organization_id' => $orgId,
                    'name' => $type['Name'],
                    'type_of_units' => $type['TypeOfUnits'] ?? 'Hours',
                    'is_paid_leave' => $type['IsPaidLeave'] ?? true,
                    'show_on_payslip' => $type['ShowOnPayslip'] ?? true,
                ]
            );
        }

        return response()->json([
            'status' => true, 
            'message' => 'Leave Types Synced Successfully',
            'data' => XeroLeaveType::where('organization_id', $orgId)->get()
        ]);
    }

    // ----------------------------------------------------------------
    // 2. CREATE/PUSH LEAVE TO XERO (Jab Manager Approve kare)
    // ----------------------------------------------------------------
    public function applyLeave(Request $request)
    {
        $request->validate([
            'organization_id' => 'required',
            'employee_id'     => 'required', // Local Employee ID
            'leave_type_id'   => 'required', // Xero Leave Type ID (Select from DB)
            'start_date'      => 'required|date',
            'end_date'        => 'required|date',
            'description'     => 'nullable|string',
            'local_leave_id'  => 'nullable' // Optional: Link to local HRMS
        ]);

        $orgId = $request->organization_id;

        // 1. Get Connections
        $connection = $this->getXeroConnection($orgId);
        $empConn = EmployeeXeroConnection::where('employee_id', $request->employee_id)->firstOrFail();

        // 2. Prepare Payload
        $payload = [
            [
                "EmployeeID" => $empConn->xero_employee_id,
                "LeaveTypeID" => $request->leave_type_id,
                "Title" => $request->description ?? 'Leave Application',
                "StartDate" => $request->start_date,
                "EndDate" => $request->end_date,
                "Description" => "Applied via HRMS"
            ]
        ];

        // 3. Send to Xero
        $response = Http::withHeaders($this->getHeaders($connection))
            ->post('https://api.xero.com/payroll.xro/1.0/LeaveApplications', $payload);

        if (!$response->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'Xero Error',
                'details' => $response->json()
            ], $response->status());
        }

        // 4. Save Response to DB
        $xeroData = $response->json()['LeaveApplications'][0];

        $leaveApp = XeroLeaveApplication::create([
            'organization_id' => $orgId,
            'employee_xero_connection_id' => $empConn->id,
            'xero_connection_id' => $connection->id,
            'xero_leave_id' => $xeroData['LeaveApplicationID'],
            'xero_employee_id' => $empConn->xero_employee_id,
            'xero_leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'APPROVED', // Usually pushes as Approved
            'title' => $request->description,
            'xero_data' => $xeroData,
            'is_synced' => true,
            'last_synced_at' => now(),
            // 'local_leave_id' => $request->local_leave_id // Uncomment if you added the column
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave pushed to Xero successfully',
            'data' => $leaveApp
        ]);
    }

    // ----------------------------------------------------------------
    // 3. FETCH LEAVES (List View)
    // ----------------------------------------------------------------
    public function index(Request $request)
    {
        $query = XeroLeaveApplication::with('employeeConnection.employee');

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }
        
        // Filter by specific employee
        if ($request->has('employee_id')) {
            $query->whereHas('employeeConnection', function($q) use ($request){
                $q->where('employee_id', $request->employee_id);
            });
        }

        return response()->json([
            'status' => true,
            'data' => $query->orderByDesc('start_date')->get()
        ]);
    }

    // --- Private Helpers ---
    private function getXeroConnection($orgId) {
        $connection = XeroConnection::where('organization_id', $orgId)->where('is_active', 1)->firstOrFail();
        return app(\App\Services\Xero\XeroTokenService::class)->refreshIfNeeded($connection);
    }

    private function getHeaders($connection) {
        return [
            'Authorization' => 'Bearer ' . $connection->access_token,
            'Xero-Tenant-Id' => $connection->tenant_id,
            'Accept' => 'application/json'
        ];
    }





   }
