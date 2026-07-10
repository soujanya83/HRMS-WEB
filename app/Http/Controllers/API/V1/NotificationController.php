<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
   public function index(Request $request)
{
    // Logged in user aur uski selected organization ke notifications
    $notifications = SystemNotification::where('user_id', auth()->id())
        ->where('organization_id', $request->header('Organization-Id')) // Ya jo bhi aapka org identify karne ka tareeqa hai
        ->orderBy('created_at', 'desc')
        ->paginate(15);

    $unreadCount = SystemNotification::where('user_id', auth()->id())
        ->where('organization_id', $request->header('Organization-Id'))
        ->whereNull('read_at')
        ->count();

    return response()->json([
        'success' => true,
        'unread_count' => $unreadCount,
        'data' => $notifications
    ]);
}

public function markAsRead($id)
{
    $notification = SystemNotification::where('user_id', auth()->id())->findOrFail($id);
    $notification->update(['read_at' => now()]);

    return response()->json(['success' => true]);
}
}