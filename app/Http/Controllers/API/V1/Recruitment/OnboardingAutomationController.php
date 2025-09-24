<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\OnboardingTask;
use App\Models\Recruitment\OnboardingTemplate;
use App\Models\Recruitment\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class OnboardingAutomationController extends Controller
{
    /**
     * Generate onboarding tasks from template for hired applicant
     */
    public function generateTasksFromTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'applicant_id' => 'required|exists:applicants,id',
                'template_id' => 'required|exists:onboarding_templates,id',
                'start_date' => 'sometimes|date|after_or_equal:today',
            ]);

            // Check if applicant is hired
            $applicant = Applicant::with(['jobOpening'])->findOrFail($validated['applicant_id']);
            if ($applicant->status !== 'Hired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only generate tasks for hired applicants',
                    'current_status' => $applicant->status
                ], 400);
            }

            // Check if applicant already has onboarding tasks
            $existingTasks = OnboardingTask::where('applicant_id', $applicant->id)->count();
            if ($existingTasks > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Applicant already has onboarding tasks',
                    'existing_task_count' => $existingTasks
                ], 400);
            }

            // Get template with tasks
            $template = OnboardingTemplate::with(['tasks'])->findOrFail($validated['template_id']);
            
            if ($template->tasks->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template has no tasks to generate',
                    'template_name' => $template->name
                ], 400);
            }

            $startDate = isset($validated['start_date']) ? 
                Carbon::parse($validated['start_date']) : 
                now();

            $createdTasks = [];

            foreach ($template->tasks as $templateTask) {
                $dueDate = $startDate->copy()->addDays($templateTask->default_due_days);
                
                $task = OnboardingTask::create([
                    'applicant_id' => $applicant->id,
                    'task_name' => $templateTask->task_name,
                    'description' => $templateTask->description,
                    'due_date' => $dueDate,
                    'status' => 'pending'
                ]);

                $createdTasks[] = $task;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdTasks) . ' onboarding tasks generated successfully',
                'applicant' => $applicant->first_name . ' ' . $applicant->last_name,
                'template_used' => $template->name,
                'start_date' => $startDate->format('Y-m-d'),
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
                'message' => 'Failed to generate onboarding tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-generate tasks for all newly hired applicants
     */
    public function autoGenerateForNewHires(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'template_id' => 'sometimes|exists:onboarding_templates,id',
            ]);

            // Get newly hired applicants without onboarding tasks
            $newHires = Applicant::with(['jobOpening'])
                ->where('status', 'Hired')
                ->whereDoesntHave('onboardingTasks')
                ->get();

            if ($newHires->count() === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No new hires found without onboarding tasks',
                    'data' => [],
                    'count' => 0
                ], 200);
            }

            $results = [];
            $totalTasksCreated = 0;

            foreach ($newHires as $applicant) {
                try {
                    // Use provided template or try to find default template for organization
                    $templateId = $validated['template_id'] ?? $this->getDefaultTemplate($applicant->organization_id);
                    
                    if (!$templateId) {
                        $results[] = [
                            'applicant_id' => $applicant->id,
                            'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name,
                            'status' => 'skipped',
                            'reason' => 'No template available'
                        ];
                        continue;
                    }

                    $template = OnboardingTemplate::with(['tasks'])->find($templateId);
                    if (!$template || $template->tasks->count() === 0) {
                        $results[] = [
                            'applicant_id' => $applicant->id,
                            'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name,
                            'status' => 'skipped',
                            'reason' => 'Template has no tasks'
                        ];
                        continue;
                    }

                    $tasksCreated = 0;
                    foreach ($template->tasks as $templateTask) {
                        $dueDate = now()->addDays($templateTask->default_due_days);
                        
                        OnboardingTask::create([
                            'applicant_id' => $applicant->id,
                            'task_name' => $templateTask->task_name,
                            'description' => $templateTask->description,
                            'due_date' => $dueDate,
                            'status' => 'pending'
                        ]);

                        $tasksCreated++;
                    }

                    $results[] = [
                        'applicant_id' => $applicant->id,
                        'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name,
                        'status' => 'success',
                        'tasks_created' => $tasksCreated,
                        'template_used' => $template->name
                    ];

                    $totalTasksCreated += $tasksCreated;

                } catch (Exception $e) {
                    $results[] = [
                        'applicant_id' => $applicant->id,
                        'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Processed {$newHires->count()} new hires, created {$totalTasksCreated} tasks",
                'total_processed' => $newHires->count(),
                'total_tasks_created' => $totalTasksCreated,
                'data' => $results
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
                'message' => 'Failed to auto-generate tasks for new hires',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get onboarding progress dashboard
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $stats = [
                'total_active_onboarding' => OnboardingTask::whereHas('applicant', function($q) {
                    $q->where('status', 'hired');
                })->whereIn('status', ['pending', 'overdue'])->count(),
                
                'completed_this_week' => OnboardingTask::where('status', 'completed')
                    ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                    
                'overdue_tasks' => OnboardingTask::where('status', 'overdue')->count(),
                
                'pending_tasks' => OnboardingTask::where('status', 'pending')->count(),
                
                'new_hires_without_tasks' => Applicant::where('status', 'hired')
                    ->whereDoesntHave('onboardingTasks')
                    ->count(),
                    
                'completion_rate' => $this->calculateCompletionRate(),
            ];

            $recentActivity = OnboardingTask::with(['applicant'])
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->limit(10)
                ->get();

            $upcomingDeadlines = OnboardingTask::with(['applicant'])
                ->where('status', 'pending')
                ->whereBetween('due_date', [now(), now()->addDays(7)])
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Onboarding dashboard data retrieved successfully',
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'upcoming_deadlines' => $upcomingDeadlines
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get default template for organization
     */
    private function getDefaultTemplate($organizationId)
    {
        $template = OnboardingTemplate::where('organization_id', $organizationId)
            ->orderBy('created_at', 'asc')
            ->first();
        
        return $template ? $template->id : null;
    }

    /**
     * Helper method to calculate completion rate
     */
    private function calculateCompletionRate()
    {
        $total = OnboardingTask::count();
        if ($total === 0) return 0;
        
        $completed = OnboardingTask::where('status', 'completed')->count();
        return round(($completed / $total) * 100, 2);
    }
}
