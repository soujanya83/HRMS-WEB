<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\Interview;
use App\Models\Recruitment\Applicant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;


class InterviewController extends Controller
{
    /**
     * Display a listing of interviews
     */
    public function index(): JsonResponse
    {
        try {
            $interviews = Interview::with(['applicant.jobOpening', 'interviewers'])
                ->orderBy('scheduled_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Interviews retrieved successfully',
                'data' => $interviews
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created interview
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'applicant_id' => 'required|exists:applicants,id',
                'interview_type' => 'required|in:phone-screen,technical,hr-round,behavioral,panel,final,cultural-fit,case-study',
                'scheduled_at' => 'required|date|after:now',
                'location' => 'required|string|max:500',
                'status' => 'sometimes|in:scheduled,completed,cancelled,rescheduled',
                'feedback' => 'nullable|string|max:2000',
                'result' => 'nullable|in:progress,hold,reject',
                'interviewer_ids' => 'required|array|min:1',
                'interviewer_ids.*' => 'exists:users,id',
            ]);

            // Check if applicant exists and is in appropriate status
            $applicant = Applicant::findOrFail($validated['applicant_id']);
            $allowedStatuses = ['Interviewing','shortlisted', 'interview-scheduled', 'interviewed', 'in-review'];
            
            if (!in_array($applicant->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Applicant status does not allow interview scheduling',
                    'current_status' => $applicant->status,
                    'allowed_statuses' => $allowedStatuses
                ], 400);
            }

            // Set default status if not provided
            $validated['status'] = $validated['status'] ?? 'scheduled';

            // Create interview
            $interview = Interview::create([
                'applicant_id' => $validated['applicant_id'],
                'interview_type' => $validated['interview_type'],
                'scheduled_at' => $validated['scheduled_at'],
                'location' => $validated['location'],
                'status' => $validated['status'],
                'feedback' => $validated['feedback'],
                'result' => $validated['result'],
            ]);

            // Attach interviewers using pivot table
            $interview->interviewers()->attach($validated['interviewer_ids']);

            // Update applicant status to interview-scheduled if not already
            if ($applicant->status !== 'interview-scheduled') {
                $applicant->update(['status' => 'interview-scheduled']);
            }

            // Load relationships for response
            $interview->load(['applicant.jobOpening', 'interviewers']);

            return response()->json([
                'success' => true,
                'message' => 'Interview scheduled successfully',
                'data' => $interview
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
                'message' => 'Failed to schedule interview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified interview
     */
    public function show($id): JsonResponse
    {
        try {
            $interview = Interview::with(['applicant.jobOpening', 'interviewers'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Interview retrieved successfully',
                'data' => $interview
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified interview
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $interview = Interview::findOrFail($id);

            $validated = $request->validate([
                'applicant_id' => 'sometimes|exists:applicants,id',
                'interview_type' => 'sometimes|in:phone-screen,technical,hr-round,behavioral,panel,final,cultural-fit,case-study',
                'scheduled_at' => 'sometimes|date|after:now',
                'location' => 'sometimes|string|max:500',
                'status' => 'sometimes|in:scheduled,completed,cancelled,rescheduled',
                'feedback' => 'nullable|string|max:2000',
                'result' => 'nullable|in:progress,hold,reject',
                'interviewer_ids' => 'sometimes|array|min:1',
                'interviewer_ids.*' => 'exists:users,id',
            ]);

            // Update interview fields
            $interview->update(Arr::except($validated, ['interviewer_ids']));

            // Update interviewers if provided
            if (isset($validated['interviewer_ids'])) {
                $interview->interviewers()->sync($validated['interviewer_ids']);
            }

            // If status changed to completed, update applicant status
            if (isset($validated['status']) && $validated['status'] === 'completed') {
                $interview->applicant()->update(['status' => 'interviewed']);
            }

            // Load relationships for response
            $interview->load(['applicant.jobOpening', 'interviewers']);

            return response()->json([
                'success' => true,
                'message' => 'Interview updated successfully',
                'data' => $interview
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
                'message' => 'Failed to update interview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified interview
     */
    public function destroy($id): JsonResponse
    {
        try {
            $interview = Interview::findOrFail($id);
            
            // Check if interview can be deleted (only scheduled interviews)
            if ($interview->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed interviews',
                    'current_status' => $interview->status
                ], 400);
            }

            // Detach all interviewers
            $interview->interviewers()->detach();
            
            // Delete interview
            $interview->delete();

            return response()->json([
                'success' => true,
                'message' => 'Interview deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interviews by applicant
     */
    public function getByApplicant($applicantId): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($applicantId);
            
            $interviews = Interview::with(['interviewers'])
                ->where('applicant_id', $applicantId)
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Interviews for applicant retrieved successfully',
                'applicant' => $applicant->first_name . ' ' . $applicant->last_name,
                'data' => $interviews
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews for applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interviews by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['scheduled', 'completed', 'cancelled', 'rescheduled'];
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided',
                    'valid_statuses' => $validStatuses
                ], 400);
            }

            $interviews = Interview::with(['applicant.jobOpening', 'interviewers'])
                ->where('status', $status)
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Interviews with status '{$status}' retrieved successfully",
                'data' => $interviews
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interviews by interviewer
     */
    public function getByInterviewer($interviewerId): JsonResponse
    {
        try {
            $interviewer = User::findOrFail($interviewerId);
            
            $interviews = Interview::with(['applicant.jobOpening'])
                ->whereHas('interviewers', function ($query) use ($interviewerId) {
                    $query->where('user_id', $interviewerId);
                })
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Interviews for interviewer retrieved successfully',
                'interviewer' => $interviewer->name,
                'data' => $interviews
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews for interviewer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update interview status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $interview = Interview::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:scheduled,completed,cancelled,rescheduled',
                'feedback' => 'nullable|string|max:2000',
                'result' => 'nullable|in:progress,hold,reject',
            ]);

            $interview->update($validated);

            // Update applicant status based on interview status
            if ($validated['status'] === 'completed') {
                $interview->applicant()->update(['status' => 'interviewed']);
            } elseif ($validated['status'] === 'cancelled') {
                // Check if applicant has other scheduled interviews
                $otherInterviews = Interview::where('applicant_id', $interview->applicant_id)
                    ->where('id', '!=', $interview->id)
                    ->where('status', 'scheduled')
                    ->count();
                
                if ($otherInterviews === 0) {
                    $interview->applicant()->update(['status' => 'shortlisted']);
                }
            }

            $interview->load(['applicant.jobOpening', 'interviewers']);

            return response()->json([
                'success' => true,
                'message' => 'Interview status updated successfully',
                'data' => $interview
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
                'message' => 'Failed to update interview status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add feedback to interview
     */
    public function addFeedback(Request $request, $id): JsonResponse
    {
        try {
            $interview = Interview::findOrFail($id);

            $validated = $request->validate([
                'feedback' => 'required|string|max:2000',
                'result' => 'required|in:progress,hold,reject',
            ]);

            $interview->update($validated);
            $interview->load(['applicant.jobOpening', 'interviewers']);

            return response()->json([
                'success' => true,
                'message' => 'Interview feedback added successfully',
                'data' => $interview
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
                'message' => 'Failed to add interview feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming interviews (next 7 days)
     */
    public function getUpcoming(): JsonResponse
    {
        try {
            $interviews = Interview::with(['applicant.jobOpening', 'interviewers'])
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
                ->orderBy('scheduled_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Upcoming interviews retrieved successfully',
                'data' => $interviews
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming interviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
