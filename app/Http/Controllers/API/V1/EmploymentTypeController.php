<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmploymentType;
use Illuminate\Container\Attributes\Auth;

class EmploymentTypeController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Determine organization ID 
            $organizationId = $request->organization_id
                ?? auth()->user()->employee->organization_id;

            // Fetch all employment types for the organization
            $employmentTypes = EmploymentType::where('organization_id', $organizationId)->get();

            return response()->json([
                'status' => true,
                'data' => $employmentTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource.
     */
  public function store(Request $request)
{
    try {
        // Validate request
        $validated = $request->validate([
            'name' => 'required|string|unique:employment_types,name',
            'max_work_hours' => 'required|integer|min:1',
            'min_work_hours' => 'required|integer|min:0',
            'overtime_allowed' => 'required|boolean',
            'organization_id' => 'required|integer|exists:organizations,id',
        ]);

        // Create employment type
        $employmentType = EmploymentType::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Employment type created successfully',
            'data' => $employmentType
        ], 201);

    } catch (\Exception $e) {

        return response()->json([
            'status' => false,
            'message' => 'Failed to create employment type',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            // Fetch the employment type
            $employmentType = EmploymentType::find($id);

            // If not found
            if (!$employmentType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }

            // Successful response
            return response()->json([
                'status' => true,
                'data' => $employmentType
            ]);
        } catch (\Exception $e) {
            // Unexpected errors
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, $id)
    {
        try {
            // Find record
            $employmentType = EmploymentType::find($id);

            if (!$employmentType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }
            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|unique:employment_types,name,' . $id,
                'max_work_hours' => 'required|integer|min:1',
                'min_work_hours' => 'required|integer|min:0',
                'overtime_allowed' => 'required|boolean',
            ]);

            // Update record
            $employmentType->update($validated);

            return response()->json([
                'status' => true,
                'message' => 'Employment type updated successfully',
                'data' => $employmentType
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Any other error
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource.
     */
    public function destroy($id)
    {
        try {
            // Find the employment type
            $employmentType = EmploymentType::find($id);

            // If not found
            if (!$employmentType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }

            // Delete record
            $employmentType->delete();

            return response()->json([
                'status' => true,
                'message' => 'Employment type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while deleting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
