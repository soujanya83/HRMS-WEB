<?php

namespace App\Models\Rostering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RosterPeriod extends Model
{
    protected $fillable = [
        'organization_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }
}
