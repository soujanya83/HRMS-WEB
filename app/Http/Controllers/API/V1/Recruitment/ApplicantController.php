<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\Applicant;
use App\Models\Recruitment\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Exception;

class ApplicantController extends Controller
{
    /**
     * Display a listing of applicants
     */
    public function index(): JsonResponse
    {
        try {
            $applicants = Applicant::with(['jobOpening', 'interviews', 'jobOffer', 'onboardingTasks'])
                ->orderBy('applied_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Applicants retrieved successfully',
                'data' => $applicants
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve applicants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created applicant
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'job_opening_id' => 'required|exists:job_openings,id',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:applicants,email',
                'phone' => 'required|string|max:20',
                'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120', // 5MB max
                'cover_letter' => 'nullable|string',
                'source' => 'required|in:website,linkedin,referral,job-board,social-media,direct-application,recruiter,other',
                'status' => 'required|in:new,in-review,shortlisted,interview-scheduled,interviewed,on-hold,rejected,hired,withdrawn',
                'applied_date' => 'required|date|before_or_equal:today',
            ]);

            // Handle resume upload
            if ($request->hasFile('resume')) {
                $resume = $request->file('resume');
                $resumePath = $resume->store('resumes', 'public');
                $validated['resume_url'] = Storage::url($resumePath);
            }

            // Check if job opening is still active
            $jobOpening = JobOpening::findOrFail($validated['job_opening_id']);
            if ($jobOpening->status !== 'open' || $jobOpening->closing_date < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job opening is no longer accepting applications',
                    'job_status' => $jobOpening->status,
                    'closing_date' => $jobOpening->closing_date
                ], 400);
            }

            $applicant = Applicant::create($validated);
            $applicant->load(['jobOpening']);

            return response()->json([
                'success' => true,
                'message' => 'Applicant created successfully',
                'data' => $applicant
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
                'message' => 'Failed to create applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified applicant
     */
    public function show($id): JsonResponse
    {
        try {
            $applicant = Applicant::with(['jobOpening', 'interviews', 'jobOffer', 'onboardingTasks'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Applicant retrieved successfully',
                'data' => $applicant
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Applicant not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified applicant
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($id);

            $validated = $request->validate([
                'organization_id' => 'sometimes|exists:organizations,id',
                'job_opening_id' => 'sometimes|exists:job_openings,id',
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:applicants,email,' . $id,
                'phone' => 'sometimes|string|max:20',
                'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'cover_letter' => 'sometimes|string',
                'source' => 'sometimes|in:website,linkedin,referral,job-board,social-media,direct-application,recruiter,other',
                'status' => 'sometimes|in:new,in-review,shortlisted,interview-scheduled,interviewed,on-hold,rejected,hired,withdrawn',
                'applied_date' => 'sometimes|date|before_or_equal:today',
            ]);

            // Handle resume upload if provided
            if ($request->hasFile('resume')) {
                // Delete old resume if exists
                if ($applicant->resume_url) {
                    $oldPath = str_replace('/storage/', '', $applicant->resume_url);
                    Storage::disk('public')->delete($oldPath);
                }
                
                $resume = $request->file('resume');
                $resumePath = $resume->store('resumes', 'public');
                $validated['resume_url'] = Storage::url($resumePath);
            }

            $applicant->update($validated);
            $applicant->load(['jobOpening', 'interviews', 'jobOffer']);

            return response()->json([
                'success' => true,
                'message' => 'Applicant updated successfully',
                'data' => $applicant
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
                'message' => 'Failed to update applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified applicant
     */
    public function destroy($id): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($id);
            
            // Check if applicant has interviews, job offers, or onboarding tasks
            $hasInterviews = $applicant->interviews()->count() > 0;
            $hasJobOffer = $applicant->jobOffer !== null;
            $hasOnboardingTasks = $applicant->onboardingTasks()->count() > 0;

            if ($hasInterviews || $hasJobOffer || $hasOnboardingTasks) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete applicant with existing interviews, job offers, or onboarding tasks',
                    'has_interviews' => $hasInterviews,
                    'has_job_offer' => $hasJobOffer,
                    'has_onboarding_tasks' => $hasOnboardingTasks
                ], 400);
            }

            // Delete resume file if exists
            if ($applicant->resume_url) {
                $resumePath = str_replace('/storage/', '', $applicant->resume_url);
                Storage::disk('public')->delete($resumePath);
            }

            $applicant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Applicant deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete applicant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get applicants by job opening
     */
    public function getByJobOpening($jobOpeningId): JsonResponse
    {
        try {
            $jobOpening = JobOpening::findOrFail($jobOpeningId);
            
            $applicants = Applicant::with(['interviews', 'jobOffer'])
                ->where('job_opening_id', $jobOpeningId)
                ->orderBy('applied_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Applicants for job opening retrieved successfully',
                'job_opening' => $jobOpening->title,
                'data' => $applicants
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve applicants for job opening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get applicants by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['new', 'in-review', 'shortlisted', 'interview-scheduled', 'interviewed', 'on-hold', 'rejected', 'hired', 'withdrawn'];
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided',
                    'valid_statuses' => $validStatuses
                ], 400);
            }

            $applicants = Applicant::with(['jobOpening', 'interviews', 'jobOffer'])
                ->where('status', $status)
                ->orderBy('applied_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Applicants with status '{$status}' retrieved successfully",
                'data' => $applicants
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve applicants by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update applicant status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:new,in-review,shortlisted,interview-scheduled,interviewed,on-hold,rejected,hired,withdrawn',
                'notes' => 'nullable|string|max:1000'
            ]);

            $applicant->update(['status' => $validated['status']]);
            $applicant->load(['jobOpening']);

            return response()->json([
                'success' => true,
                'message' => 'Applicant status updated successfully',
                'data' => $applicant,
                'notes' => $validated['notes'] ?? null
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
                'message' => 'Failed to update applicant status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download applicant resume
     */
    public function downloadResume($id): JsonResponse
    {
        try {
            $applicant = Applicant::findOrFail($id);

            if (!$applicant->resume_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No resume found for this applicant'
                ], 404);
            }

            $resumePath = str_replace('/storage/', '', $applicant->resume_url);
            
            if (!Storage::disk('public')->exists($resumePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resume file not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Resume download link generated',
                'download_url' => $applicant->resume_url,
                'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate resume download link',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
