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
use App\Models\PayPeriod; // Import the new model


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

                // A. Find Current Period
                $currentPeriod = $this->findCurrentPeriod($xeroStartDate, $type, $today);

                if ($currentPeriod) {
                    // --- 1. Current Period ---
                    $allPayPeriods[] = $this->formatPeriod($currentPeriod, $name, $type, true, 'Current');

                    // --- 2. Future Period (Next 1) ---
                    $nextPeriod = $this->calculateNextPeriod($currentPeriod['start'], $type);
                    $allPayPeriods[] = $this->formatPeriod($nextPeriod, $name, $type, false, 'Future');

                    // --- 3. Past Periods (Last 3) ---
                    $tempStart = $currentPeriod['start']; // Start anchoring from current
                    
                    for ($i = 1; $i <= 3; $i++) {
                        $pastPeriod = $this->calculatePreviousPeriod($tempStart, $type);
                        
                        $allPayPeriods[] = $this->formatPeriod($pastPeriod, $name, $type, false, 'Past');
                        
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
    private function formatPeriod($periodData, $name, $type, $isCurrent, $statusTag)
    {
        return [
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



   }
