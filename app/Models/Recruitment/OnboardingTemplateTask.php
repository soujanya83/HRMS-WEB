<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTemplateTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_template_id', 'task_name', 'description', 
        'default_due_days', 'default_assigned_role',
    ];

    public function template() { return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id'); }
}