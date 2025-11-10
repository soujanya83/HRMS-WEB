<?php

namespace App\Services;

use App\Models\Employee\OvertimeRequest;
use App\Models\Employee\Attendance;
use Illuminate\Support\Facades\Auth;

class OvertimeServices
{
    public function updateOvertime(OvertimeRequest $overtime, array $data)
    {
        
        $employee = Auth::user()->employee;
        $designation = $employee->designation->title ?? null;
      

        if (in_array($overtime->status, ['manager_approved', 'hr_approved', 'rejected'])) {
            throw new \Exception('Cannot modify an approved or rejected overtime request.');
        }

        if ($designation === 'HR') {
            $this->processHrApproval($overtime, $data);
        } elseif ($designation === 'Manager') {
            $this->processManagerApproval($overtime, $data);
        } else {
            // dd('here');
            //   dd($data);
            $this->updateEmployeeRequest($overtime, $data);
        }

        $overtime->updated_by = Auth::id();
        $overtime->save();

        if ($overtime->status === 'hr_approved') {
            $this->updateAttendance($overtime->attendance_id);
        }

        return $overtime;
    }

    private function processHrApproval($overtime, $data)
    {
        if ($overtime->status !== 'manager_approved') {
            throw new \Exception('HR can only process requests after manager approval.');
        }

        $overtime->fill([
            'status' => $data['status'] ?? $overtime->status,
            'actual_overtime_hours' => $data['actual_overtime_hours'] ?? $overtime->actual_overtime_hours,
        ]);
    }

    private function processManagerApproval($overtime, $data)
    {
        if ($overtime->status !== 'pending') {
            throw new \Exception('Manager can only act on pending requests.');
        }

        $overtime->fill([
            'status' => $data['status'] ?? $overtime->status,
            'actual_overtime_hours' => $data['actual_overtime_hours'] ?? $overtime->actual_overtime_hours,
        ]);
    }

   private function updateEmployeeRequest($overtime, $data)
{
    // Allow cancel only if user is cancelling their own pending request
    if (isset($data['status']) && $data['status'] === 'cancelled') {
        // Employee can cancel only if current status is still pending
        if ($overtime->status === 'pending') {
            $overtime->status = 'cancelled';
        } else {
            throw new \Exception('You can only cancel a pending overtime request.');
        }
    }

    // Remove status from mass update so user can't manipulate other statuses
    unset($data['status']);

    // Fill other updatable fields (reason, expected hours, etc.)
    $overtime->fill($data);
}


    private function updateAttendance($attendanceId)
    {
        $attendance = Attendance::find($attendanceId);
        if ($attendance) {
            $attendance->is_overtime = true;
            $attendance->save();
        }
    }
}
