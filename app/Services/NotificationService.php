<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\NotificationRoleSetting; // Naya model import kiya
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * DYNAMIC FUNCTION: Ye frontend se set kiye gaye roles aur MUTE status ko check karega.
     * Ab API controller se roles pass karne ki zaroorat nahi hai.
     */
    public static function sendDynamic(
        int $organizationId, 
        string $type, 
        string $title, 
        string $message, 
        int $creatorId = null,
        array $data = []
    ) {
        // 1. Un roles ko DB se nikalo jo is organization me selected hain AUR muted nahi hain
        $activeRoles = NotificationRoleSetting::where('organization_id', $organizationId)
            ->where(function ($query) {
                $query->whereNull('muted_until')
                      ->orWhere('muted_until', '<', now()); // Mute time khatam ho chuka ho
            })
            ->pluck('role_name')
            ->toArray();

        // 2. Agar koi bhi role active/selected nahi hai, to aage notification create mat karo
        if (empty($activeRoles)) {
            return;
        }

        // 3. Agar active roles mil gaye, to aapke purane function ko call kardo
        self::sendToOrganizationRoles(
            $organizationId, 
            $activeRoles, // DB se aaye hue roles pass kar diye
            $type, 
            $title, 
            $message, 
            $creatorId, 
            $data
        );
    }

    /**
     * PURANA FUNCTION: Ye waisa hi rahega, kyunki 'sendDynamic' isko use kar raha hai.
     */
    public static function sendToOrganizationRoles(
        int $organizationId,
        array $roles, 
        string $type, 
        string $title, 
        string $message, 
        int $creatorId = null,
        array $data = []
    ) {
        $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');

        $userIds = DB::table('user_organization_roles')
            ->where('organization_id', $organizationId)
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        $notifications = [];
        $now = now();

        foreach ($userIds as $userId) {
            $notifications[] = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'creator_id' => $creatorId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($notifications)) {
            SystemNotification::insert($notifications);
        }
    }
}