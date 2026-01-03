<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroConnection;

class XeroLeaveApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'employee_xero_connection_id',
        'xero_connection_id',
        'xero_leave_id',
        'xero_employee_id',
        'xero_leave_type_id',
        'leave_type_name',
        'start_date',
        'end_date',
        'status',
        'units',
        'units_type',
        'description',
        'title',
        'leave_periods',
        'xero_data',
        'last_synced_at',
        'is_synced',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'last_synced_at' => 'datetime',
        'is_synced' => 'boolean',
        'leave_periods' => 'array',
        'xero_data' => 'array',
        'units' => 'decimal:2',
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

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }
}
