<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmploymentHistory;
use Illuminate\Http\Request;

class EmploymentHistoryController extends Controller
{
    public function index()
    {
        $list = EmploymentHistory::with(['employee', 'department', 'designation'])->get();
        return response()->json(['success' => true, 'data' => $list]);
    }

    public function show($id)
    {
        $item = EmploymentHistory::with(['employee', 'department', 'designation'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'department_id'     => 'required|exists:departments,id',
            'designation_id'    => 'required|exists:designations,id',
            'start_date'        => 'required|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'reason_for_change' => 'nullable|string|max:500',
        ]);
        $rec = EmploymentHistory::create($validated);
        return response()->json(['success' => true, 'message' => 'Employment history created', 'data' => $rec], 201);
    }

    public function update(Request $request, $id)
    {
        $rec = EmploymentHistory::findOrFail($id);
        $validated = $request->validate([
            'department_id'     => 'sometimes|exists:departments,id',
            'designation_id'    => 'sometimes|exists:designations,id',
            'start_date'        => 'sometimes|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'reason_for_change' => 'nullable|string|max:500',
        ]);
        $rec->update($validated);
        return response()->json(['success' => true, 'message' => 'Employment history updated', 'data' => $rec]);
    }

    public function destroy($id)
    {
        EmploymentHistory::destroy($id);
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function byEmployee($employeeId)
    {
        $list = EmploymentHistory::where('employee_id', $employeeId)->with(['department', 'designation'])->get();
        return response()->json(['success' => true, 'data' => $list]);
    }
}
