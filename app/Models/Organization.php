<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
