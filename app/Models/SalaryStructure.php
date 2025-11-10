<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use App\Models\Employee\Employee;
use App\Models\SalaryStructureComponent;
use App\Models\SalaryRevision;
use App\Models\Payroll;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'grade_level',
        'base_salary',
        'currency',
        'is_active'
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function components()
    {
        return $this->hasMany(SalaryStructureComponents::class);
    }

    public function revisions()
    {
        return $this->hasMany(SalaryRevisions::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payrolls::class);
    }
}
