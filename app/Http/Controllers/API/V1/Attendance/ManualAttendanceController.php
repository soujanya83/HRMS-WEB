<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\manually_adjusted_attendance;
use App\Models\Employee\Attendance;
use Carbon\Carbon;
use App\Services\NotificationService;
use App\Models\Employee\Employee;

class ManualAttendanceController extends Controller
{

    // ================= CREATE =================

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required',
            'organization_id' => 'required',
            'attendance_id' => 'required',
            'date' => 'required|date',
            'original_check_in' => 'required',
            'original_check_out' => 'required',
            'adjusted_check_in' => 'required',
            'adjusted_check_out' => 'required',
            'reason' => 'nullable',
            'created_by' => 'nullable'
        ]);

        $data['status'] = 'pending';
        $data['created_at'] = now();

        $adjustment = manually_adjusted_attendance::create($data);

        // ==========================================
        // ADD NOTIFICATION LOGIC HERE
        // ==========================================
        try {
            $employee = Employee::find($data['employee_id']);
            $empName = $employee ? $employee->first_name . ' ' . $employee->last_name : 'An Employee';
            $formattedDate = Carbon::parse($data['date'])->format('d M Y');

            NotificationService::sendDynamic(
                $data['organization_id'],
                'attendance_adjustment_created',
                'New Attendance Adjustment Request',
                "{$empName} has requested a manual attendance adjustment for {$formattedDate}.",
                $data['created_by'] ?? ($employee->user_id ?? null),
                [
                    'adjustment_id' => $adjustment->id,
                    'employee_id' => $data['employee_id'],
                    'route_link' => "/attendance-adjustments" // React frontend page path
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send attendance adjustment notification: ' . $e->getMessage());
        }
        // ==========================================

        return response()->json([
            'status' => true,
            'message' => 'Adjustment request created',
            'data' => $adjustment
        ]);
    }

    // ================= LIST =================

    public function index($id)
    {
        try {
            if (!$id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization ID is required.'
                ], 400);
            }

            $adjustments = manually_adjusted_attendance::where('organization_id', $id)
                ->with(['employee', 'attendance'])
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Manual adjustments retrieved successfully',
                'data' => $adjustments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= VIEW =================

    public function show($id)
    {
        return manually_adjusted_attendance::with(['employee','attendance'])->findOrFail($id);
    }

    // ================= UPDATE =================

    public function update(Request $request, $id)
    {
        $adjust = manually_adjusted_attendance::findOrFail($id);

        if($adjust->status != 'pending'){
            return response()->json(['message'=>'Cannot edit after approval/rejection'],400);
        }

        $adjust->update($request->all());

        // ==========================================
        // ADD NOTIFICATION LOGIC HERE
        // ==========================================
        try {
            // Update mein direct request me data na bhi aaye to DB ($adjust) se lenge
            $empId = $request->employee_id ?? $adjust->employee_id;
            $orgId = $request->organization_id ?? $adjust->organization_id;
            $dateVal = $request->date ?? $adjust->date;

            $employee = Employee::find($empId);
            $empName = $employee ? $employee->first_name . ' ' . $employee->last_name : 'An Employee';
            $formattedDate = Carbon::parse($dateVal)->format('d M Y');

            NotificationService::sendDynamic(
                $orgId,
                'attendance_adjustment_updated',
                'Attendance Adjustment Updated',
                "The attendance adjustment request for {$empName} on {$formattedDate} has been updated.",
                auth()->id() ?? ($employee->user_id ?? null), // Jisne update kiya hai (fallback to employee user id)
                [
                    'adjustment_id' => $adjust->id,
                    'employee_id' => $empId,
                    'route_link' => "/attendance-adjustments" // React frontend page path
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send attendance update notification: ' . $e->getMessage());
        }
        // ==========================================

        return response()->json([
            'status'=>true,
            'message'=>'Updated successfully'
        ]);
    }

    // ================= DELETE =================

    public function destroy($id)
    {
        manually_adjusted_attendance::findOrFail($id)->delete();

        return response()->json([
            'status'=>true,
            'message'=>'Deleted'
        ]);
    }

    // ================= APPROVE =================

    public function approve(Request $request, $id)
    {
        $adjust = manually_adjusted_attendance::findOrFail($id);

        if($adjust->status != 'pending'){
            return response()->json(['message'=>'Already processed'],400);
        }

        // Update Attendance Table
        Attendance::where('id',$adjust->attendance_id)->update([
            'check_in' => $adjust->adjusted_check_in,
            'check_out' => $adjust->adjusted_check_out
        ]);

        $adjust->update([
            'status'=>'approved',
            'approved_by'=>$request->approved_by,
            'approved_at'=>now()
        ]);

        return response()->json([
            'status'=>true,
            'message'=>'Approved & Attendance Updated'
        ]);
    }

    // ================= REJECT =================

    public function reject(Request $request, $id)
    {
        $adjust = manually_adjusted_attendance::findOrFail($id);

        $adjust->update([
            'status'=>'rejected',
            'rejected_by'=>$request->rejected_by
        ]);

        return response()->json([
            'status'=>true,
            'message'=>'Rejected'
        ]);
    }
}
