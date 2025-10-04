<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentHistory extends Model
{
    use HasFactory;
    protected $table = 'employment_history';
    protected $fillable = ['employee_id', 'department_id', 'designation_id', 'start_date', 'end_date', 'reason_for_change'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function department() { return $this->belongsTo(\App\Models\Department::class); }
    public function designation() { return $this->belongsTo(\App\Models\Designation::class); }
}
