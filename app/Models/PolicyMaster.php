<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyMaster extends Model
{
     protected $fillable = [

        'policy_name',

        'slug',

        'description',

        'document',

        'sort_order',

        'is_required',

        'is_active'

    ];
}
