<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        try {

            // ✅ Validate organization_id (optional or required as per your need)
            $validated = $request->validate([
                'organization_id' => ['required', 'exists:organizations,id']
            ]);

            // ✅ Fetch roles
            $roles = Role::where('organization_id', $validated['organization_id'])
                ->where('name', '!=', 'superadmin') // exclude superadmin
                ->get();

            return response()->json([
                'status' => true,
                'data'   => $roles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
        ]);
        $organizationId = $request->organization_id ?? (auth()->user()->organization_id ?? null);
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
            //'guard_name' => $request->guard_name === 'api' ? 'api' : 'web',
            'organization_id' => $organizationId,
        ]);

        return response()->json($role, 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['superadmin', 'organization_admin', 'employee'])) {
            return response()->json([
                'message' => 'System roles cannot be deleted'
            ], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
