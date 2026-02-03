<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\manually_adjusted_attendance;
use App\Models\Employee\Attendance;
use Carbon\Carbon;

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

        return response()->json([
            'status' => true,
            'message' => 'Adjustment request created',
            'data' => $adjustment
        ]);
    }

    // ================= LIST =================

    public function index(Request $request)
    {
        try {
            $organization_id = $request->query('organization_id');

            if (!$organization_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization ID is required.'
                ], 400);
            }

            $adjustments = manually_adjusted_attendance::where('organization_id', $organization_id)
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
