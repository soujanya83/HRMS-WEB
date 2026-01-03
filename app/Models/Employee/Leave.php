<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = ['employee_id', 'reason', 'start_date', 'end_date', 'leave_type', 'approved_by', 'status', 'days_count', 'XeroLeaveTypeID', 'xeroLeaveApplicationId'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
