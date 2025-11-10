<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Organization;


class OvertimePolicies extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'hourly_rate',
        'multiplier',
        'approve_required',
        'is_active'
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'approve_required' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

