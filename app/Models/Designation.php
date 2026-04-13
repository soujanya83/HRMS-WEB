<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Designation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'title',
        'level',
    ];

    /**
     * Get the department that the designation belongs to.
     */
  public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
