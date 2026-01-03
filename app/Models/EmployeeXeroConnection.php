<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Employee\Employee;
use App\Models\XeroConnection;
use App\Models\XeroPayslip;
use App\Models\XeroTimesheet;
use App\Models\XeroLeaveApplication;


class EmployeeXeroConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_id',
        'xero_connection_id',
        'xero_employee_id',
        'xero_contact_id',
        'is_synced',
        'last_synced_at',
        'sync_status',
        'sync_error',
        'xero_status',
        'xero_start_date',
        'xero_termination_date',
        'xero_employee_number',
        'xero_data',
        'mapping_config',
        'xerocalenderId',
        'OrdinaryEarningsRateID',
        'EarningsRateID'
    ];

    protected $casts = [
        'is_synced' => 'boolean',
        'last_synced_at' => 'datetime',
        'xero_start_date' => 'date',
        'xero_termination_date' => 'date',
        'xero_data' => 'array',
        'mapping_config' => 'array',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function xeroConnection()
    {
        return $this->belongsTo(XeroConnection::class);
    }

    public function payslips()
    {
        return $this->hasMany(XeroPayslip::class);
    }

    public function timesheets()
    {
        return $this->hasMany(XeroTimesheet::class);
    }

    public function leaveApplications()
    {
        return $this->hasMany(XeroLeaveApplication::class);
    }

    // Scopes
    public function scopeSynced($query)
    {
        return $query->where('is_synced', true);
    }

    public function scopeActive($query)
    {
        return $query->where('xero_status', 'ACTIVE');
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('sync_status', 'needs_update');
    }
}
