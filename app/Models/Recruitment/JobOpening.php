<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Designation;

class JobOpening extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'department_id', 'designation_id', 'title', 'description', 
        'requirements', 'location', 'employment_type', 'status', 'posting_date', 'closing_date',
    ];

    protected $casts = ['posting_date' => 'date', 'closing_date' => 'date'];

    public function organization() 
    { 
        return $this->belongsTo(Organization::class); 
    }
    public function department() {
         return $this->belongsTo(Department::class); 
        }
    public function designation() {
         return $this->belongsTo(Designation::class); 
        }
    public function applicants() {
         return $this->hasMany(Applicant::class); 
        }
}
