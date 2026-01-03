<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\Leave;
use App\Models\OrganizationLeave;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\XeroConnection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use App\Services\XeroRefreshAccessTokenServices;
use App\Services\XeroEmployeeService;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroLeaveApplication;


class LeaveController extends Controller
{
    //  public function index(Request $request): JsonResponse
    // {
    //     try {
    //         // filter by date range

    //         $query = Leave::with('employee:id,first_name,last_name,personal_email')
    //             ->orderBy('id', 'desc');


    //               if ($request->from && $request->to) {
    //             $query->whereBetween('start_date', [$request->from, $request->to]);
    //         }
    //          $leaves = $query->get();

    //         if ($leaves->isEmpty()) {
    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'No leave requests found',
    //                 'data' => []
    //             ]);
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Leaves retrieved successfully',
    //             'data' => $leaves
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to retrieve leaves',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Leave::with([
                'employee:id,first_name,last_name,personal_email,department_id',
                'employee.department:id,name'
            ])
                ->orderBy('id', 'desc');

            // ✅ SEARCH BY EMPLOYEE NAME (first_name OR last_name)
            if ($request->filled('employee_name')) {
                $search = $request->employee_name;

                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            // ✅ FILTER BY STATUS
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // ✅ FILTER BY LEAVE TYPE
            if ($request->filled('leave_type')) {
                $query->where('leave_type', $request->leave_type);
            }

            // ✅ FILTER BY DEPARTMENT
            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }

            // ✅ FILTER BY DATE RANGE
            if ($request->filled('from') && $request->filled('to')) {
                $query->whereBetween('start_date', [
                    $request->from,
                    $request->to
                ]);
            }

            // ✅ FETCH DATA
            $leaves = $query->get();

            if ($leaves->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No leave requests found',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Filtered leave requests retrieved successfully',
                'data' => $leaves
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve leave requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getLeavesSummary(): JsonResponse
    {
        try {
            // ✅ Leave Type Summary
            $typeSummary = Leave::select('leave_type', DB::raw('COUNT(*) as total_leaves'))
                ->groupBy('leave_type')
                ->get();

            // ✅ Total leave requests
            $totalRequests = Leave::count();

            // ✅ Status summary
            $statusSummary = Leave::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status'); // converts to {"approved": 10, "pending": 5...}

            return response()->json([
                'status' => true,
                'message' => 'Leave summary retrieved successfully',
                'data' => [
                    'total_requests' => $totalRequests,
                    'status_summary' => [
                        'pending'  => $statusSummary['pending'] ?? 0,
                        'approved' => $statusSummary['approved'] ?? 0,
                        'rejected' => $statusSummary['rejected'] ?? 0,
                    ],
                    'leave_type_summary' => $typeSummary
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve leave summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id): JsonResponse
    {
        try {
            $leave = Leave::with('employee:id,first_name,last_name,personal_email')->find($id);

            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Leave retrieved successfully',
                'data' => $leave
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve leave',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // public function store(Request $request, $id = null): JsonResponse
    // {
    //     try {
    //         // Validate JSON input
    //         $validated = $request->validate([
    //             'start_date' => 'required|date',
    //             'end_date' => 'required|date|after_or_equal:start_date',
    //             'leave_type' => 'required|in:casual,sick,earned,maternity,paternity,unpaid',
    //             'reason' => 'nullable|string|max:500',
    //             'XeroLeaveTypeID' => 'required|string',
    //             "employee_id" => [
    //                 'sometimes',
    //                 Rule::exists('employees', 'id')
    //             ],
    //         ]);

    //         // If ID exists, it's an update
    //         if ($id) {
    //             $leave = Leave::find($id);

    //             if (!$leave) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Leave record not found',
    //                 ], 404);
    //             }

    //             $leave->update($validated);

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Leave updated successfully',
    //                 'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
    //             ], 200);
    //         }

    //         if ($request->has('employee_id')) {
    //             $employeeId = Employee::where('id', $request->employee_id)->first();
    //         } else {
    //             // Otherwise, create new record
    //             $employeeId = Employee::where('user_id', Auth::user()->id)->first();

    //             $validated['employee_id'] =  $employeeId->id;
    //         }

    //         $validated['days_count'] = (new \DateTime($validated['end_date']))->diff(new \DateTime($validated['start_date']))->days + 1;

    //         $leave = Leave::create($validated);

    //         $leave = Leave::create($validated);

    //         if ($leave && $leave->id) {
    //             // Leave created successfully in DB
    //             $this->createleaveonXero($leave);
    //         }


    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Leave created successfully',
    //             'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
    //         ], 201);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function store(Request $request, $id = null): JsonResponse
    {
        try {
            // Validate JSON input
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'leave_type' => 'required|in:casual,sick,earned,maternity,paternity,unpaid',
                'reason' => 'nullable|string|max:500',
                'XeroLeaveTypeID' => 'required|string',
                'employee_id' => [
                    'sometimes',
                    Rule::exists('employees', 'id')
                ],
            ]);

            // ------------------------------------------
            // UPDATE (PUT) OPERATION
            // ------------------------------------------
            if ($id) {
                $leave = Leave::find($id);

                if (!$leave) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Leave record not found',
                    ], 404);
                }

                $leave->update($validated);



                return response()->json([
                    'success' => true,
                    'message' => 'Leave updated successfully',
                    'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
                ], 200);
            }

            // ------------------------------------------
            // CREATE OPERATION
            // ------------------------------------------

            // If employee_id not passed, use logged-in employee
            if ($request->has('employee_id')) {
                $employeeRecord = Employee::where('id', $request->employee_id)->first();
            } else {
                $employeeRecord = Employee::where('user_id', Auth::user()->id)->first();
                $validated['employee_id'] = $employeeRecord->id;
            }

            // Calculate leave days
            $validated['days_count'] = (new \DateTime($validated['end_date']))
                ->diff(new \DateTime($validated['start_date']))
                ->days + 1;

            // Create local leave record
            $leave = Leave::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Leave created successfully',
                'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function approve_leave(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:approved,reject,pending',
            ]);

            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found',
                ], 404);
            }

            $leave->status = $validated['status'];
            $leave->approved_by = Auth::id();
            $leave->save();

            // ─────────────────────────────────────────
            // TRIGGER XERO ONLY IF LEAVE IS APPROVED
            // ─────────────────────────────────────────
            if ($validated['status'] === 'approved') {
                $this->createleaveonXero($leave);
            }else if (in_array($validated['status'], ['reject'])) {
                // REJECT LEAVE ON XERO
                $this->rejectApprovedLeaveOnXero($leave);
            }

            return response()->json([
                'status' => true,
                'message' => 'Leave status updated successfully',
                'data' => $leave->load('employee:id,first_name,last_name,personal_email'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createleaveonXero($leave)
    {
        try {
            $employee = Employee::where('id', $leave->employee_id)->first();
            $xeroConnection = XeroConnection::where('organization_id', $employee->organization_id)
                ->where('is_active', 1)
                ->first();

            $employeeXeroConnection = EmployeeXeroConnection::where('employee_id', $employee->id)
                ->where('xero_connection_id', $xeroConnection->id)
                ->first();

            if (!$employeeXeroConnection || !$employeeXeroConnection->xero_employee_id) {
                throw new \Exception("Employee does not have a Xero Employee ID.");
            }

            // Leave Type ID is required
            $leaveTypeId = $leave->xero_leave_type_id ?? $leave->XeroLeaveTypeID ?? null;

            if (!$leaveTypeId) {
                throw new \Exception("Xero LeaveTypeID missing for this leave.");
            }

            // ---------------------------------
            // BUILD PAYLOAD
            // ---------------------------------
            $payload = [
                [
                    "EmployeeID"   => $employeeXeroConnection->xero_employee_id,
                    "LeaveTypeID"  => $leaveTypeId,
                    "StartDate"    => "/Date(" . (strtotime($leave->start_date) * 1000) . "+0000)/",
                    "EndDate"      => "/Date(" . (strtotime($leave->end_date) * 1000) . "+0000)/",
                    "Title"        => $leave->title ?? "Leave Application",
                    "Description"  => $leave->description ?? $leave->reason ?? "Leave Application",
                ]
            ];

            // ---------------------------------
            // API CALL TO XERO
            // ---------------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $xeroConnection->access_token,
                'xero-tenant-id' => $xeroConnection->tenant_id,
                'Accept' => 'application/json'
            ])->post(
                'https://api.xero.com/payroll.xro/1.0/LeaveApplications',
                $payload
            );
            //   dd([
            //     'body' => $response->body(),
            // ]);

            $data = $response->json();

            // ---------------------------------
            // HANDLE ERRORS
            // ---------------------------------
            if ($response->failed()) {
                Log::error("Xero Leave Error", $data);
                throw new \Exception("Xero API Error: " . json_encode($data));
            }

            // ---------------------------------
            // EXTRACT XERO LEAVE APPLICATION ID
            // ---------------------------------
            $xeroLeaveId = $data['LeaveApplications'][0]['LeaveApplicationID'] ?? null;

            if (!$xeroLeaveId) {
                throw new \Exception("Xero response did not return a LeaveApplicationID.");
            }

            $leave->xeroLeaveApplicationId = $xeroLeaveId;
            $leave->save();

            // ---------------------------------
            // STORE IN xero_leave_applications TABLE
            // ---------------------------------
            XeroLeaveApplication::create([
                'organization_id'        => $employee->organization_id,
                'employee_xero_connection_id' => $employeeXeroConnection->id,
                'xero_connection_id'     => $xeroConnection->id,
                'xero_leave_id'          => $xeroLeaveId,
                'xero_employee_id'       => $employeeXeroConnection->xero_employee_id,
                'xero_leave_type_id'     => $leaveTypeId,
                'leave_type_name'        => $leave->leave_type_name,
                'start_date'             => $leave->start_date,
                'end_date'               => $leave->end_date,
                'units'                  => $leave->units ?? null, // optional
                'units_type'             => $leave->units_type ?? null,
                'description'            => $leave->description ?? $leave->reason,
                'title'                  => $leave->title,
                'leave_periods'          => null, // Fill when using LeavePeriods
                'xero_data'              => json_encode($data),
                'last_synced_at'         => now(),
                'is_synced'              => 1
            ]);

            // ---------------------------------
            // UPDATE LOCAL LEAVE RECORD
            // ---------------------------------
            $leave->xero_leave_id = $xeroLeaveId;
            $leave->is_synced = 1;
            $leave->save();

            return $data;
        } catch (\Exception $e) {
            Log::error("Create Leave in Xero Failed: " . $e->getMessage());
            return false;
        }
    }

    // reject , approved leave on xero
    public function rejectApprovedLeaveOnXero($leave)
    {
        try {
            $employee = Employee::find($leave->employee_id);

            $xeroConnection = XeroConnection::where('organization_id', $employee->organization_id)
                ->where('is_active', 1)
                ->first();

            $employeeXeroConnection = EmployeeXeroConnection::where('employee_id', $employee->id)
                ->where('xero_connection_id', $xeroConnection->id)
                ->first();

            if (!$employeeXeroConnection) {
                throw new \Exception("Xero employee mapping not found.");
            }

            $payload = [
                [
                    "LeaveApplicationID" => $leave->xeroLeaveApplicationId,
                    "EmployeeID"         => $employeeXeroConnection->xero_employee_id,
                    "LeaveTypeID"        => $leave->XeroLeaveTypeID,
                    "StartDate"          => "/Date(" . (strtotime($leave->start_date) * 1000) . "+0000)/",
                    "EndDate"            => "/Date(" . (strtotime($leave->end_date) * 1000) . "+0000)/",
                    "Title"              => $leave->title,
                    "Description"        => $leave->reason,

                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $xeroConnection->access_token,
                'xero-tenant-id' => $xeroConnection->tenant_id,
                'Accept' => 'application/json'
            ])->post(
                "https://api.xero.com/payroll.xro/1.0/LeaveApplications/{$leave->xeroLeaveApplicationId}/reject",
                $payload
            );

            return $response->json();
        } catch (\Exception $e) {
            \Log::error("Update Leave in Xero Failed: " . $e->getMessage());
            return false;
        }
    }





    public function destroy($id)
    {
        try {
            // Find the leave record
            $leave = Leave::find($id);

            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave not found',
                ], 404);
            }

            // Delete the record
            $leave->delete();

            return response()->json([
                'status' => true,
                'message' => 'Leave deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete leave',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


  
    public function leaveBalance(Request $request)
    {
        try {
            // 1️⃣ Get organization_id
            if ($request->filled('organization_id')) {
                $organization_id = $request->organization_id;
            } else {
                $employee = Employee::where('user_id', Auth::id())->first();

                if (!$employee) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Employee record not found.'
                    ], 404);
                }

                $organization_id = $employee->organization_id;
            }

            // 2️⃣ Get leave policy for organization
            $leavePolicies = OrganizationLeave::where([
                'organization_id' => $organization_id,
                'is_active' => 1
            ])->get();

            if ($leavePolicies->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No leave policy found.'
                ], 404);
            }

            // 3️⃣ Get all employees of organization
            $employees = Employee::where('organization_id', $organization_id)
                ->select('id', 'first_name', 'last_name')
                ->get();

            // 4️⃣ Get used leaves grouped by employee + leave_type
            $usedLeaves = Leave::select(
                'employee_id',
                'leave_type',
                DB::raw('COUNT(*) as used_count')
            )
                ->whereIn('employee_id', $employees->pluck('id'))
                ->groupBy('employee_id', 'leave_type')
                ->get()
                ->groupBy('employee_id');

            // 5️⃣ Calculate balance
            $result = $employees->map(function ($employee) use ($leavePolicies, $usedLeaves) {

                $employeeUsedLeaves = $usedLeaves[$employee->id] ?? collect();

                $leaveData = $leavePolicies->map(function ($policy) use ($employeeUsedLeaves) {

                    $used = optional(
                        $employeeUsedLeaves->firstWhere('leave_type', $policy->leave_type)
                    )->used_count ?? 0;

                    return [
                        'leave_type' => $policy->leave_type,
                        'description' => $policy->description,
                        'paid' => $policy->paid,
                        'carry_forward' => $policy->carry_forward,
                        'max_carry_forward' => $policy->max_carry_forward,
                        'granted' => $policy->granted_days,
                        'used' => $used,
                        'balance' => max($policy->granted_days - $used, 0),
                    ];
                });

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'leaves' => $leaveData,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Leave balance fetched successfully.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error while fetching leave balance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getXeroLeaveTypes($organization_id): JsonResponse
    {
        try {

            // GET XERO CONNECTION
            $xeroConnection = XeroConnection::where('organization_id', $organization_id)
                ->where('is_active', 1)
                ->first();

            if (!$xeroConnection) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active Xero connection found for this organization.'
                ], 404);
            }

            // XERO CREDS
            $tenantId = $xeroConnection->tenant_id;
            $accessToken = $xeroConnection->access_token;

            // ---------------------------
            // CALL XERO PAYITEMS API
            // ---------------------------
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'xero-tenant-id' => $tenantId,
                'Accept' => 'application/json'
            ])->get('https://api.xero.com/payroll.xro/1.0/PayItems');

            // Handle API failure
            if ($response->failed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to fetch leave types from Xero.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $data = $response->json();

            // Xero leave types are inside PayItems → LeaveTypes
            $leaveTypes = $data['PayItems']['LeaveTypes'] ?? [];

            return response()->json([
                'status' => true,
                'message' => 'Xero leave types fetched successfully.',
                'data' => $leaveTypes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching Xero leave types.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function assignEmployeeleaveType(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->employee_id;
            $leaveType = $request->leave_type;
            $TypeOfUnits = $request->type_of_units;
            $IsPaidLeave = $request->is_paid_leave;
            $xeroleaveId = $request->xero_leave_id;
            $ShowOnPayslip = $request->show_on_payslip;
            $hours = $request->hours;
            $openingBalance = $request->opening_balance;

            $employee = OrganizationLeave::find($employeeId); // assigning the leave type to employee and giving the amount of leave hours to be granted 

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            $employee->default_leave_type_id = $leaveType;
            $employee->save();

            return response()->json([
                'status' => true,
                'message' => 'Leave type assigned successfully.',
                'data' => $employee,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error assigning leave type.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
