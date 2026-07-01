<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentContractForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'contract_date',
        'educator_name',
        'address',
        'position',
        'disclosure_date',
        'disclosure_signature_path',
        'employment_type',
        'hours_per_week',
        'commencement_date',
        'award_classification',
        'remuneration',
        'acceptance_name',
        'contract_signature_path',
        'contract_signature_date',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'disclosure_date' => 'date',
        'contract_signature_date' => 'date',
    ];

    protected $appends = [
        'disclosure_signature_url',
        'contract_signature_url'
    ];

    public function getDisclosureSignatureUrlAttribute()
    {
        return $this->disclosure_signature_path ? asset('storage/' . $this->disclosure_signature_path) : null;
    }

    public function getContractSignatureUrlAttribute()
    {
        return $this->contract_signature_path ? asset('storage/' . $this->contract_signature_path) : null;
    }
}
