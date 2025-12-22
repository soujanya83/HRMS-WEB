<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index($roleId)
    {
        $role = Role::findOrFail($roleId);

        return response()->json([
            'role' => $role->name,
            'permissions' => $role->permissions,
        ]);
    }

    public function sync(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $request->validate([
            'permissions' => 'required|array',
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions synced successfully',
        ]);
    }
}
