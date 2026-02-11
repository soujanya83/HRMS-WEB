<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XeroLeaveType extends Model
{
    protected $fillable = [
        'organization_id',
        'xero_leave_type_id',
        'name',
        'type_of_units',
        'is_paid_leave',
        'show_on_payslip'
    ];
}