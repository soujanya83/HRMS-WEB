<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroConnection;
use App\Models\XeroPayRun;

class XeroTimesheet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_xero_connection_id',
        'xero_connection_id',
        'xero_timesheet_id',
        'xero_employee_id',
        'start_date',
        'end_date',
        'status',
        'total_hours',
        'ordinary_hours',
        'overtime_hours',
        'timesheet_lines',
        'xero_data',
        'last_synced_at',
        'is_synced',
        'payment_date'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_synced_at' => 'datetime',
        'is_synced' => 'boolean',
        'timesheet_lines' => 'array',
        'xero_data' => 'array',
        'total_hours' => 'decimal:2',
        'ordinary_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // Relationships
    public function employeeConnection()
    {
        return $this->belongsTo(EmployeeXeroConnection::class, 'employee_xero_connection_id');
    }

    public function xeroConnection()
    {
        return $this->belongsTo(XeroConnection::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }
}
