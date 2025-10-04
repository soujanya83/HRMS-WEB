<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffboardingTask extends Model
{
    use HasFactory;
    protected $fillable = ['employee_exit_id', 'task_name', 'due_date', 'completed_at', 'status', 'assigned_to'];
    public function employeeExit() { return $this->belongsTo(EmployeeExit::class); }
    public function assignedUser() { return $this->belongsTo(\App\Models\User::class, 'assigned_to'); }
}
