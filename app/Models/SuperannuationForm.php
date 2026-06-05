<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperannuationForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'Tax_file_number', 'Employee_number', 'Employee_name' ,  'employee_id', 'organization_id', 'super_choice_type',
        
        // Section B
        'b_super_fund_name', 'b_super_fund_abn', 'b_usi', 
        'b_member_account_number', 'b_account_name', 'b_letter_of_compliance_attached',
        
        // Section C
        'c_business_name', 'c_business_abn', 'c_super_fund_name', 
        'c_super_fund_abn', 'c_usi', 'c_choose_default_fund_checkbox',
        
        // Section D
        'd_smsf_name', 'd_smsf_abn', 'd_smsf_esa', 'd_account_name', 
        'd_bank_account_name', 'd_bsb_code', 'd_account_number', 'd_provided_evidence_ato',
        
        // Signatures
        'signature_path', 'declaration_date'
    ];

    protected $casts = [
        'declaration_date' => 'date',
        'b_letter_of_compliance_attached' => 'boolean',
        'c_choose_default_fund_checkbox' => 'boolean',
        'd_provided_evidence_ato' => 'boolean',
    ];

    protected $appends = ['signature_url'];

    // Automatically appends the complete direct URL for the React app
    public function getSignatureUrlAttribute()
    {
        return $this->signature_path ? asset('storage/' . $this->signature_path) : null;
    }
}
