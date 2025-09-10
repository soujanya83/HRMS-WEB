<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User; // Assuming a standard User model

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'interview_type', 'scheduled_at', 'location', 'status', 'feedback', 'result',
    ];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function interviewers() { return $this->belongsToMany(User::class, 'interview_user'); }
}
