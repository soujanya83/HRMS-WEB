<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
      public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
                'assigned_to' => 'nullable|exists:employees,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'progress_percent' => 'nullable|integer|min:0|max:100',
                'status' => 'nullable|in:not_started,in_progress,completed,on_hold',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $employee = Employee::where('user_id',Auth::user()->id)->first();
            $data['created_by'] = $employee->id;

            $task = Task::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Task created successfully.',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Get all tasks
    public function index(): JsonResponse
    {
        try {
            $tasks = Task::with([
                'project:id,name',
                'assignedTo:id,first_name,last_name,employee_code',
                'creator:id,first_name,last_name,employee_code'
            ])->latest()->get();

            return response()->json([
                'status' => true,
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Get a single task
    public function show($id): JsonResponse
    {
        try {
            $task = Task::with([
                'project:id,name',
                'assignedTo:id,first_name,last_name,employee_code',
                'creator:id,first_name,last_name,employee_code'
            ])->find($id);

            if (!$task) {
                return response()->json([
                    'status' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Update task
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'status' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'assigned_to' => 'nullable|exists:employees,id',
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'progress_percent' => 'nullable|integer|min:0|max:100',
                'status' => 'nullable|in:not_started,in_progress,completed,on_hold',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $task->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Task updated successfully.',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Delete task
    public function destroy($id): JsonResponse
    {
        try {
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'status' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            $task->delete();

            return response()->json([
                'status' => true,
                'message' => 'Task deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
