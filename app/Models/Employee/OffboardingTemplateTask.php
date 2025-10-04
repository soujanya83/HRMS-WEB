<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffboardingTemplateTask extends Model
{
    use HasFactory;
    protected $fillable = ['offboarding_template_id', 'task_name', 'description', 'due_before_days', 'default_assigned_role'];
    public function template() { return $this->belongsTo(OffboardingTemplate::class, 'offboarding_template_id'); }
}
