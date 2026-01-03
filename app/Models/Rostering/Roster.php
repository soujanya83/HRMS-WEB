<?php

namespace App\Models\Rostering;

use App\Models\Employee\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roster extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'roster_period_id',
        'employee_id',
        'shift_id',
        'roster_date',
        'start_time',
        'end_time',
        'notes',
        'created_by',
    ];

    /**
     * Define attribute casting.
     */
    protected $casts = [
        'roster_date' => 'date',
    ];

    /**
     * Get the organization this roster entry belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the employee assigned to this roster entry.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the shift definition for this roster entry.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the user (manager) who created this roster entry.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function period()
    {
        return $this->belongsTo(RosterPeriod::class, 'roster_period_id');
    }
}
