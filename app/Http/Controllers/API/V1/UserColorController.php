<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserColorController extends Controller
{
    private $sidebarColors = [
        [ 'name' => 'Dark Navy', 'value' => '#0B1A2E' ],
        [ 'name' => 'Charcoal', 'value' => '#2C2C2C' ],
        [ 'name' => 'Teal', 'value' => '#008080' ],
        [ 'name' => 'Deep Purple', 'value' => '#4B0082' ],
        [ 'name' => 'Forest Green', 'value' => '#228B22' ],
        [ 'name' => 'Slate Blue', 'value' => '#5B7B9A' ],
    ];

    private $backgroundColors = [
        [ 'name' => 'Pure White', 'value' => '#FFFFFF' ],
        [ 'name' => 'Snow', 'value' => '#FFFAFA' ],
        [ 'name' => 'Ivory', 'value' => '#FFFFF0' ],
        [ 'name' => 'Pearl', 'value' => '#F8F6F0' ],
        [ 'name' => 'Whisper', 'value' => '#F5F5F5' ],
        [ 'name' => 'Silver Mist', 'value' => '#E5E7EB' ],
        [ 'name' => 'Ash', 'value' => '#D1D5DB' ],
        [ 'name' => 'Pewter', 'value' => '#9CA3AF' ],
        [ 'name' => 'Stone', 'value' => '#6B7280' ],
        [ 'name' => 'Graphite', 'value' => '#4B5563' ],
        [ 'name' => 'Slate', 'value' => '#374151' ],
        [ 'name' => 'Charcoal', 'value' => '#1F2937' ],
    ];

    public function getColors(Request $request)
    {
        $user = Auth::user();
        return response()->json([
            'sidebar_color' => $user->sidebar_color ?? null,
            'background_color' => $user->background_color ?? null,
        ]);
    }

    public function setColors(Request $request)
    {
        $request->validate([
            'sidebar_color' => 'nullable|string|max:20',
            'background_color' => 'nullable|string|max:20',
        ]);
        $user = Auth::user();
        $sidebar = $request->sidebar_color;
        $background = $request->background_color;
        $user->sidebar_color = $sidebar;
        $user->background_color = $background;
        $user->save();

        if ($sidebar && !$background) {
            return response()->json(['message' => 'Sidebar color changed']);
        } elseif (!$sidebar && $background) {
            return response()->json(['message' => 'Background color changed']);
        } elseif ($sidebar && $background) {
            return response()->json(['message' => 'Sidebar and background colors changed']);
        } else {
            return response()->json(['message' => 'No color selected']);
        }
    }

    public function allColors()
    {
        return response()->json([
            'sidebarColors' => $this->sidebarColors,
            'backgroundColors' => $this->backgroundColors,
        ]);
    }
}
