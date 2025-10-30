<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\ProbationPeriod;
use Illuminate\Http\Request;

class ProbationPeriodController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ProbationPeriod::with('employee')->get()
        ]);
    }

    public function show($id)
    {
        $period = ProbationPeriod::with('employee')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $period]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id|unique:probation_periods,employee_id',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after:start_date',
            'status'            => 'required|in:Active,Completed,Extended,Failed',
            'feedback'          => 'nullable|string|max:500',
            'confirmation_date' => 'nullable|date',
        ]);
        $period = ProbationPeriod::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Probation period created',
            'data' => $period
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $period = ProbationPeriod::findOrFail($id);
        $validated = $request->validate([
            'start_date'        => 'sometimes|date',
            'end_date'          => 'sometimes|date|after_or_equal:start_date',
            'status'            => 'sometimes|in:Active,Completed,Extended,Failed',
            'feedback'          => 'nullable|string|max:500',
            'confirmation_date' => 'nullable|date',
        ]);
        $period->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Probation period updated',
            'data' => $period
        ]);
    }

    public function destroy($id)
    {
        ProbationPeriod::destroy($id);
        return response()->json(['success' => true, 'message' => 'Probation period deleted']);
    }

    public function byEmployee($employeeId)
    {
        $period = ProbationPeriod::where('employee_id', $employeeId)->first();
        return response()->json(['success' => true, 'data' => $period]);
    }
}
