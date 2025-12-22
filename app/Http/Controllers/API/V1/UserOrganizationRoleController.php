<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class UserOrganizationRoleController extends Controller
{
    public function index($orgId, $userId)
    {
        $roles = DB::table('user_organization_roles')
            ->join('roles', 'roles.id', '=', 'user_organization_roles.role_id')
            ->where('user_organization_roles.user_id', $userId)
            ->where('user_organization_roles.organization_id', $orgId)
            ->pluck('roles.name');

        return response()->json($roles);
    }

    public function store(Request $request, $orgId, $userId)
    {
        $request->validate([
            'roles' => 'required|array|max:1',
        ]);

        $user = User::findOrFail($userId);

        // Remove existing roles (single-role-per-org model)
        DB::table('user_organization_roles')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->delete();

        foreach ($request->roles as $roleName) {
            $role = Role::where('name', $roleName)->firstOrFail();

            DB::table('user_organization_roles')->insert([
                'user_id' => $user->id,
                'organization_id' => $orgId,
                'role_id' => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Roles assigned']);
    }

    public function destroy($orgId, $userId, $roleName)
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $check = DB::table('user_organization_roles')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('role_id', $role->id)
            ->first();
        if (!$check) {
            return response()->json(['message' => 'Role not assigned to user in this organization'], 404);
        }
        

        DB::table('user_organization_roles')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('role_id', $role->id)
            ->delete();

        return response()->json(['message' => 'Role removed']);
    }
}
