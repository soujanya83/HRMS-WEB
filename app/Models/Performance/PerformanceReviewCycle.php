<?php

namespace App\Models\Performance;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceReviewCycle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'start_date',
        'end_date',
        'deadline',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'deadline' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function performanceReviews()
    {
        return $this->hasMany(PerformanceReview::class, 'review_cycle_id');
    }

    public function performanceGoals()
    {
        return $this->hasMany(PerformanceGoal::class, 'review_cycle_id');
    }
}