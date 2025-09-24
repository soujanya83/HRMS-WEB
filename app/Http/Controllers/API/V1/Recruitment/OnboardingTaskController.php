<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\OnboardingTask;
use App\Models\Recruitment\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class OnboardingTaskController extends Controller
{
    /**
     * Display a listing of onboarding tasks
     */
    public function index(): JsonResponse
    {
        try {
            $tasks = OnboardingTask::with(['applicant.jobOpening'])
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding tasks retrieved successfully',
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve onboarding tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created onboarding task
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'applicant_id' => 'required|exists:applicants,id',
                'task_name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'due_date' => 'required|date|after_or_equal:today',
                'status' => 'sometimes|in:pending,completed,overdue',
            ]);

            // Check if applicant is hired
            $applicant = Applicant::findOrFail($validated['applicant_id']);
            if ($applicant->status !== 'Hired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only create onboarding tasks for hired applicants',
                    'current_status' => $applicant->status
                ], 400);
            }

            // Set default status
            $validated['status'] = $validated['status'] ?? 'pending';

            $task = OnboardingTask::create($validated);
            $task->load(['applicant']);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding task created successfully',
                'data' => $task
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create onboarding task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified onboarding task
     */
    public function show($id): JsonResponse
    {
        try {
            $task = OnboardingTask::with(['applicant.jobOpening'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding task retrieved successfully',
                'data' => $task
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding task not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified onboarding task
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $task = OnboardingTask::findOrFail($id);

            $validated = $request->validate([
                'applicant_id' => 'sometimes|exists:applicants,id',
                'task_name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:1000',
                'due_date' => 'sometimes|date|after_or_equal:today',
                'status' => 'sometimes|in:pending,completed,overdue',
            ]);

            // Handle completion timestamp
            if (isset($validated['status'])) {
                if ($validated['status'] === 'completed' && $task->status !== 'completed') {
                    $validated['completed_at'] = now();
                } elseif ($validated['status'] !== 'completed') {
                    $validated['completed_at'] = null;
                }
            }

            $task->update($validated);
            $task->load(['applicant']);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding task updated successfully',
                'data' => $task
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update onboarding task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified onboarding task
     */
    public function destroy($id): JsonResponse
    {
        try {
            $task = OnboardingTask::findOrFail($id);
            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding task deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete onboarding task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get onboarding tasks by applicant
     */
    public function getByApplicant($applicantId): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($applicantId);
            
            $tasks = OnboardingTask::where('applicant_id', $applicantId)
                ->orderBy('due_date', 'asc')
                ->get();

            $stats = [
                'total' => $tasks->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'pending' => $tasks->where('status', 'pending')->count(),
                'overdue' => $tasks->where('status', 'overdue')->count(),
                'completion_percentage' => $tasks->count() > 0 ? 
                    round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Onboarding tasks for applicant retrieved successfully',
                'applicant' => $applicant->first_name . ' ' . $applicant->last_name,
                'stats' => $stats,
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve onboarding tasks for applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tasks by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['pending', 'completed', 'overdue'];
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided',
                    'valid_statuses' => $validStatuses
                ], 400);
            }

            $tasks = OnboardingTask::with(['applicant'])
                ->where('status', $status)
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Onboarding tasks with status '{$status}' retrieved successfully",
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve onboarding tasks by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark task as completed
     */
    public function markCompleted($id): JsonResponse
    {
        try {
            $task = OnboardingTask::findOrFail($id);
            
            $task->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            $task->load(['applicant']);

            return response()->json([
                'success' => true,
                'message' => 'Task marked as completed',
                'data' => $task
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark task as completed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue tasks and update their status
     */
    public function getOverdue(): JsonResponse
    {
        try {
            $overdueTasks = OnboardingTask::with(['applicant'])
                ->where('due_date', '<', now())
                ->where('status', 'pending')
                ->get();

            // Update status to overdue
            OnboardingTask::where('due_date', '<', now())
                ->where('status', 'pending')
                ->update(['status' => 'overdue']);

            return response()->json([
                'success' => true,
                'message' => 'Overdue tasks retrieved and updated',
                'data' => $overdueTasks,
                'count' => $overdueTasks->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overdue tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming tasks (due within next 7 days)
     */
    public function getUpcoming(): JsonResponse
    {
        try {
            $upcomingTasks = OnboardingTask::with(['applicant'])
                ->where('status', 'pending')
                ->whereBetween('due_date', [now(), now()->addDays(7)])
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Upcoming tasks retrieved successfully',
                'data' => $upcomingTasks,
                'count' => $upcomingTasks->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
