<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee\Employee;

class HolidayModel extends Model
{
        use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'organization_holidays';

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'organization_id',
        'holiday_name',
        'holiday_date',
        'holiday_type',
        'is_recurring',
        'description',
        'is_active',
        'created_by',
    ];

    /**
     * Relationships
     */

    // Belongs to an organization
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    // Created by a specific employee
    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Scopes
     */

    // Active holidays only
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Holidays for a specific year
    public function scopeForYear($query, $year)
    {
        return $query->whereYear('holiday_date', $year);
    }

    // Recurring holidays only
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }
}
