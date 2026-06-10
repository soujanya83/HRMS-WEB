<?php

namespace App\Http\Controllers\API\V1\Rostering;

use App\Http\Controllers\Controller;
use App\Models\Rostering\ShiftSwapRequest;
use Illuminate\Http\Request;
use App\Models\Rostering\Roster;

class ShiftSwapRequestController extends Controller
{
    // List all swap requests (optionally by status/manager)
    public function index(Request $request)
    {
        $query = ShiftSwapRequest::with([
            'requester', 'requesterRoster', 'requestedEmployee', 'requestedRoster', 'managerApprover'
        ]);
        if ($request->status) $query->where('status', $request->status);
        if ($request->manager_approver_id) $query->where('manager_approver_id', $request->manager_approver_id);
        $swaps = $query->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $swaps], 200);
    }

    // Create swap request
    public function store(Request $request)
    {
            $validated = $request->validate([
            'requester_employee_id' => 'required|exists:employees,id',
            'requested_employee_id' => 'required|exists:employees,id|different:requester_employee_id',
            'roster_date' => 'required|date',
            'requester_reason' => 'required|string|max:1000',
        ]);
        $requesterRoster = Roster::where('employee_id', $validated['requester_employee_id'])
            ->whereDate('roster_date', $validated['roster_date'])
            ->first();

            if (!$requesterRoster) {
            return response()->json([
                'success' => false,
                'message' => 'Requester roster not found for selected date.'
            ], 422);
        }

        $requestedRoster = Roster::where('employee_id', $validated['requested_employee_id'])
            ->whereDate('roster_date', $validated['roster_date'])
            ->first();

            if (!$requestedRoster) {
            return response()->json([
                'success' => false,
                'message' => 'Requested employee roster not found for selected date.'
            ], 422);
        }

        if ($requesterRoster->shift_id == $requestedRoster->shift_id) {
            return response()->json([
                'success' => false,
                'message' => 'Both employees are already assigned to the same shift.'
            ], 422);
        }

     $existingSwap = ShiftSwapRequest::whereIn('status', ['Pending', 'Accepted'])
    ->where(function ($query) use ($requesterRoster, $requestedRoster) {

        $query->where('requester_roster_id', $requesterRoster->id)
              ->orWhere('requested_roster_id', $requesterRoster->id)
              ->orWhere('requester_roster_id', $requestedRoster->id)
              ->orWhere('requested_roster_id', $requestedRoster->id);

    })
    ->exists();

     if ($existingSwap) {
    return response()->json([
        'success' => false,
        'message' => 'A swap request already exists for one of these employees on the selected date.'
    ], 422);
}

        $openSwapExists = ShiftSwapRequest::whereIn('status', ['Pending', 'Accepted'])
            ->where(function ($query) use ($requesterRoster, $requestedRoster) {

                $query->where('requester_roster_id', $requesterRoster->id)
                    ->orWhere('requested_roster_id', $requesterRoster->id)
                    ->orWhere('requester_roster_id', $requestedRoster->id)
                    ->orWhere('requested_roster_id', $requestedRoster->id);

            })
            ->exists();

            if ($openSwapExists) {
            return response()->json([
                'success' => false,
                'message' => 'A swap request is already pending for one of these rosters.'
            ], 422);
        }

        $swap = ShiftSwapRequest::create([
            'requester_employee_id' => $validated['requester_employee_id'],
            'requested_employee_id' => $validated['requested_employee_id'],

            'requester_roster_id' => $requesterRoster->id,
            'requested_roster_id' => $requestedRoster->id,

            'requester_reason' => $validated['requester_reason'],
            'status' => 'Pending',
        ]);



return response()->json(['success' => true, 'data' => $swap], 201);
        
    }

    // Show swap request
    public function show($id)
    {
        $swap = ShiftSwapRequest::with([
            'requester', 'requesterRoster', 'requestedEmployee', 'requestedRoster', 'managerApprover'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $swap], 200);
    }

    // Update (manager approve/reject, set status)
  public function update(Request $request, $id)
{
    $swap = ShiftSwapRequest::findOrFail($id);

    if (in_array($swap->status, ['Approved', 'Rejected', 'Cancelled'])) {
        return response()->json([
            'success' => false,
            'message' => 'This swap request can no longer be edited.'
        ], 422);
    }

    $validated = $request->validate([
        'requester_employee_id' => 'required|exists:employees,id',
        'requested_employee_id' => 'required|exists:employees,id|different:requester_employee_id',
        'roster_date' => 'required|date',
        'requester_reason' => 'required|string|max:1000',
    ]);

    $requesterRoster = Roster::where('employee_id', $validated['requester_employee_id'])
        ->whereDate('roster_date', $validated['roster_date'])
        ->first();

    if (!$requesterRoster) {
        return response()->json([
            'success' => false,
            'message' => 'Requester roster not found for selected date.'
        ], 422);
    }

    $requestedRoster = Roster::where('employee_id', $validated['requested_employee_id'])
        ->whereDate('roster_date', $validated['roster_date'])
        ->first();

    if (!$requestedRoster) {
        return response()->json([
            'success' => false,
            'message' => 'Requested employee roster not found for selected date.'
        ], 422);
    }

    if ($requesterRoster->shift_id == $requestedRoster->shift_id) {
        return response()->json([
            'success' => false,
            'message' => 'Both employees are already assigned to the same shift.'
        ], 422);
    }

    // Check if any Approved swap already exists for either employee on this roster date
    $approvedSwapExists = ShiftSwapRequest::where('id', '!=', $swap->id)
        ->where('status', 'Approved')
        ->where(function ($q) use ($validated) {

            $q->where('requester_employee_id', $validated['requester_employee_id'])
                ->orWhere('requested_employee_id', $validated['requester_employee_id'])
                ->orWhere('requester_employee_id', $validated['requested_employee_id'])
                ->orWhere('requested_employee_id', $validated['requested_employee_id']);

        })
        ->whereHas('requesterRoster', function ($q) use ($validated) {
            $q->whereDate('roster_date', $validated['roster_date']);
        })
        ->exists();

    if ($approvedSwapExists) {
        return response()->json([
            'success' => false,
            'message' => 'An approved swap request already exists for one of these employees on the selected date.'
        ], 422);
    }

    // Duplicate pending request check
    $pendingSwapExists = ShiftSwapRequest::where('id', '!=', $swap->id)
        ->where('status', 'Pending')
        ->where(function ($q) use ($validated) {

            $q->where(function ($sub) use ($validated) {

                $sub->where('requester_employee_id', $validated['requester_employee_id'])
                    ->where('requested_employee_id', $validated['requested_employee_id']);

            })->orWhere(function ($sub) use ($validated) {

                $sub->where('requester_employee_id', $validated['requested_employee_id'])
                    ->where('requested_employee_id', $validated['requester_employee_id']);

            });

        })
        ->whereHas('requesterRoster', function ($q) use ($validated) {
            $q->whereDate('roster_date', $validated['roster_date']);
        })
        ->exists();

    if ($pendingSwapExists) {
        return response()->json([
            'success' => false,
            'message' => 'A pending swap request already exists for these employees on the selected date.'
        ], 422);
    }

    $swap->update([
        'requester_employee_id' => $validated['requester_employee_id'],
        'requested_employee_id' => $validated['requested_employee_id'],
        'requester_roster_id' => $requesterRoster->id,
        'requested_roster_id' => $requestedRoster->id,
        'requester_reason' => $validated['requester_reason'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Swap request updated successfully.',
        'data' => $swap->fresh()
    ]);
}

    // Delete/cancel swap request
    public function destroy($id)
    {
        $swap = ShiftSwapRequest::findOrFail($id);
        $swap->delete();
        return response()->json(['success' => true, 'message' => 'Shift swap request deleted'], 200);
    }

    // Approve request
    public function approve(Request $request, $id)
    {
        $swap = ShiftSwapRequest::findOrFail($id);
        $validated = $request->validate([
            'manager_approver_id' => 'required|exists:users,id'
        ]);
        $swap->update([
            'status' => 'Approved',
            'manager_approver_id' => $validated['manager_approver_id'],
            'manager_approved_at' => now(),
            'rejection_reason' => null,
        ]);
        return response()->json(['success' => true, 'data' => $swap], 200);
    }

    // Reject request
    public function reject(Request $request, $id)
    {
        $swap = ShiftSwapRequest::findOrFail($id);
        $validated = $request->validate([
            'manager_approver_id' => 'required|exists:users,id',
            'rejection_reason' => 'required|string|max:1000',
        ]);
        $swap->update([
            'status' => 'Rejected',
            'manager_approver_id' => $validated['manager_approver_id'],
            'manager_approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);
        return response()->json(['success' => true, 'data' => $swap], 200);
    }

    // List by employee (pending, history)
    public function byEmployee($employeeId)
    {
        $swaps = ShiftSwapRequest::where(function ($q) use ($employeeId) {
            $q->where('requester_employee_id', $employeeId)->orWhere('requested_employee_id', $employeeId);
        })->with([
            'requester', 'requestedEmployee', 'requesterRoster', 'requestedRoster'
        ])->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $swaps], 200);
    }
}
