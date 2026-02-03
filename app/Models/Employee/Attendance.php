<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\OrganizationAttendanceRule;
use App\Models\Employee\Employee;


class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'notes',
        'total_work_hours',
        'break_start',
        'break_end',
        'is_late'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
    ];

    /**
     * Get the employee that owns the attendance.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to filter attendances by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter attendances by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Calculate total hours worked for this attendance record.
     */
    public function getTotalHoursAttribute()
    {
        if (!$this->check_in || !$this->check_out) {
            return null;
        }

        return $this->check_in->diffInHours($this->check_out, false);
    }

    public function attendanceRule(): BelongsTo
    {
        return $this->belongsTo(OrganizationAttendanceRule::class, 'organization_id', 'organization_id');
    }


}
