<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Models\Payrolls as Payroll;

    class Bonus extends Model
{
    use HasFactory;

    protected $table = 'bonuses';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'type',
        'amount',
        'reason',
        'bonus_month',
        'status',
        'approved_by',
        'created_by',
        'paid_in_payroll_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bonus_month' => 'string',
    ];

    /**
     * Bonus belongs to an organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Bonus belongs to an employee.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The approver (HR/Admin user).
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The payroll where this bonus was finally included.
     */
    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'paid_in_payroll_id');
    }
}
