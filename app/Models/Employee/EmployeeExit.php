<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeExit extends Model
{
    use HasFactory;
    protected $fillable = ['employee_id', 'resignation_date', 'last_working_day', 'reason_for_leaving', 'exit_interview_feedback', 'is_eligible_for_rehire'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function offboardingTasks() { return $this->hasMany(OffboardingTask::class); }
}
