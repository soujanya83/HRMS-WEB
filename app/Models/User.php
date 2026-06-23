<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;


class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_pin',
        'device_id',
        'is_organization',
        'reset_password_otp_expires_at',
        'reset_password_otp',
        'temp_pass_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship to employee
    public function employee()
    {
        return $this->hasOne(\App\Models\Employee\Employee::class);
    }

    /**
     * Assign a role to this user for a specific organization.
     */
    public function assignRoleForOrganization(string $roleName, int $organizationId): void
    {
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->firstOrFail();

        DB::table('user_organization_roles')->updateOrInsert(
            [
                'user_id' => $this->id,
                'organization_id' => $organizationId,
                'role_id' => $role->id,
            ],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Remove role for organization
     */
    public function removeRoleForOrganization(string $roleName, int $organizationId): int
    {
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
        if (!$role) return 0;

        return DB::table('user_organization_roles')
            ->where('user_id', $this->id)
            ->where('organization_id', $organizationId)
            ->where('role_id', $role->id)
            ->delete();
    }

    /**
     * Check if user has a given role for the organization.
     */
    public function hasRoleForOrganization(string $roleName, int $organizationId): bool
    {
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
        if (!$role) return false;

        return DB::table('user_organization_roles')
            ->where('user_id', $this->id)
            ->where('organization_id', $organizationId)
            ->where('role_id', $role->id)
            ->exists();
    }

    /**
     * Get roles for specific organization.
     */
    public function rolesForOrganization(int $organizationId)
    {
        return DB::table('user_organization_roles')
            ->join('roles','user_organization_roles.role_id','roles.id')
            ->where('user_organization_roles.user_id', $this->id)
            ->where('user_organization_roles.organization_id', $organizationId)
            ->select('roles.*')
            ->get();
    }

public function organizations()
{
    return $this->belongsToMany(
        Organization::class,
        'user_organization_roles',
        'user_id',
        'organization_id'
    );
}

}

// roles informations for furthur uses ----- 
// A. Getting the Authenticated User's Roles
// PHP
// $user = auth()->user();

 // 1. Get Global Roles (Spatie standard)
 // Returns a collection of role names like ['Super Admin', 'Employee']
// $globalRoles = $user->getRoleNames(); 

 // 2. Get Organization-Specific Roles (Using your custom model method)
// $orgId = $request->organization_id; 
// $orgRoles = $user->rolesForOrganization($orgId)->pluck('name'); 
// B. Assigning Roles to a User
// PHP
// $user = \App\Models\User::find($userId);

 // 1. Assign a Global Role (Spatie standard)
// $user->assignRole('HR Manager');

 // 2. Assign an Organization-Specific Role (Using your custom model method)
// $user->assignRoleForOrganization('Employee', $orgId);
// C. Checking if a User has a Specific Role (Security Checks)
// Use these inside your controllers to block unauthorized access.

// PHP
// $user = auth()->user();
// $orgId = $request->organization_id;

 // Check if they are a global Super Admin OR an Employee in this specific org
// if ($user->hasRole('Super Admin') || $user->hasRoleForOrganization('Employee', $orgId)) {
    // Allow action...
// } else {
//     return response()->json(['message' => 'Unauthorized action.'], 403);
// }