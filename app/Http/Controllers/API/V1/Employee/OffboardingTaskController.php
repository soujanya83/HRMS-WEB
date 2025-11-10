<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\OffboardingTask;
use App\Models\Employee\EmployeeExit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OffboardingTaskController extends Controller
{
    // List tasks (optionally by employee_exit_id or assigned_to)
    public function index(Request $request)
    {
        $query = OffboardingTask::with(['employeeExit', 'assignedUser']);
        if ($request->employee_exit_id) $query->where('employee_exit_id', $request->employee_exit_id);
        if ($request->assigned_to) $query->where('assigned_to', $request->assigned_to);
        $tasks = $query->orderBy('due_date')->get();
        return response()->json(['success' => true, 'data' => $tasks], 200);
    }

    // Create task
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_exit_id' => 'required|exists:employee_exits,id',
            'task_name' => 'required|string|max:255',
            'due_date' => 'required|date',
            'assigned_to' => 'required|exists:users,id',
            'status' => 'sometimes|in:Pending,Completed,Overdue',
        ]);
        $validated['status'] = $validated['status'] ?? 'Pending';
        $task = OffboardingTask::create($validated);
        return response()->json(['success' => true, 'data' => $task], 201);
    }

    // Show task
    public function show($id)
    {
        $task = OffboardingTask::with(['employeeExit', 'assignedUser'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $task], 200);
    }

    // Update task info
    public function update(Request $request, $id)
    {
        $task = OffboardingTask::findOrFail($id);
        $validated = $request->validate([
            'task_name' => 'sometimes|string|max:255',
            'due_date' => 'sometimes|date',
            'completed_at' => 'nullable|date',
            'status' => 'sometimes|in:Pending,Completed,Overdue',
            'assigned_to' => 'sometimes|exists:users,id',
        ]);
        // Update completed_at when completed
        if (isset($validated['status']) && $validated['status'] === 'Completed' && !$task->completed_at) {
            $validated['completed_at'] = now();
        }
        $task->update($validated);
        return response()->json(['success' => true, 'data' => $task], 200);
    }

    // Delete
    public function destroy($id)
    {
        $task = OffboardingTask::findOrFail($id);
        $task->delete();
        return response()->json(['success' => true, 'message' => 'Offboarding task deleted'], 200);
    }

    // Mark as completed
    public function markCompleted($id)
    {
        $task = OffboardingTask::findOrFail($id);
        $task->update(['status' => 'Completed', 'completed_at' => now()]);
        return response()->json(['success' => true, 'data' => $task], 200);
    }

    // Get overdue tasks and update
    public function overdue()
    {
        $tasks = OffboardingTask::where('due_date', '<', now())->where('status', 'Pending')->get();
        OffboardingTask::where('due_date', '<', now())->where('status', 'Pending')->update(['status' => 'Overdue']);
        return response()->json(['success' => true, 'data' => $tasks, 'count' => $tasks->count()], 200);
    }
}
