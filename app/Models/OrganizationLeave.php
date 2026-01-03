<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationLeave extends Model
{
       protected $table = 'organization_leaves';

        protected $fillable = [
        'leave_type',
        'granted_days',
        'carry_forward',
        'max_carry_forward',
        'paid',
        'requires_approval',
        'is_active',
        'allow_half_day',
        'gender_applicable',
        'description',
        'created_by',
        'updated_by',
        'organization_id',
        'employee_id',
        'xero_leave_type_id'
    ]; 

     protected $casts = [
        'carry_forward' => 'boolean',
        'paid' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'allow_half_day' => 'boolean',
    ];

       /**
     * Define relationships.
     */

    // Creator relationship (Admin/HR who added this leave type)

        public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active leave types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only paid leave types.
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }
}
