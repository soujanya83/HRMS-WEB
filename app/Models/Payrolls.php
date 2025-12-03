<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Organization;
use App\Models\Employee\Employee;
use App\Models\SalaryStructure;

class Payrolls extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'salary_structure_id',
        'pay_period',
        'from_date',
        'to_date',
        'gross_earnings',
        'gross_deductions',
        'net_salary',
        'working_days',
        'present_days',
        'leave_days',
        'overtime_hours',
        'overtime_amount',
        'tax_deducted',
        'pf_contribution',
        'esi_contribution',
        'payment_status',
        'payment_date',
        'payment_method',
        'transaction_ref',
        'component_breakdown',
        'remarks'
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'gross_earnings' => 'decimal:2',
        'gross_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'component_breakdown' => 'array'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class);
    }
}