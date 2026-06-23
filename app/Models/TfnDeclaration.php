<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TfnDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'organization_id',
        
        // Section A
        'tfn_number', 'tfn_exemption_type', 'title', 'surname', 'first_name', 
        'other_names', 'previous_name', 'dob', 'payee_address', 'payee_suburb', 
        'payee_state', 'payee_postcode', 'payee_email', 'employment_basis', 
        'residency_status', 'claim_tax_free_threshold', 'has_help_debt', 
        'payee_signature_path', 'payee_declaration_date',
        
        // Section B
        'payer_abn', 'payer_branch_number', 'payer_applied_for_abn', 'payer_legal_name', 
        'payer_address', 'payer_suburb', 'payer_state', 'payer_postcode', 'payer_email', 
        'payer_contact_person', 'payer_phone', 'no_longer_makes_payments', 
        'payer_signature_path', 'payer_declaration_date'
    ];

    protected $casts = [
        'dob' => 'date',
        'payee_declaration_date' => 'date',
        'payer_declaration_date' => 'date',
        'claim_tax_free_threshold' => 'boolean',
        'has_help_debt' => 'boolean',
        'payer_applied_for_abn' => 'boolean',
        'no_longer_makes_payments' => 'boolean',
    ];

    // Append these virtual attributes to the JSON response automatically
    protected $appends = ['payee_signature_url', 'payer_signature_url'];

    public function getPayeeSignatureUrlAttribute()
    {
        return $this->payee_signature_path ? asset('storage/' . $this->payee_signature_path) : null;
    }

    public function getPayerSignatureUrlAttribute()
    {
        return $this->payer_signature_path ? asset('storage/' . $this->payer_signature_path) : null;
    }
}