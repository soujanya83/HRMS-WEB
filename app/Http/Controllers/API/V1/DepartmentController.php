<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Exception;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource for a specific organization.
     */
    public function index(Organization $organization)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $organization->departments,
                'message' => 'Departments retrieved successfully.'
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
    public function store(StoreDepartmentRequest $request, Organization $organization)
    {
        try {
            $department = $organization->departments()->create($request->validated());
            return response()->json([
                'success' => true,
                'data' => $department,
                'message' => 'Department created successfully.'
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
    public function show(Department $department)
    {
        return response()->json([
            'success' => true,
            'data' => $department,
            'message' => 'Department retrieved successfully.'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
       try {
            $department->update($request->validated());
            return response()->json([
                'success' => true,
                'data' => $department,
                'message' => 'Department updated successfully.'
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
    public function destroy(Department $department)
    {
        try {
            $department->delete();
            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
