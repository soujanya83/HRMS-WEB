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
        $orgId = $request->organization_id;

        $connection = XeroConnection::where('organization_id', $orgId)
            ->where('is_active', 1)
            ->firstOrFail();

        // Auto refresh token
        $connection = app(\App\Services\Xero\XeroTokenService::class)
            ->refreshIfNeeded($connection);

        $timesheets = Timesheet::where('organization_id', $orgId)
            ->where('status', 'submitted')
            ->whereNull('xero_synced_at')
            ->get()
            ->groupBy('employee_id');

        $created = 0;

        foreach ($timesheets as $employeeId => $rows) {

            $empXero = EmployeeXeroConnection::where('employee_id', $employeeId)->first();

            if (!$empXero) continue;

            $totalHours = $rows->sum('regular_hours');

            $payload = [
                "Timesheets" => [[
                    "EmployeeID" => $empXero->xero_employee_id,
                    "StartDate" => $rows->first()->from_date,
                    "EndDate" => $rows->first()->to_date,
                    "TimesheetLines" => [[
                        "EarningsRateID" => $empXero->OrdinaryEarningsRateID,
                        "NumberOfUnits" => $totalHours
                    ]]
                ]]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $connection->access_token,
                'Xero-Tenant-Id' => $connection->tenant_id,
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/payroll.xro/1.0/Timesheets', $payload);

            if (!$response->successful()) {
    Log::error('Xero Timesheet Push Failed', [
        'employee_id' => $employeeId,
        'payload' => $payload,
        'response' => $response->json(),
        'status' => $response->status(),
    ]);
}

            if ($response->successful()) {

                $xero = $response->json()['Timesheets'][0];

                XeroTimesheet::create([
                    'organization_id' => $orgId,
                    'employee_xero_connection_id' => $empXero->id,
                    'xero_connection_id' => $connection->id,
                    'xero_timesheet_id' => $xero['TimesheetID'],
                    'xero_employee_id' => $empXero->xero_employee_id,
                    'start_date' => $rows->first()->from_date,
                    'end_date' => $rows->first()->to_date,
                    'total_hours' => $totalHours,
                    'ordinary_hours' => $totalHours,
                    'status' => 'CREATED',
                    'xero_data' => $xero,
                    'is_synced' => true,
                    'last_synced_at' => now(),
                ]);

                Timesheet::whereIn('id', $rows->pluck('id'))->update([
                    'xero_synced_at' => now(),
                    'xero_status' => 'pushed'
                ]);

                $created++;
            }
        }

        return response()->json([
            'status' => true,
            'employees_pushed' => $created
        ]);
    }

}
