<?php

namespace App\Models\Rostering;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'start_time',
        'end_time',
        'color_code',
        'notes',
    ];

    /**
     * Get the organization that this shift belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get all the roster entries associated with this shift definition.
     */
    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }
}
