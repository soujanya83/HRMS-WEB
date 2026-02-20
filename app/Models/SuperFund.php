<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperFund extends Model
{
        protected $table = 'super_funds';

    protected $fillable = [
        'fund_name'
    ];

    public $timestamps = false;
}
