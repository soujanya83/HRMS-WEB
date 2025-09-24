<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\OnboardingTemplate;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class OnboardingTemplateController extends Controller
{
    /**
     * Display a listing of onboarding templates
     */
    public function index(): JsonResponse
    {
        try {
            $templates = OnboardingTemplate::with(['organization', 'tasks'])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding templates retrieved successfully',
                'data' => $templates
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve onboarding templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created onboarding template
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'name' => 'required|string|max:255|unique:onboarding_templates,name',
                'description' => 'required|string|max:1000',
            ]);

            $template = OnboardingTemplate::create($validated);
            $template->load(['organization']);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding template created successfully',
                'data' => $template
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
                'message' => 'Failed to create onboarding template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified onboarding template
     */
    public function show($id): JsonResponse
    {
        try {
            $template = OnboardingTemplate::with(['organization', 'tasks'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding template retrieved successfully',
                'data' => $template
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding template not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified onboarding template
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $template = OnboardingTemplate::findOrFail($id);

            $validated = $request->validate([
                'organization_id' => 'sometimes|exists:organizations,id',
                'name' => 'sometimes|string|max:255|unique:onboarding_templates,name,' . $id,
                'description' => 'sometimes|string|max:1000',
            ]);

            $template->update($validated);
            $template->load(['organization', 'tasks']);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding template updated successfully',
                'data' => $template
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
                'message' => 'Failed to update onboarding template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified onboarding template
     */
    public function destroy($id): JsonResponse
    {
        try {
            $template = OnboardingTemplate::findOrFail($id);
            
            // Check if template has tasks
            $taskCount = $template->tasks()->count();
            if ($taskCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete template with existing tasks',
                    'task_count' => $taskCount
                ], 400);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding template deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete onboarding template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get templates by organization
     */
    public function getByOrganization($organizationId): JsonResponse
    {
        try {
            $organization = Organization::findOrFail($organizationId);
            
            $templates = OnboardingTemplate::with(['tasks'])
                ->where('organization_id', $organizationId)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding templates for organization retrieved successfully',
                'organization' => $organization->name,
                'data' => $templates
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates for organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone/duplicate a template
     */
    public function clone(Request $request, $id): JsonResponse
    {
        try {
            $originalTemplate = OnboardingTemplate::with(['tasks'])->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:onboarding_templates,name',
                'organization_id' => 'sometimes|exists:organizations,id',
            ]);

            // Create new template
            $newTemplate = OnboardingTemplate::create([
                'organization_id' => $validated['organization_id'] ?? $originalTemplate->organization_id,
                'name' => $validated['name'],
                'description' => $originalTemplate->description . ' (Copy)',
            ]);

            // Clone all tasks from original template
            foreach ($originalTemplate->tasks as $task) {
                $newTemplate->tasks()->create([
                    'task_name' => $task->task_name,
                    'description' => $task->description,
                    'default_due_days' => $task->default_due_days,
                    'default_assigned_role' => $task->default_assigned_role,
                ]);
            }

            $newTemplate->load(['organization', 'tasks']);

            return response()->json([
                'success' => true,
                'message' => 'Template cloned successfully',
                'original_template' => $originalTemplate->name,
                'data' => $newTemplate
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
                'message' => 'Failed to clone template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
