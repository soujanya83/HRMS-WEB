<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
     protected $fillable = [
        'name',
        'max_work_hours',
        'min_work_hours',
        'overtime_allowed',
        'organization_id'
    ];
}
