<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildSafeCodeOfConductForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 
        'organization_id',
        'name',
        'signature_path',
        'signature_date',
    ];

    protected $casts = [
        'signature_date' => 'date',
    ];

    // Automatically append the full URL for the signature
    protected $appends = [
        'signature_url'
    ];

    public function getSignatureUrlAttribute()
    {
        return $this->signature_path ? asset('storage/' . $this->signature_path) : null;
    }
}