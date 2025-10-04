<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffboardingTemplate extends Model
{
    use HasFactory;
    protected $fillable = ['organization_id', 'name', 'description'];
    public function organization() { return $this->belongsTo(\App\Models\Organization::class); }
    public function tasks() { return $this->hasMany(OffboardingTemplateTask::class, 'offboarding_template_id'); }
}
