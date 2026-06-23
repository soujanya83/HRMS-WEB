<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'name',
        'dob',
        'email',
        'mobile_number',
        'address',
        'relevant_qualifications',
        'qualifications_copies_attached',
        'other_approved_training',
        'training_copies_attached',
        'wwc_wwvp_check_number',
        'status_check_date',
        'certified_supervisor_number',
    ];

    protected $casts = [
        'dob' => 'date',
        'status_check_date' => 'date',
        'qualifications_copies_attached' => 'boolean',
        'training_copies_attached' => 'boolean',
    ];
}
