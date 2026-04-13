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
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee\Employee;
use App\Models\SuperFund;


class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index()
{
    try {
        $user = Auth::user();

        // =====================================
        // 1. IF USER IS ORGANIZATION
        // =====================================
        if ($user->is_organization == 1) {

            $organizations = Organization::where('user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'data' => $organizations,
                'message' => 'Organization data retrieved.'
            ], 200);
        }

        // =====================================
        // 2. CHECK SUPERADMIN ROLE
        // =====================================
        $isSuperadmin = DB::table('user_organization_roles')
            ->join('roles','user_organization_roles.role_id','=','roles.id')
            ->where('user_organization_roles.user_id',$user->id)
            ->where('roles.name','superadmin')
            ->exists();

        if ($isSuperadmin) {

            $organizations = Organization::where('created_by', $user->id)->get();

            return response()->json([
                'success' => true,
                'data' => $organizations,
                'message' => 'Superadmin organizations retrieved.'
            ], 200);
        }

        // =====================================
        // 3. NORMAL EMPLOYEE USER
        // =====================================
        $employee = $user->employee;

        if (!$employee || !$employee->organization_id) {
            return response()->json([
                'success' => false,
                'message' => 'User has not been assigned any role or organization.'
            ], 404);
        }

        $organizations = Organization::where('id', $employee->organization_id)->get();

        return response()->json([
            'success' => true,
            'data' => $organizations,
            'message' => 'Employee organization retrieved.'
        ], 200);

    } catch (Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}


   

    /**
     * Store a newly created resource in storage.
     */
public function store(StoreOrganizationRequest $request)
{
    DB::beginTransaction();

    try {
        $authUser = Auth::user();
        $validated = $request->validated();

        // ===============================
        // GET EMAIL FROM contact_email
        // ===============================
        $email = $validated['contact_email'];

        // ===============================
        // CHECK USER EXIST OR CREATE
        // ===============================
        $user = User::where('email', $email)->first();

        if (!$user) {

            // name from email before @
            $name = explode('@', $email)[0];

            $user = User::create([
                'name' => ucfirst($name),
                'email' => $email,
                'password' => Hash::make('test@123'),
                'is_organization' => 1
            ]);
        }

        // ===============================
        // CREATE ORGANIZATION
        // ===============================
        $orgData = array_merge($validated, [
            'user_id'    => $user->id,        // created/existing user id
            'created_by' => $authUser->id     // auth user id
        ]);

        $organization = Organization::create($orgData);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully',
            'data' => [
                'organization' => $organization,
                'user' => $user
            ]
        ], 201);

    } catch (Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Failed: ' . $e->getMessage()
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

     public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([]);
        }

        $funds = SuperFund::where('fund_name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->pluck('fund_name');

        return response()->json($funds);
    }
}
