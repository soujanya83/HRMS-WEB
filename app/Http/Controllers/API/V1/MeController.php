<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MeController extends Controller
{
    public function roles(Request $request, $orgId)
    {
        $user = $request->user();

        $orgRoles = DB::table('user_organization_roles')
            ->join('roles', 'roles.id', '=', 'user_organization_roles.role_id')
            ->where('user_organization_roles.user_id', $user->id)
            ->where('user_organization_roles.organization_id', $orgId)
            ->pluck('roles.name');

        return response()->json([
            'global_roles' => $user->getRoleNames(),
            'organization_roles' => $orgRoles,
        ]);
    }

    public function permissions(Request $request, $orgId)
    {
        $user = $request->user();

        // aggregate permissions via roles
        $permissions = DB::table('user_organization_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'user_organization_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('user_organization_roles.user_id', $user->id)
            ->where('user_organization_roles.organization_id', $orgId)
            ->distinct()
            ->pluck('permissions.name');

        return response()->json($permissions);
    }
}
