<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\XeroPayRun;
use App\Models\EmployeeXeroConnection;
use App\Models\Employee;
use App\Models\XeroConnection;


class XeroPayslip extends Model
{
  use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'xero_connection_id',
        'xero_pay_run_id',
        'employee_xero_connection_id',
        'xero_payslip_id',
        'xero_employee_id',
        'wages',
        'allowances',
        'overtime',
        'bonuses',
        'total_earnings',
        'tax_deducted',
        'super_deducted',
        'other_deductions',
        'total_deductions',
        'reimbursements',
        'net_pay',
        'hours_worked',
        'overtime_hours',
        'earnings_lines',
        'deduction_lines',
        'leave_lines',
        'reimbursement_lines',
        'super_lines',
        'xero_data',
        'last_synced_at',
        'is_synced',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'is_synced' => 'boolean',
        'earnings_lines' => 'array',
        'deduction_lines' => 'array',
        'leave_lines' => 'array',
        'reimbursement_lines' => 'array',
        'super_lines' => 'array',
        'xero_data' => 'array',
        'wages' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'tax_deducted' => 'decimal:2',
        'super_deducted' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'reimbursements' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // Relationships
    public function payRun()
    {
        return $this->belongsTo(XeroPayRun::class, 'xero_pay_run_id');
    }

    public function employeeConnection()
    {
        return $this->belongsTo(EmployeeXeroConnection::class, 'employee_xero_connection_id');
    }

    // Helper Methods
    public function getEmployee()
    {
        return $this->employeeConnection->employee;
    }
}
