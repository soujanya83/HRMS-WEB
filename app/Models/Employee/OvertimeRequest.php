<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Organization;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'attendance_id',
        'work_date',
        'expected_overtime_hours',
        'actual_overtime_hours',
        'reason',
        'status',
        'approved_by_manager',
        'approved_at_manager',
        'approved_by_hr',
        'approved_at_hr',
        'manager_remarks',
        'hr_remarks',
        'payroll_processed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'approved_at_manager' => 'datetime',
        'approved_at_hr' => 'datetime',
        'payroll_processed' => 'boolean',
    ];

    // 🔗 Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'approved_by_manager');
    }

    public function hr()
    {
        return $this->belongsTo(Employee::class, 'approved_by_hr');
    }
}
