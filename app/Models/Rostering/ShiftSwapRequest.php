<?php

namespace App\Models\Rostering;

use App\Models\Employee\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSwapRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_employee_id',
        'requester_roster_id',
        'requested_employee_id',
        'requested_roster_id',
        'status',
        'requester_reason',
        'rejection_reason',
        'manager_approver_id',
        'manager_approved_at',
    ];

    /**
     * Define attribute casting.
     */
    protected $casts = [
        'manager_approved_at' => 'datetime',
    ];

    /**
     * Get the employee who initiated the request.
     */
    public function requester()
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    /**
* Get the roster entry (shift) the requester wants to give away.
     */
    public function requesterRoster()
    {
        return $this->belongsTo(Roster::class, 'requester_roster_id');
    }

    /**
     * Get the employee who was asked to swap.
     */
    public function requestedEmployee()
    {
        return $this->belongsTo(Employee::class, 'requested_employee_id');
    }

    /**
     * Get the roster entry (shift) the requester wants to take.
     */
    public function requestedRoster()
    {
        return $this->belongsTo(Roster::class, 'requested_roster_id');
    }

    /**
     * Get the manager (as a User) who approved the request.
     */
    public function managerApprover()
    {
        return $this->belongsTo(User::class, 'manager_approver_id');
    }
}
