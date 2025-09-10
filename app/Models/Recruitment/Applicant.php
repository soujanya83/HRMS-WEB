<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'job_opening_id', 'first_name', 'last_name', 'email', 'phone', 
        'resume_url', 'cover_letter', 'source', 'status', 'applied_date',
    ];

    protected $casts = ['applied_date' => 'date'];

    public function jobOpening() { return $this->belongsTo(JobOpening::class); }
    public function interviews() { return $this->hasMany(Interview::class); }
    public function jobOffer() { return $this->hasOne(JobOffer::class); }
    public function onboardingTasks() { return $this->hasMany(OnboardingTask::class); }
}