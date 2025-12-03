<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee\Employee;
use App\Models\Employee\Timesheet;


class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'assigned_to',
        'title',
        'description',
        'priority',
        'start_date',
        'due_date',
        'estimated_hours',
        'progress_percent',
        'status',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'progress_percent' => 'decimal:2',
    ];

    /**
     * Relationship: Task belongs to a Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class,'project_id');
    }

    /**
     * Relationship: Task belongs to an Employee (assigned user)
     */
    public function assignedTo()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

      public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
    /**
     * Relationship: Task has many Timesheets
     */
    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }
}
