<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalaryStructureComponents extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_structure_id',
        'component_type_id',
        'percentage',
        'amount',
        'is_custom',
        'remarks'
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_custom' => 'boolean'
    ];

    public function structure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function componentType()
    {
        return $this->belongsTo(SalaryComponentTypes::class, 'component_type_id');
    }

    
}