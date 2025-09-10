<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'job_opening_id', 'offer_date', 'expiry_date', 
        'salary_offered', 'joining_date', 'status', 'offer_letter_url',
    ];

    protected $casts = ['offer_date' => 'date', 'expiry_date' => 'date', 'joining_date' => 'date'];

    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function jobOpening() { return $this->belongsTo(JobOpening::class); }
}
