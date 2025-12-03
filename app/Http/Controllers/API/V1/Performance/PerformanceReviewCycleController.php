<?php

namespace App\Http\Controllers\API\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Performance\PerformanceReviewCycle;
use Illuminate\Http\Request;

class PerformanceReviewCycleController extends Controller
{
    // List cycles (optionally by organization/status)
    public function index(Request $request)
    {
        $query = PerformanceReviewCycle::with('organization', 'performanceReviews');
        if ($request->organization_id) $query->where('organization_id', $request->organization_id);
        if ($request->status) $query->where('status', $request->status);
        $cycles = $query->orderByDesc('start_date')->get();
        return response()->json(['success' => true, 'data' => $cycles], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'deadline' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:Planned,Active,Closed,Archived',
        ]);
        $cycle = PerformanceReviewCycle::create($validated);
        return response()->json(['success' => true, 'data' => $cycle], 201);
    }

    public function show($id)
    {
        $cycle = PerformanceReviewCycle::with(['organization', 'performanceReviews', 'performanceGoals'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $cycle], 200);
    }

    public function update(Request $request, $id)
    {
        $cycle = PerformanceReviewCycle::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'deadline' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:Planned,Active,Closed,Archived',
        ]);
        $cycle->update($validated);
        return response()->json(['success' => true, 'data' => $cycle], 200);
    }

    public function destroy($id)
    {
        $cycle = PerformanceReviewCycle::findOrFail($id);
        $cycle->delete();
        return response()->json(['success' => true, 'message' => 'Cycle deleted'], 200);
    }

    // List cycles by status
    public function status($status)
    {
        $cycles = PerformanceReviewCycle::where('status', $status)->get();
        return response()->json(['success' => true, 'data' => $cycles], 200);
    }
}
