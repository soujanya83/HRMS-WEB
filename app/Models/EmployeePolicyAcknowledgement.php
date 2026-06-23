<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePolicyAcknowledgement extends Model
{
      protected $fillable = [

        'organization_id',

        'employee_id',

        'policy_master_id',

        'is_viewed',

        'viewed_at',

        'is_acknowledged',

        'acknowledged_at',

        'ip_address',

        'user_agent'

    ];

    protected $casts = [

        'is_viewed'=>'boolean',

        'is_acknowledged'=>'boolean',

        'viewed_at'=>'datetime',

        'acknowledged_at'=>'datetime'

    ];
}
