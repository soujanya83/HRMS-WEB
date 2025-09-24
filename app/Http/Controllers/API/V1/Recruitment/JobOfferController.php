<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\JobOffer;
use App\Models\Recruitment\Applicant;
use App\Models\Recruitment\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class JobOfferController extends Controller
{
    /**
     * Display a listing of job offers
     */
    public function index(): JsonResponse
    {
        try {
            $jobOffers = JobOffer::with(['applicant', 'jobOpening'])
                ->orderBy('offer_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Job offers retrieved successfully',
                'data' => $jobOffers
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created job offer
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'applicant_id' => 'required|exists:applicants,id|unique:job_offers,applicant_id',
                'job_opening_id' => 'required|exists:job_openings,id',
                'offer_date' => 'required|date|before_or_equal:today',
                'expiry_date' => 'required|date|after:offer_date',
                'salary_offered' => 'required|numeric|min:0|max:99999999.99',
                'joining_date' => 'required|date|after:offer_date',
                'status' => 'required|in:sent,pending,accepted,rejected,withdrawn,expired',
                'offer_letter' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            ]);

            // Check if applicant is eligible for job offer
            $applicant = Applicant::findOrFail($validated['applicant_id']);
            $eligibleStatuses = ['interviewed', 'shortlisted', 'in-review'];
            
            if (!in_array($applicant->status, $eligibleStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Applicant status does not allow job offer creation',
                    'current_status' => $applicant->status,
                    'eligible_statuses' => $eligibleStatuses
                ], 400);
            }

            // Validate applicant belongs to the job opening
            if ($applicant->job_opening_id != $validated['job_opening_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Applicant does not belong to the specified job opening',
                    'applicant_job_opening' => $applicant->job_opening_id,
                    'provided_job_opening' => $validated['job_opening_id']
                ], 400);
            }

            // Check if job opening is still active
            $jobOpening = JobOpening::findOrFail($validated['job_opening_id']);
            if ($jobOpening->status !== 'Open') {
                return response()->json([
                    'success' => false,
                    'message' => 'Job opening is not active for offer creation',
                    'job_opening_status' => $jobOpening->status
                ], 400);
            }

            // Handle offer letter upload
            if ($request->hasFile('offer_letter')) {
                $offerLetter = $request->file('offer_letter');
                $fileName = 'offer_letter_' . $validated['applicant_id'] . '_' . time() . '.' . $offerLetter->getClientOriginalExtension();
                $offerLetterPath = $offerLetter->storeAs('offer-letters', $fileName, 'public');
                $validated['offer_letter_url'] = Storage::url($offerLetterPath);
            }

            // Create job offer
            $jobOffer = JobOffer::create($validated);

            // Update applicant status based on offer status
            if ($validated['status'] === 'sent' || $validated['status'] === 'pending') {
                $applicant->update(['status' => 'in-review']);
            }

            // Load relationships for response
            $jobOffer->load(['applicant', 'jobOpening']);

            return response()->json([
                'success' => true,
                'message' => 'Job offer created successfully',
                'data' => $jobOffer
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
                'message' => 'Failed to create job offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified job offer
     */
    public function show($id): JsonResponse
    {
        try {
            $jobOffer = JobOffer::with(['applicant.jobOpening', 'jobOpening'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Job offer retrieved successfully',
                'data' => $jobOffer
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job offer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified job offer
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $jobOffer = JobOffer::findOrFail($id);

            // Prevent updates if offer is accepted or expired
            if (in_array($jobOffer->status, ['accepted', 'expired'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update job offer with status: ' . $jobOffer->status,
                    'current_status' => $jobOffer->status
                ], 400);
            }

            $validated = $request->validate([
                'applicant_id' => 'sometimes|exists:applicants,id|unique:job_offers,applicant_id,' . $id,
                'job_opening_id' => 'sometimes|exists:job_openings,id',
                'offer_date' => 'sometimes|date|before_or_equal:today',
                'expiry_date' => 'sometimes|date|after:offer_date',
                'salary_offered' => 'sometimes|numeric|min:0|max:99999999.99',
                'joining_date' => 'sometimes|date|after:offer_date',
                'status' => 'sometimes|in:sent,pending,accepted,rejected,withdrawn,expired',
                'offer_letter' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            ]);

            // Handle offer letter upload if provided
            if ($request->hasFile('offer_letter')) {
                // Delete old offer letter if exists
                if ($jobOffer->offer_letter_url) {
                    $oldPath = str_replace('/storage/', '', $jobOffer->offer_letter_url);
                    Storage::disk('public')->delete($oldPath);
                }
                
                $offerLetter = $request->file('offer_letter');
                $fileName = 'offer_letter_' . $jobOffer->applicant_id . '_' . time() . '.' . $offerLetter->getClientOriginalExtension();
                $offerLetterPath = $offerLetter->storeAs('offer-letters', $fileName, 'public');
                $validated['offer_letter_url'] = Storage::url($offerLetterPath);
            }

            $jobOffer->update($validated);

            // Update applicant status based on offer status changes
            if (isset($validated['status'])) {
                $this->updateApplicantStatus($jobOffer->applicant, $validated['status']);
            }

            $jobOffer->load(['applicant', 'jobOpening']);

            return response()->json([
                'success' => true,
                'message' => 'Job offer updated successfully',
                'data' => $jobOffer
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
                'message' => 'Failed to update job offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified job offer
     */
    public function destroy($id): JsonResponse
    {
        try {
            $jobOffer = JobOffer::findOrFail($id);
            
            // Prevent deletion if offer is accepted
            if ($jobOffer->status === 'accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete accepted job offer',
                    'current_status' => $jobOffer->status
                ], 400);
            }

            // Delete offer letter file if exists
            if ($jobOffer->offer_letter_url) {
                $offerLetterPath = str_replace('/storage/', '', $jobOffer->offer_letter_url);
                Storage::disk('public')->delete($offerLetterPath);
            }

            // Reset applicant status if needed
            $this->updateApplicantStatus($jobOffer->applicant, 'withdrew_offer');

            $jobOffer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Job offer deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job offers by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['sent', 'pending', 'accepted', 'rejected', 'withdrawn', 'expired'];
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided',
                    'valid_statuses' => $validStatuses
                ], 400);
            }

            $jobOffers = JobOffer::with(['applicant', 'jobOpening'])
                ->where('status', $status)
                ->orderBy('offer_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Job offers with status '{$status}' retrieved successfully",
                'data' => $jobOffers
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job offers by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job offers by job opening
     */
    public function getByJobOpening($jobOpeningId): JsonResponse
    {
        try {
            $jobOpening = JobOpening::findOrFail($jobOpeningId);
            
            $jobOffers = JobOffer::with(['applicant'])
                ->where('job_opening_id', $jobOpeningId)
                ->orderBy('offer_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Job offers for job opening retrieved successfully',
                'job_opening' => $jobOpening->title,
                'data' => $jobOffers
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job offers for job opening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update job offer status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $jobOffer = JobOffer::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:sent,pending,accepted,rejected,withdrawn,expired',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Prevent status changes from accepted
            if ($jobOffer->status === 'accepted' && $validated['status'] !== 'accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change status from accepted',
                    'current_status' => $jobOffer->status
                ], 400);
            }

            $oldStatus = $jobOffer->status;
            $jobOffer->update(['status' => $validated['status']]);

            // Update applicant status based on offer status
            $this->updateApplicantStatus($jobOffer->applicant, $validated['status']);

            $jobOffer->load(['applicant', 'jobOpening']);

            return response()->json([
                'success' => true,
                'message' => 'Job offer status updated successfully',
                'data' => $jobOffer,
                'status_change' => [
                    'from' => $oldStatus,
                    'to' => $validated['status']
                ],
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
                'message' => 'Failed to update job offer status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download offer letter
     */
    public function downloadOfferLetter($id): JsonResponse
    {
        try {
            $jobOffer = JobOffer::with(['applicant'])->findOrFail($id);

            if (!$jobOffer->offer_letter_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No offer letter found for this job offer'
                ], 404);
            }

            $offerLetterPath = str_replace('/storage/', '', $jobOffer->offer_letter_url);
            
            if (!Storage::disk('public')->exists($offerLetterPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer letter file not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Offer letter download link generated',
                'download_url' => $jobOffer->offer_letter_url,
                'applicant_name' => $jobOffer->applicant->first_name . ' ' . $jobOffer->applicant->last_name,
                'job_offer_id' => $jobOffer->id
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate offer letter download link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expired job offers
     */
    public function getExpired(): JsonResponse
    {
        try {
            $expiredOffers = JobOffer::with(['applicant', 'jobOpening'])
                ->where('expiry_date', '<', now())
                ->where('status', '!=', 'accepted')
                ->where('status', '!=', 'expired')
                ->get();

            // Update status to expired
            foreach ($expiredOffers as $offer) {
                $offer->update(['status' => 'expired']);
                $this->updateApplicantStatus($offer->applicant, 'expired');
            }

            return response()->json([
                'success' => true,
                'message' => 'Expired job offers retrieved and updated',
                'data' => $expiredOffers,
                'count' => $expiredOffers->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expired job offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending offers (expiring soon)
     */
    public function getPendingOffers(): JsonResponse
    {
        try {
            $pendingOffers = JobOffer::with(['applicant', 'jobOpening'])
                ->whereIn('status', ['sent', 'pending'])
                ->where('expiry_date', '>=', now())
                ->where('expiry_date', '<=', now()->addDays(7))
                ->orderBy('expiry_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending job offers (expiring within 7 days) retrieved successfully',
                'data' => $pendingOffers,
                'count' => $pendingOffers->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending job offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to update applicant status based on offer status
     */
    private function updateApplicantStatus($applicant, $offerStatus): void
    {
        $statusMap = [
            'sent' => 'in-review',
            'pending' => 'in-review',
            'accepted' => 'hired',
            'rejected' => 'rejected',
            'withdrawn' => 'interviewed',
            'expired' => 'rejected',
            'withdrew_offer' => 'interviewed'
        ];

        if (isset($statusMap[$offerStatus])) {
            $applicant->update(['status' => $statusMap[$offerStatus]]);
        }
    }
}
