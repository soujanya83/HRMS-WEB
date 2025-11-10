<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee\Employee;
use App\Models\Employee\Timesheet;

class Project extends Model
{
     use HasFactory;

    protected $fillable = [
        'organization_id',
        'created_by',
        'details_file',
        'name',
        'description',
        'manager_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Relationship: Project has many Tasks
     */
    public function organization(){
        return $this->belongsTo(Organization::class,'organization_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relationship: Project belongs to a Manager (Employee)
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

      public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Relationship: Project has many Timesheets (via tasks)
     */
    public function timesheets()
    {
        return $this->hasManyThrough(Timesheet::class, Task::class);
    }
}
