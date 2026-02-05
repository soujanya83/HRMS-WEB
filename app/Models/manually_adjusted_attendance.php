<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee\Attendance;
use App\Models\Employee\Employee;
class manually_adjusted_attendance extends Model
{
    public $table = 'manually_adjusted_attendance';
    public $timestamps = false;
    protected $fillable = [
        'employee_id',
        'organization_id',
        'date',
        'original_check_in',
        'original_check_out',
        'adjusted_check_in',
        'adjusted_check_out',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'attendance_id',
        'created_at',
        'updated_at',
        'type'
    ];

      public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }


public function employee()
{
    return $this->belongsTo(Employee::class);
}

public function approver()
{
    return $this->belongsTo(Employee::class, 'approved_by');    

}
public function creator()
{
    return $this->belongsTo(Employee::class, 'created_by');     

}

public function rejectedBy()
{
    return $this->belongsTo(Employee::class, 'rejected_by');     

}

}





