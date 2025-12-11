<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class TaxSlabs extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_code',
        'financial_year',
        'tax_regime',
        'min_income',
        'max_income',
        'tax_rate',
        'surcharge',
        'cess'
    ];

    protected $casts = [
        'min_income' => 'decimal:2',
        'max_income' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'surcharge' => 'decimal:2',
        'cess' => 'decimal:2'
    ];

    public function scopeForYear($query, $year)
    {
        return $query->where('financial_year', $year);
    }

    public function scopeForCountry($query, $country)
    {
        return $query->where('country_code', $country);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
