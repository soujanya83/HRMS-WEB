<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use Exception;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $organizations = Organization::all();
            return response()->json([
                'success' => true,
                'data' => $organizations,
                'message' => 'Organizations retrieved successfully.'
            ], 200);
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
    public function store(StoreOrganizationRequest $request)
    {
        try {
            $organization = Organization::create($request->validated());
            return response()->json([
                'success' => true,
                'data' => $organization,
                'message' => 'Organization created successfully.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the organization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
         return response()->json([
            'success' => true,
            'data' => $organization,
            'message' => 'Organization retrieved successfully.'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        try {
            $organization->update($request->validated());
            return response()->json([
                'success' => true,
                'data' => $organization,
                'message' => 'Organization updated successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the organization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        try {
            $organization->delete();
            return response()->json([
                'success' => true,
                'message' => 'Organization deleted successfully.'
            ], 200);
        } catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the organization: ' . $e->getMessage()
            ], 500);
        }
    }
}
