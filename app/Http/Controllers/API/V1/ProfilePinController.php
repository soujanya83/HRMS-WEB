<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Notifications\ProfilePinOtpNotification;


class ProfilePinController extends Controller
{
    /**
     * Create or update profile pin for authenticated user
     */
    public function createPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }
        if ($user->profile_pin) {
            return response()->json(['message' => 'Profile pin already set. Please use verify or reset.'], 400);
        }
        $user->profile_pin = password_hash($request->pin, PASSWORD_DEFAULT);
        $user->save();

        return response()->json(['message' => 'Profile pin set successfully.']);
    }

    /**
     * Verify profile pin for logged-in user
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }
        if (!$user->profile_pin) {
            return response()->json(['message' => 'No profile pin set. Please create one.'], 400);
        }
        if (!password_verify($request->pin, $user->profile_pin)) {
            return response()->json(['message' => 'Invalid profile pin.'], 401);
        }
        // Optionally, set a session flag for pin verification
        session(['profile_pin_verified' => true]);
        return response()->json(['message' => 'Profile pin verified.']);
    }

    /**
     * Forgot pin: send OTP to user's email for verification
     */
  public function forgotPin(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found.'], 404);
    }

    $otp = rand(100000, 999999);

    Cache::put('profile_pin_otp_' . $user->id, $otp, now()->addMinutes(10));

    $user->notify(new ProfilePinOtpNotification($otp));

    return response()->json([
        'message' => 'OTP sent to your email.'
    ]);
}


    /**
     * Verify OTP and reset profile pin
     */
    public function verifyOtpAndResetPin(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'pin' => 'required|digits:4',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $cacheKey = 'profile_pin_otp_' . $user->id;
        $cachedOtp = Cache::get($cacheKey);
        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP expired or invalid.'], 400);
        }
        if ($request->otp != $cachedOtp) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }
        // Set new encrypted pin
        $user->profile_pin = password_hash($request->pin, PASSWORD_DEFAULT);
        $user->save();
        // Remove OTP from cache
        Cache::forget($cacheKey);
        return response()->json(['message' => 'Profile pin reset successfully.']);
    }
}
