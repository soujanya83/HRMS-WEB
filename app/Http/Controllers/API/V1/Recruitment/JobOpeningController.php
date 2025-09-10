<?php

namespace App\Http\Controllers\API\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class JobOpeningController extends Controller
{
    /**
     * Display a listing of job openings
     */
    public function index(): JsonResponse
    {
        try {
            $jobOpenings = JobOpening::with(['organization', 'department', 'designation'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Job openings retrieved successfully',
                'data' => $jobOpenings
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job openings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created job opening
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'requirements' => 'required|string',
                'location' => 'required|string|max:255',
                'employment_type' => 'required|in:full-time,part-time,contract,internship,temporary',
                'status' => 'required|in:open,closed,draft,on-hold',
                'posting_date' => 'required|date',
                'closing_date' => 'required|date|after:posting_date',
            ]);

            $jobOpening = JobOpening::create($validated);
            $jobOpening->load(['organization', 'department', 'designation']);

            return response()->json([
                'success' => true,
                'message' => 'Job opening created successfully',
                'data' => $jobOpening
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
                'message' => 'Failed to create job opening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified job opening
     */
    public function show($id): JsonResponse
    {
        try {
            $jobOpening = JobOpening::with(['organization', 'department', 'designation', 'applicants'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Job opening retrieved successfully',
                'data' => $jobOpening
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job opening not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified job opening
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $jobOpening = JobOpening::findOrFail($id);

            $validated = $request->validate([
                'organization_id' => 'sometimes|exists:organizations,id',
                'department_id' => 'sometimes|exists:departments,id',
                'designation_id' => 'sometimes|exists:designations,id',
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'requirements' => 'sometimes|string',
                'location' => 'sometimes|string|max:255',
                'employment_type' => 'sometimes|in:full-time,part-time,contract,internship,temporary',
                'status' => 'sometimes|in:open,closed,draft,on-hold',
                'posting_date' => 'sometimes|date',
                'closing_date' => 'sometimes|date|after:posting_date',
            ]);

            $jobOpening->update($validated);
            $jobOpening->load(['organization', 'department', 'designation']);

            return response()->json([
                'success' => true,
                'message' => 'Job opening updated successfully',
                'data' => $jobOpening
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
                'message' => 'Failed to update job opening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified job opening
     */
    public function destroy($id): JsonResponse
    {
        try {
            $jobOpening = JobOpening::findOrFail($id);
            
            // Check if there are any applicants
            $applicantCount = $jobOpening->applicants()->count();
            if ($applicantCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete job opening with existing applicants',
                    'applicant_count' => $applicantCount
                ], 400);
            }

            $jobOpening->delete();

            return response()->json([
                'success' => true,
                'message' => 'Job opening deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job opening',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job openings by status
     */
    public function getByStatus($status): JsonResponse
    {
        try {
            $validStatuses = ['open', 'closed', 'draft', 'on-hold'];
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided',
                    'valid_statuses' => $validStatuses
                ], 400);
            }

            $jobOpenings = JobOpening::with(['organization', 'department', 'designation'])
                ->where('status', $status)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Job openings with status '{$status}' retrieved successfully",
                'data' => $jobOpenings
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job openings by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active job openings (open and before closing date)
     */
    public function getActiveJobOpenings(): JsonResponse
    {
        try {
            $jobOpenings = JobOpening::with(['organization', 'department', 'designation'])
                ->where('status', 'open')
                ->where('closing_date', '>=', now())
                ->orderBy('posting_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Active job openings retrieved successfully',
                'data' => $jobOpenings
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active job openings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
