<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;

class OnboardingTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'name', 'description'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function tasks() { return $this->hasMany(OnboardingTemplateTask::class, 'onboarding_template_id'); }
}
