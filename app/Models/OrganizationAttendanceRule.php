<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Employee\Employee;


class OrganizationAttendanceRule extends Model
{
    use HasFactory, SoftDeletes;

    // Table name (optional if it matches Laravel's pluralization)
    protected $table = 'organization_attendance_rules';

    // Columns that can be mass-assigned
    protected $fillable = [
        'organization_id',
        'shift_name',
        'check_in',
        'check_out',
        'shift_start',
        'shift_end',
        'break_start',
        'break_end',
        'late_grace_minutes',
        'half_day_after_minutes',
        'allow_overtime',
        'overtime_rate',
        'weekly_off_days',
        'flexible_hours',
        'absent_after_minutes',
        'is_remote_applicable',
        'rounding_minutes',
        'cross_midnight',
        'late_penalty_amount',
        'absent_penalty_amount',
        'relaxation',
        'policy_notes',
        'policy_version',
        'created_by',
        'is_active',
    ];

    // Casts for proper data types
    protected $casts = [
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
        'shift_start' => 'datetime:H:i',
        'shift_end' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'allow_overtime' => 'boolean',
        'flexible_hours' => 'boolean',
        'is_remote_applicable' => 'boolean',
        'cross_midnight' => 'boolean',
        'is_active' => 'boolean',
        'late_grace_minutes' => 'integer',
        'half_day_after_minutes' => 'integer',
        'absent_after_minutes' => 'integer',
        'overtime_rate' => 'decimal:2',
        'late_penalty_amount' => 'decimal:2',
        'absent_penalty_amount' => 'decimal:2',
    ];

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function roster()
    {
        return $this->hasMany(\App\Models\Rostering\Roster::class, 'organization_id', 'organization_id');
    }

}
