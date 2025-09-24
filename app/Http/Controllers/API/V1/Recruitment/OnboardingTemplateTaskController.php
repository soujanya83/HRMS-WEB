<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\OnboardingTemplateTask;
use App\Models\Recruitment\OnboardingTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class OnboardingTemplateTaskController extends Controller
{
    /**
     * Display a listing of template tasks
     */
    public function index(): JsonResponse
    {
        try {
            $tasks = OnboardingTemplateTask::with(['template.organization'])
                ->orderBy('onboarding_template_id')
                ->orderBy('default_due_days', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Template tasks retrieved successfully',
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created template task
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'onboarding_template_id' => 'required|exists:onboarding_templates,id',
                'task_name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'default_due_days' => 'required|integer|min:1|max:365',
                'default_assigned_role' => 'required|in:hr,it,manager,admin,security,facilities,finance',
            ]);

            $task = OnboardingTemplateTask::create($validated);
            $task->load(['template']);

            return response()->json([
                'success' => true,
                'message' => 'Template task created successfully',
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
                'message' => 'Failed to create template task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified template task
     */
    public function show($id): JsonResponse
    {
        try {
            $task = OnboardingTemplateTask::with(['template.organization'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Template task retrieved successfully',
                'data' => $task
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template task not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified template task
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $task = OnboardingTemplateTask::findOrFail($id);

            $validated = $request->validate([
                'onboarding_template_id' => 'sometimes|exists:onboarding_templates,id',
                'task_name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:1000',
                'default_due_days' => 'sometimes|integer|min:1|max:365',
                'default_assigned_role' => 'sometimes|in:hr,it,manager,admin,security,facilities,finance',
            ]);

            $task->update($validated);
            $task->load(['template']);

            return response()->json([
                'success' => true,
                'message' => 'Template task updated successfully',
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
                'message' => 'Failed to update template task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified template task
     */
    public function destroy($id): JsonResponse
    {
        try {
            $task = OnboardingTemplateTask::findOrFail($id);
            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template task deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tasks by template
     */
    public function getByTemplate($templateId): JsonResponse
    {
        try {
            $template = OnboardingTemplate::findOrFail($templateId);
            
            $tasks = OnboardingTemplateTask::where('onboarding_template_id', $templateId)
                ->orderBy('default_due_days', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tasks for template retrieved successfully',
                'template' => $template->name,
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks for template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tasks by assigned role
     */
    public function getByRole($role): JsonResponse
    {
        try {
            $validRoles = ['hr', 'it', 'manager', 'admin', 'security', 'facilities', 'finance'];
            
            if (!in_array($role, $validRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role provided',
                    'valid_roles' => $validRoles
                ], 400);
            }

            $tasks = OnboardingTemplateTask::with(['template'])
                ->where('default_assigned_role', $role)
                ->orderBy('default_due_days', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Template tasks for role '{$role}' retrieved successfully",
                'data' => $tasks
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks by role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create tasks for a template
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'onboarding_template_id' => 'required|exists:onboarding_templates,id',
                'tasks' => 'required|array|min:1',
                'tasks.*.task_name' => 'required|string|max:255',
                'tasks.*.description' => 'required|string|max:1000',
                'tasks.*.default_due_days' => 'required|integer|min:1|max:365',
                'tasks.*.default_assigned_role' => 'required|in:hr,it,manager,admin,security,facilities,finance',
            ]);

            $createdTasks = [];
            foreach ($validated['tasks'] as $taskData) {
                $taskData['onboarding_template_id'] = $validated['onboarding_template_id'];
                $task = OnboardingTemplateTask::create($taskData);
                $createdTasks[] = $task;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdTasks) . ' template tasks created successfully',
                'data' => $createdTasks,
                'count' => count($createdTasks)
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
                'message' => 'Failed to bulk create template tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
