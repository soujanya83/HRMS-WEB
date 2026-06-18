<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentMaster extends Model
{
    protected $fillable = [

        'document_name',

        'document_type',

        'slug',

        'description',

        'icon',

        'is_required',

        'has_expiry',

        'expiry_years',

        'sort_order',

        'is_active'

    ];
}
