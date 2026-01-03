<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\XeroConnection;
use App\Models\User;

class XeroSyncLog extends Model
{
     use HasFactory;

    protected $fillable = [
        'organization_id',
        'xero_connection_id',
        'sync_type',
        'operation',
        'status',
        'records_processed',
        'records_successful',
        'records_failed',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_message',
        'error_details',
        'failed_records',
        'sync_filters',
        'response_summary',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_details' => 'array',
        'failed_records' => 'array',
        'sync_filters' => 'array',
        'response_summary' => 'array',
    ];

    // Relationships
    public function xeroConnection()
    {
        return $this->belongsTo(XeroConnection::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }
}
