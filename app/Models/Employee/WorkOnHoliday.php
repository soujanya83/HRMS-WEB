<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Organization;
USE App\Models\HolidayModel;

class WorkOnHoliday extends Model
{
    use HasFactory;
    protected $table = 'work_on_holidays';

    // Mass assignable fields
    protected $fillable = [
        'employee_id',
        'organization_id',
        'holiday_id',
        'work_date',
        'reason',
        'expected_overtime_hours',
        'actual_overtime_hours',
        'status',
        'approved_by_manager',
        'approved_at_manager',
        'approved_by_hr',
        'approved_at_hr',
        'manager_remarks',
        'hr_remarks',
        'payroll_processed',
        'created_by',
        'updated_by',
    ];

    // Casts
    protected $casts = [
        'work_date' => 'date',
        'expected_overtime_hours' => 'decimal:2',
        'actual_overtime_hours' => 'decimal:2',
        'approved_at_manager' => 'datetime',
        'approved_at_hr' => 'datetime',
        'payroll_processed' => 'boolean',
    ];

    // Relationships

    /** Employee who requested the work on holiday */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Organization of the employee */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** Holiday linked to this request */
    public function holiday()
    {
        return $this->belongsTo(HolidayModel::class, 'holiday_id');
    }

    /** Manager who approved */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'approved_by_manager');
    }

    /** HR who approved */
    public function hr()
    {
        return $this->belongsTo(Employee::class, 'approved_by_hr');
    }

    // Scopes for filtering

    /** Only pending requests */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Only approved by manager */
    public function scopeManagerApproved($query)
    {
        return $query->where('status', 'manager_approved');
    }

    /** Only fully approved by HR */
    public function scopeHrApproved($query)
    {
        return $query->where('status', 'hr_approved');
    }

    /** Only rejected requests */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /** Only cancelled requests */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Helper methods

    /** Check if fully approved */
    public function isFullyApproved(): bool
    {
        return $this->status === 'hr_approved';
    }

    /** Check if pending approval */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
