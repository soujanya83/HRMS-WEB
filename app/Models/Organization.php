<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'registration_number',
        'password',
        'created_by',
        'address',
        'contact_email',
        'contact_phone',
        'industry_type',
        'logo_url',
        'timezone',
    ];

    /**
     * Get the departments for the organization.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function attendanceRule(): HasMany
    {
        return $this->hasMany(OrganizationAttendanceRule::class);
    }
     /**
     * Users associated with this organization via pivot user_organization_roles.
     * Pivot contains role_id so you can filter by role if needed.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'user_organization_roles',
            'organization_id',
            'user_id'
        )->withPivot('role_id', 'created_at', 'updated_at');
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ];
    }

    /**
     * Scope to limit organizations visible to a given user.
     * - If $user has global "superadmin" role (spatie) -> do not limit.
     * - Otherwise return orgs from user_organization_roles pivot for user.
     */
    public function scopeForUser($query, $user)
    {
        // If user is a global superadmin (Spatie role), return all orgs
        // Make sure role name matches your roles table ('superadmin' assumed)
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        // Otherwise limit to organization_ids present in pivot for this user
        $orgIds = DB::table('user_organization_roles')
            ->where('user_id', $user->id)
            ->pluck('organization_id')
            ->toArray();

        return $query->whereIn('id', $orgIds);
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
