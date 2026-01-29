<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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


    // public function index(Request $request)
    // {
    //     try {
    //         /** @var \App\Models\User $user */
    //         $user = Auth::user();

    //         // If unauthenticated, return 401
    //         if (!$user) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthenticated.'
    //             ], 401);
    //         }

    //         // Base query
    //         $query = Organization::query();

    //         // If user is NOT superadmin, scope to their orgs (uses Organization::scopeForUser)
    //         $query = $query->forUser($user);

    //         // search
    //         $search = $request->query('q');
    //         if (!empty($search)) {
    //             $query->where('name', 'like', '%' . $search . '%')
    //                   ->orWhere('contact_email', 'like', '%' . $search . '%')
    //                   ->orWhere('contact_phone', 'like', '%' . $search . '%');
    //         }

    //         // allow eager loading small relations if requested (safe defaults)
    //         if ($request->query('with_departments') == '1') {
    //             $query->with('departments');
    //         }

    //         // superadmin may request all records without pagination using ?all=true
    //         $isSuperadmin = $user->hasRole('superadmin');

    //         if ($isSuperadmin && $request->boolean('all') === true) {
    //             $organizations = $query->get();
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => $organizations,
    //                 'message' => 'Organizations retrieved successfully.'
    //             ], 200);
    //         }

    //         // pagination (default 15)
    //         $perPage = (int) $request->query('per_page', 15);
    //         if ($perPage === 0) {
    //             $organizations = $query->get();
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => $organizations,
    //                 'message' => 'Organizations retrieved successfully.'
    //             ], 200);
    //         }

    //         $organizations = $query->paginate($perPage)->appends($request->except('page'));

    //         return response()->json([
    //             'success' => true,
    //             'data' => $organizations,
    //             'message' => 'Organizations retrieved successfully.'
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }


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
