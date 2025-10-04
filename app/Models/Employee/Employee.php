<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Recruitment\Applicant;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'user_id', 'applicant_id', 'department_id', 'designation_id', 'reporting_manager_id',
        'employee_code', 'first_name', 'last_name', 'personal_email', 'date_of_birth', 'gender', 'phone_number',
        'address', 'joining_date', 'employment_type', 'status', 'tax_file_number', 'superannuation_fund_name',
        'superannuation_member_number', 'bank_bsb', 'bank_account_number', 'visa_type', 'visa_expiry_date',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    // Relationships
    public function user() { return $this->belongsTo(User::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function designation() { return $this->belongsTo(Designation::class); }
    public function manager() { return $this->belongsTo(Employee::class, 'reporting_manager_id'); }
    public function documents() { return $this->hasMany(EmployeeDocument::class); }
    public function employmentHistory() { return $this->hasMany(EmploymentHistory::class); }
    public function probationPeriod() { return $this->hasOne(ProbationPeriod::class); }
    public function exitDetails() { return $this->hasOne(EmployeeExit::class); }
}
