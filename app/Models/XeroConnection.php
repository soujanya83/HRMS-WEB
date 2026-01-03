<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use App\Models\EmployeeXeroConnection;
use App\Models\XeroPayRun;
use App\Models\XeroTimesheet;
use App\Models\XeroLeaveApplication;
use App\Models\XeroSyncLog;
use App\Models\XeroPayItem;
use App\Models\Organization;
use App\Models\Employee;
use App\Models\XeroWebhookEvent;
use App\Models\XeroPayslip;


class XeroConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'tenant_id',
        'tenant_name',
        'tenant_type',
        'xero_organization_id',
        'xero_organization_name',
        'xero_client_id',
        'xero_client_secret',
        'access_token',
        'refresh_token',
        'id_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'is_active',
        'last_synced_at',
        'connected_at',
        'disconnected_at',
        'country_code',
        'organisation_type',
        'scopes',
    ];


    protected $casts = [
        'token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'is_active' => 'boolean',
        'scopes' => 'array',
    ];

    // Encrypt sensitive tokens
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }




    public function getAccessTokenAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;  // or return $value;
        }
    }

    public function getRefreshTokenAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;  // or return $value;
        }
    }


    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }



    // Relationships
    public function employeeConnections()
    {
        return $this->hasMany(EmployeeXeroConnection::class);
    }

    public function payRuns()
    {
        return $this->hasMany(XeroPayRun::class);
    }

    public function timesheets()
    {
        return $this->hasMany(XeroTimesheet::class);
    }

    public function leaveApplications()
    {
        return $this->hasMany(XeroLeaveApplication::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(XeroSyncLog::class);
    }

    public function payItems()
    {
        return $this->hasMany(XeroPayItem::class);
    }

    // Helper Methods
    public function isTokenExpired()
    {
        return now()->greaterThan($this->token_expires_at);
    }

    public function needsRefresh()
    {
        // Refresh 5 minutes before expiry
        return now()->addMinutes(5)->greaterThan($this->token_expires_at);
    }
}
