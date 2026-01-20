<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\XeroConnection;


class XeroPayItem extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'xero_connection_id',
        'xero_pay_item_id',
        'item_type',
        'category',
        'name',
        'display_name',
        'is_active',
        'is_system_item',
        'rate_type',
        'default_rate',
        'expense_account_code',
        'liability_account_code',
        'xero_data',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system_item' => 'boolean',
        'last_synced_at' => 'datetime',
        'xero_data' => 'array',
        'default_rate' => 'decimal:2',
    ];

    // Relationships
    public function xeroConnection()
    {
        return $this->belongsTo(XeroConnection::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings($query)
    {
        return $query->where('item_type', 'earnings');
    }

    public function scopeDeductions($query)
    {
        return $query->where('item_type', 'deduction');
    }

    public function scopeLeave($query)
    {
        return $query->where('item_type', 'leave');
    }
}
