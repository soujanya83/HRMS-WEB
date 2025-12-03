<?php

namespace App\Http\Controllers\API\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Performance\PerformanceGoal;
use Illuminate\Http\Request;

class PerformanceGoalController extends Controller
{
    public function index(Request $request)
    {
        $query = PerformanceGoal::with([
            'employee', 'manager', 'organization', 'reviewCycle', 'keyResults', 'feedback'
        ]);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->manager_id) $query->where('manager_id', $request->manager_id);
        if ($request->review_cycle_id) $query->where('review_cycle_id', $request->review_cycle_id);
        if ($request->status) $query->where('status', $request->status);
        $goals = $query->orderByDesc('start_date')->get();
        return response()->json(['success' => true, 'data' => $goals], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'employee_id' => 'required|exists:employees,id',
            'review_cycle_id' => 'required|exists:performance_review_cycles,id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:Draft,Active,Completed,Cancelled',
            'manager_id' => 'required|exists:employees,id',
        ]);
        $goal = PerformanceGoal::create($validated);
        return response()->json(['success' => true, 'data' => $goal], 201);
    }

    public function show($id)
    {
        $goal = PerformanceGoal::with([
            'employee', 'manager', 'organization', 'reviewCycle', 'keyResults', 'feedback'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $goal], 200);
    }

    public function update(Request $request, $id)
    {
        $goal = PerformanceGoal::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:Draft,Active,Completed,Cancelled',
            'manager_id' => 'sometimes|exists:employees,id',
        ]);
        $goal->update($validated);
        return response()->json(['success' => true, 'data' => $goal], 200);
    }

    public function destroy($id)
    {
        $goal = PerformanceGoal::findOrFail($id);
        $goal->delete();
        return response()->json(['success' => true, 'message' => 'Goal deleted'], 200);
    }

    // Get goals for one cycle
    public function byCycle($cycleId)
    {
        $goals = PerformanceGoal::where('review_cycle_id', $cycleId)->with(['employee', 'manager', 'keyResults', 'feedback'])->get();
        return response()->json(['success' => true, 'data' => $goals], 200);
    }

    // Get goals by status
    public function byStatus($status)
    {
        $goals = PerformanceGoal::where('status', $status)->get();
        return response()->json(['success' => true, 'data' => $goals], 200);
    }

    // Assign multiple goals (bulk)
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'goals' => 'required|array|min:1',
            'goals.*.organization_id' => 'required|exists:organizations,id',
            'goals.*.employee_id' => 'required|exists:employees,id',
            'goals.*.review_cycle_id' => 'required|exists:performance_review_cycles,id',
            'goals.*.title' => 'required|string|max:150',
            'goals.*.start_date' => 'required|date',
            'goals.*.due_date' => 'required|date|after_or_equal:goals.*.start_date',
            'goals.*.status' => 'required|in:Draft,Active,Completed,Cancelled',
            'goals.*.manager_id' => 'required|exists:employees,id',
        ]);
        $created = [];
        foreach ($validated['goals'] as $data) {
            $created[] = PerformanceGoal::create($data);
        }
        return response()->json(['success' => true, 'data' => $created, 'count' => count($created)], 201);
    }
}
