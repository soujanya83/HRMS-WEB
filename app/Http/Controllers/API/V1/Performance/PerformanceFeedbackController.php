<?php

namespace App\Http\Controllers\API\V1\Performance;

use App\Http\Controllers\Controller;
use App\Models\Performance\PerformanceFeedback;
use Illuminate\Http\Request;

class PerformanceFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = PerformanceFeedback::with([
            'organization', 'giver', 'receiver', 'performanceReview', 'performanceGoal'
        ]);
        if ($request->organization_id) $query->where('organization_id', $request->organization_id);
        if ($request->receiver_employee_id) $query->where('receiver_employee_id', $request->receiver_employee_id);
        if ($request->performance_review_id) $query->where('performance_review_id', $request->performance_review_id);
        if ($request->performance_goal_id) $query->where('performance_goal_id', $request->performance_goal_id);
        if ($request->type) $query->where('type', $request->type);
        if ($request->visibility) $query->where('visibility', $request->visibility);
        $feedbacks = $query->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $feedbacks], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'giver_employee_id' => 'required|exists:employees,id',
            'receiver_employee_id' => 'required|exists:employees,id',
            'feedback_content' => 'required|string|max:2000',
            'type' => 'required|in:General,Goal,KPI,Review',
            'visibility' => 'required|in:Public,Private,ManagerOnly',
            'performance_review_id' => 'nullable|exists:performance_reviews,id',
            'performance_goal_id' => 'nullable|exists:performance_goals,id',
        ]);
        $feedback = PerformanceFeedback::create($validated);
        return response()->json(['success' => true, 'data' => $feedback], 201);
    }

    public function show($id)
    {
        $feedback = PerformanceFeedback::with([
            'organization', 'giver', 'receiver', 'performanceReview', 'performanceGoal'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $feedback], 200);
    }

    public function update(Request $request, $id)
    {
        $feedback = PerformanceFeedback::findOrFail($id);
        $validated = $request->validate([
            'feedback_content' => 'sometimes|string|max:2000',
            'type' => 'sometimes|in:General,Goal,KPI,Review,positive,constructive',
            'visibility' => 'sometimes|in:Public,Private,manager_only',
            'read_at' => 'nullable|date',
        ]);
        $feedback->update($validated);
        return response()->json(['success' => true, 'data' => $feedback], 200);
    }

    public function destroy($id)
    {
        $feedback = PerformanceFeedback::findOrFail($id);
        $feedback->delete();
        return response()->json(['success' => true, 'message' => 'Feedback deleted'], 200);
    }

    // Mark feedback as read
    public function markRead($id)
    {
        $feedback = PerformanceFeedback::findOrFail($id);
        $feedback->update(['read_at' => now()]);
        return response()->json(['success' => true, 'data' => $feedback], 200);
    }

    // Get feedback received by employee
    public function forReceiver($employeeId)
    {
        $feedbacks = PerformanceFeedback::where('receiver_employee_id', $employeeId)->get();
        return response()->json(['success' => true, 'data' => $feedbacks], 200);
    }
}
