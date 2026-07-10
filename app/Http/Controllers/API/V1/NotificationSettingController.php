<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NotificationRoleSetting;

class NotificationSettingController extends Controller
{
    // 1. Get all selected roles for an organization
    public function index(Request $request)
    {
        $request->validate(['organization_id' => 'required|integer']);

        $settings = NotificationRoleSetting::where('organization_id', $request->organization_id)->get();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    // 2. Save/Update which roles should receive notifications (Add/Edit options)
    public function syncRoles(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|integer',
            'roles' => 'required|array', // frontend se array aayega: ['superadmin', 'hr', 'manager']
            'roles.*' => 'string'
        ]);

        $orgId = $request->organization_id;

        // Jo roles frontend ne nahi bheje (uncheck kar diye), unhe delete karein
        NotificationRoleSetting::where('organization_id', $orgId)
            ->whereNotIn('role_name', $request->roles)
            ->delete();

        // Naye roles insert karein (agar pehle se nahi hain)
        foreach ($request->roles as $roleName) {
            NotificationRoleSetting::firstOrCreate([
                'organization_id' => $orgId,
                'role_name' => $roleName
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Notification roles updated successfully.']);
    }

    // 3. Mute/Snooze a specific role
    public function muteRole(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|integer',
            'role_name' => 'required|string',
            'duration' => 'required|in:1_hour,1_week,1_month,unmute'
        ]);

        $setting = NotificationRoleSetting::where('organization_id', $request->organization_id)
            ->where('role_name', $request->role_name)
            ->firstOrFail();

        $mutedUntil = match ($request->duration) {
            '1_hour' => now()->addHour(),
            '1_week' => now()->addWeek(),
            '1_month' => now()->addMonths(1),
            'unmute' => null,
        };

        $setting->update(['muted_until' => $mutedUntil]);

        $msg = $mutedUntil ? "Notifications muted for {$request->duration}" : "Notifications unmuted.";

        return response()->json(['success' => true, 'message' => $msg, 'muted_until' => $mutedUntil]);
    }
}