<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantAnswer extends Model
{
     protected $fillable = [
        'question_id',
        'applicant_name',
        'applicant_email',
        'applicant_id',
        'answer',
        'rating'
    ];

    public function question()
    {
        return $this->belongsTo(InterviewQuestion::class);
    }

}
