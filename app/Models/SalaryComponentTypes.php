<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponentTypes extends Model
{
    protected $fillable = [
        'name',
        'category',
        'is_taxable',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function components()
    {
        return $this->hasMany(SalaryStructureComponents::class, 'component_type_id');
    }
}
