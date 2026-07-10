<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRoleSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'role_name',
        'muted_until',
    ];

    protected $casts = [
        'muted_until' => 'datetime',
    ];
}