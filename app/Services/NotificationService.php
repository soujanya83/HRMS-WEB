<?php

namespace App\Services;

use App\Models\SystemNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Kisi organization ke specific roles wale users ko notification bhejein
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
        // 1. Roles ki IDs nikaalein
        $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');

        // 2. Un users ko find karein jinke paas is organization mein ye roles hain
        $userIds = DB::table('user_organization_roles')
            ->where('organization_id', $organizationId)
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        // 3. Har eligible user ke liye notification create karein
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
                'data' => json_encode($data), // DB mein insert ke liye encode
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($notifications)) {
            SystemNotification::insert($notifications);
        }
    }
}