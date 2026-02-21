<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Department;
use App\Models\Organization;    
use App\Http\Requests\StoreDesignationRequest;
use App\Http\Requests\UpdateDesignationRequest;
use Exception;


class DesignationController extends Controller
{
    /**
     * Display a listing of the resource for a specific organization.
     */
    public function index(Organization $organization)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $organization->designations,
                'message' => 'Designations retrieved successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignationRequest $request, Organization $organization)
    {
        try {
            $designation = $organization->designations()->create($request->validated());
            return response()->json([
                'success' => true,
                'data' => $designation,
                'message' => 'Designation created successfully.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Designation $designation)
    {
        return response()->json([
            'success' => true,
            'data' => $designation,
            'message' => 'Designation retrieved successfully.'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignationRequest $request, Designation $designation)
    {
        try {
            $designation->update($request->validated());
            return response()->json([
                'success' => true,
                'data' => $designation,
                'message' => 'Designation updated successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Designation $designation)
    {
        try {
            $designation->delete();
            return response()->json([
                'success' => true,
                'message' => 'Designation deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
