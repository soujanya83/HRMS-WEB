<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProhibitionNoticeDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'title',
        'last_name',
        'first_name',
        'mobile_number',
        'phone_number',
        'dob',
        'email',
        'address',
        'suburb',
        'state',
        'postcode',
        'former_names',
        'is_subject_to_prohibition',
        'is_prohibited_other_law',
        'declaration_place',
        'declaration_date',
        'witness_name',
    ];

    protected $casts = [
        'dob' => 'date',
        'declaration_date' => 'date',
        'is_subject_to_prohibition' => 'boolean',
        'is_prohibited_other_law' => 'boolean',
    ];
}