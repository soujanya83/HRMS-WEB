<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeeExit;
use Illuminate\Http\Request;

class EmployeeExitController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => EmployeeExit::with(['employee', 'offboardingTasks'])->get()
        ]);
    }

    public function show($id)
    {
        $exit = EmployeeExit::with(['employee', 'offboardingTasks'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $exit]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'           => 'required|exists:employees,id|unique:employee_exits,employee_id',
            'resignation_date'      => 'required|date',
            'last_working_day'      => 'required|date|after_or_equal:resignation_date',
            'reason_for_leaving'    => 'nullable|string|max:1000',
            'exit_interview_feedback'=> 'nullable|string|max:2000',
            'is_eligible_for_rehire'=> 'required|boolean'
        ]);
        $exit = EmployeeExit::create($validated);
        return response()->json([
            'success' => true, 'message' => 'Exit record created', 'data' => $exit
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $exit = EmployeeExit::findOrFail($id);
        $validated = $request->validate([
            'resignation_date'      => 'sometimes|date',
            'last_working_day'      => 'sometimes|date|after_or_equal:resignation_date',
            'reason_for_leaving'    => 'nullable|string|max:1000',
            'exit_interview_feedback'=> 'nullable|string|max:2000',
            'is_eligible_for_rehire'=> 'sometimes|boolean'
        ]);
        $exit->update($validated);

        return response()->json(['success' => true, 'message' => 'Exit updated', 'data' => $exit]);
    }

    public function destroy($id)
    {
        $exit = EmployeeExit::findOrFail($id);
        $exit->delete();
        return response()->json(['success' => true, 'message' => 'Exit record deleted']);
    }

    public function byEmployee($employeeId)
    {
        $exit = EmployeeExit::where('employee_id', $employeeId)->with('offboardingTasks')->first();
        return response()->json(['success' => true, 'data' => $exit]);
    }
}
