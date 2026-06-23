<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormMaster extends Model
{
     protected $fillable = [
        'form_name',
        'table_name',
        'slug',
        'sort_order',
        'is_active',
        'is_required'
    ];
}
