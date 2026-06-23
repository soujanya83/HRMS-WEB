<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PidtdcForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'organization_id',
        
        // Page 1
        'appointee_name', 'appointee_signature_path', 'appointee_signature_date',
        'nominated_supervisor_name', 'nominated_supervisor_signature_path', 'nominated_supervisor_signature_date',
        
        // Page 2
        'compliance_actions_details', 'has_suspended_certificate', 'suspended_certificate_details',
        'has_prohibition_notice', 'prohibition_notice_details', 'has_refused_licence', 'refused_licence_details',
        'declarant_full_name', 'declarant_address', 'declarant_dob', 'declarant_signature_path', 'declarant_signature_date',
        'witness_name', 'witness_signature_path',
        
        // Page 3
        'checklist_employee_name', 'checklist_data', 'checklist_comments',
        'checklist_ns_signature_path', 'checklist_ns_signature_date',
        'checklist_rp_signature_path', 'checklist_rp_signature_date',
    ];

    protected $casts = [
        'appointee_signature_date' => 'date',
        'nominated_supervisor_signature_date' => 'date',
        'declarant_dob' => 'date',
        'declarant_signature_date' => 'date',
        'checklist_ns_signature_date' => 'date',
        'checklist_rp_signature_date' => 'date',
        
        'has_suspended_certificate' => 'boolean',
        'has_prohibition_notice' => 'boolean',
        'has_refused_licence' => 'boolean',
        
        'checklist_data' => 'array', // Automatically cast the JSON string to a PHP array
    ];

    // Append URL fields dynamically for the React frontend
    protected $appends = [
        'appointee_signature_url',
        'nominated_supervisor_signature_url',
        'declarant_signature_url',
        'witness_signature_url',
        'checklist_ns_signature_url',
        'checklist_rp_signature_url',
    ];

    // Accessors
    public function getAppointeeSignatureUrlAttribute() { return $this->appointee_signature_path ? asset('storage/' . $this->appointee_signature_path) : null; }
    public function getNominatedSupervisorSignatureUrlAttribute() { return $this->nominated_supervisor_signature_path ? asset('storage/' . $this->nominated_supervisor_signature_path) : null; }
    public function getDeclarantSignatureUrlAttribute() { return $this->declarant_signature_path ? asset('storage/' . $this->declarant_signature_path) : null; }
    public function getWitnessSignatureUrlAttribute() { return $this->witness_signature_path ? asset('storage/' . $this->witness_signature_path) : null; }
    public function getChecklistNsSignatureUrlAttribute() { return $this->checklist_ns_signature_path ? asset('storage/' . $this->checklist_ns_signature_path) : null; }
    public function getChecklistRpSignatureUrlAttribute() { return $this->checklist_rp_signature_path ? asset('storage/' . $this->checklist_rp_signature_path) : null; }
}