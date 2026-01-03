<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\XeroConnection;
use App\Models\XeroPayslip;


class XeroPayRun extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'xero_connection_id',
        'xero_pay_run_id',
        'xero_payroll_calendar_id',
        'calendar_name',
        'period_start_date',
        'period_end_date',
        'payment_date',
        'status',
        'pay_run_type',
        'total_wages',
        'total_tax',
        'total_super',
        'total_reimbursement',
        'total_deductions',
        'total_net_pay',
        'last_synced_at',
        'is_synced',
        'xero_data',
        'employee_count',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'payment_date' => 'date',
        'last_synced_at' => 'datetime',
        'is_synced' => 'boolean',
        'xero_data' => 'array',
        'total_wages' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_super' => 'decimal:2',
        'total_reimbursement' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
    ];

    // Relationships
    public function xeroConnection()
    {
        return $this->belongsTo(XeroConnection::class);
    }

    public function payslips()
    {
        return $this->hasMany(XeroPayslip::class);
    }

    // Scopes
    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }
}
