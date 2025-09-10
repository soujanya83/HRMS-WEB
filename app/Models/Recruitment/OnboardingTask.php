<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'task_name', 'description', 'due_date', 'completed_at', 'status',
    ];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function applicant() { return $this->belongsTo(Applicant::class); }
}