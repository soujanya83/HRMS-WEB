<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Task;
use App\Models\Project;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'project_id',
        'task_id',
        'work_date',
        'regular_hours',
        'overtime_hours',
        'is_overtime',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
        'employee_id',
        'billable_hours'
    ];

    protected $casts = [
        'work_date' => 'date',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'is_overtime' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationship: Timesheet belongs to an Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Timesheet belongs to a Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Timesheet belongs to a Task
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Timesheet belongs to Attendance
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Relationship: Approved by Manager or HR
     */
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
