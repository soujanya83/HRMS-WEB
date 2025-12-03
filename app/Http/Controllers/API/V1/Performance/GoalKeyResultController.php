<?php

namespace App\Http\Controllers\API\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Performance\GoalKeyResult;
use Illuminate\Http\Request;

class GoalKeyResultController extends Controller
{
    public function index(Request $request)
    {
        $query = GoalKeyResult::with('performanceGoal');
        if ($request->performance_goal_id) $query->where('performance_goal_id', $request->performance_goal_id);
        $results = $query->get();
        return response()->json(['success' => true, 'data' => $results], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'performance_goal_id' => 'required|exists:performance_goals,id',
            'description' => 'required|string|max:1000',
            'type' => 'required|in:Quantitative,Qualitative,Binary',
            'start_value' => 'nullable|numeric',
            'target_value' => 'required|numeric',
            'current_value' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500'
        ]);
        $result = GoalKeyResult::create($validated);
        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function show($id)
    {
        $result = GoalKeyResult::with('performanceGoal')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $result], 200);
    }

    public function update(Request $request, $id)
    {
        $result = GoalKeyResult::findOrFail($id);
        $validated = $request->validate([
            'description' => 'sometimes|string|max:1000',
            'type' => 'sometimes|in:Quantitative,Qualitative,Binary',
            'current_value' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500'
        ]);
        $result->update($validated);
        return response()->json(['success' => true, 'data' => $result], 200);
    }

    public function destroy($id)
    {
        $result = GoalKeyResult::findOrFail($id);
        $result->delete();
        return response()->json(['success' => true, 'message' => 'Key Result deleted'], 200);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'key_results' => 'required|array|min:1',
            'key_results.*.id' => 'required|exists:goal_key_results,id',
            'key_results.*.current_value' => 'nullable|numeric',
            'key_results.*.notes' => 'nullable|string|max:500'
        ]);
        $updated = [];
        foreach ($validated['key_results'] as $row) {
            $kr = GoalKeyResult::find($row['id']);
            if ($kr) {
                $kr->update(['current_value' => $row['current_value'], 'notes' => $row['notes'] ?? null]);
                $updated[] = $kr;
            }
        }
        return response()->json(['success' => true, 'data' => $updated, 'count' => count($updated)], 200);
    }
}
