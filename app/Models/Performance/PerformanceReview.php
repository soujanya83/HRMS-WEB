<?php

namespace App\Models\Performance;

use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_cycle_id',
        'employee_id',
        'manager_id',
        'employee_comments',
        'employee_rating',
        'employee_submitted_at',
        'manager_comments',
        'manager_feedback_strengths',
        'manager_feedback_areas_for_improvement',
        'manager_rating',
        'manager_submitted_at',
        'status',
        'acknowledged_at',
    ];

    protected $casts = [
        'employee_rating' => 'integer',
        'manager_rating' => 'integer',
        'employee_submitted_at' => 'datetime',
        'manager_submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * The review cycle this appraisal belongs to.
     */
    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }

    /**
     * The employee being reviewed.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * The manager who conducted the review.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Any continuous feedback linked to this review.
     */
    public function feedback()
    {
        return $this->hasMany(PerformanceFeedback::class);
    }
}