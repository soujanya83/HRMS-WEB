<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayPeriod extends Model
{
    protected $table = 'pay_periods';

    protected $fillable = [
        'organization_id',
        'calendar_name',
        'calendar_type',
        'start_date',
        'end_date',
        'number_of_days',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];
}