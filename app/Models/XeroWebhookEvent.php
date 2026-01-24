<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class XeroWebhookEvent extends Model
{
    use HasFactory; use SoftDeletes;

    protected $table = 'xero_webhook_events';

    protected $fillable = [
        'organization_id',
         'event_id',
        'event_type',
        'event_category',
        'resource_type',
        'resource_id',
        'tenant_id',
        'payload',
        'headers',
        'status',
        'processed_at',
        'processing_error',
        'retry_count',
        'event_date_utc',
        'signature',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

      public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
