<?php

namespace App\Http\Controllers\API\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Performance\PerformanceReview;
use Illuminate\Http\Request;

class PerformanceReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = PerformanceReview::with(['reviewCycle', 'employee', 'manager', 'feedback']);
        if ($request->review_cycle_id) $query->where('review_cycle_id', $request->review_cycle_id);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->manager_id) $query->where('manager_id', $request->manager_id);
        if ($request->status) $query->where('status', $request->status);
        $reviews = $query->orderByDesc('review_cycle_id')->get();
        return response()->json(['success' => true, 'data' => $reviews], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'review_cycle_id' => 'required|exists:performance_review_cycles,id',
            'employee_id' => 'required|exists:employees,id',
            'manager_id' => 'required|exists:employees,id',
            'status' => 'required|in:Draft,Submitted,Reviewed,Acknowledged',
        ]);
        $review = PerformanceReview::create($validated);
        return response()->json(['success' => true, 'data' => $review], 201);
    }

    public function show($id)
    {
        $review = PerformanceReview::with([
            'reviewCycle', 'employee', 'manager', 'feedback'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $review], 200);
    }

    public function update(Request $request, $id)
    {
        $review = PerformanceReview::findOrFail($id);
        $validated = $request->validate([
            'employee_comments' => 'nullable|string|max:1000',
            'employee_rating' => 'nullable|integer|min:1|max:5',
            'employee_submitted_at' => 'nullable|date',
            'manager_comments' => 'nullable|string|max:1000',
            'manager_feedback_strengths' => 'nullable|string|max:1000',
            'manager_feedback_areas_for_improvement' => 'nullable|string|max:1000',
            'manager_rating' => 'nullable|integer|min:1|max:5',
            'manager_submitted_at' => 'nullable|date',
            'status' => 'sometimes|in:Draft,Submitted,Reviewed,Acknowledged',
            'acknowledged_at' => 'nullable|date',
        ]);
        $review->update($validated);
        return response()->json(['success' => true, 'data' => $review], 200);
    }

    public function destroy($id)
    {
        $review = PerformanceReview::findOrFail($id);
        $review->delete();
        return response()->json(['success' => true, 'message' => 'Review deleted'], 200);
    }

    // Review acknowledgment
    public function acknowledge($id)
    {
        $review = PerformanceReview::findOrFail($id);
        $review->update(['status' => 'Acknowledged', 'acknowledged_at' => now()]);
        return response()->json(['success' => true, 'data' => $review], 200);
    }

    // List reviews by employee
    public function byEmployee($employeeId)
    {
        $reviews = PerformanceReview::where('employee_id', $employeeId)->with(['reviewCycle', 'manager', 'feedback'])->get();
        return response()->json(['success' => true, 'data' => $reviews], 200);
    }

    // List reviews by cycle
    public function byCycle($cycleId)
    {
        $reviews = PerformanceReview::where('review_cycle_id', $cycleId)->with(['employee', 'manager', 'feedback'])->get();
        return response()->json(['success' => true, 'data' => $reviews], 200);
    }
}
