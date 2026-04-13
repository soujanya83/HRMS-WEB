<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Employee\Employee;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            // ✅ Use Validator for JSON response
            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation errors',
                    'errors'  => $validator->errors()
                ], 422);
            }
    
            // ✅ Create user
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);
    
            // ✅ Generate token
            $token = $user->createToken("API Token")->plainTextToken;
    
            return response()->json([
                "status"  => true,
                "message" => "User registered successfully",
                "data"    => [
                    "user"  => $user,
                    "token" => $token
                ]
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

  public function login(Request $request)
{
    try {
        // ✅ Validate input
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            'deviceId' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        // ✅ Check user
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                "status"  => false,
                "message" => "Email not found"
            ], 404);
        }

        // ✅ Check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                "status"  => false,
                "message" => "Incorrect password"
            ], 401);
        }

        // ✅ Get Roles + Organizations
        $rolesData = DB::table('user_organization_roles as uor')
            ->join('roles as r', 'uor.role_id', '=', 'r.id')
            ->join('organizations as o', 'uor.organization_id', '=', 'o.id')
            ->where('uor.user_id', $user->id)
            ->select(
                'uor.organization_id',
                'o.name as organization_name',
                'r.name as role_name'
            )
            ->get();


               // if (empty($user->device_id)) {
            //     // login, save device 
            //     $user->device_id = $request->deviceId;
            //     $user->save();
            // } else {
            //     // again login..same deice
            //     if ($user->device_id !== $request->deviceId) {
            //         return response()->json([
            //             "status"  => false,
            //             "message" => "Login not allowed from this device. Please use your registered device."
            //         ], 403);
            //     }
            // }

        // ✅ Token
        $token = $user->createToken("API Token")->plainTextToken;

        return response()->json([
            "status"  => true,
            "message" => "Login successful",
            "data"    => [
                "user"  => $user,
                "roles" => $rolesData, // 👈 important
                "token" => $token
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "status"  => false,
            "message" => "Something went wrong",
            "error"   => $e->getMessage()
        ], 500);
    }
}
    
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
    
            if (!$user) {
                return response()->json([
                    "status"  => false,
                    "message" => "User not authenticated"
                ], 401);
            }
    
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
    
                return response()->json([
                    "status"  => true,
                    "message" => "Logged out successfully"
                ], 200);
            }
    
            return response()->json([
                "status"  => false,
                "message" => "No active session found"
            ], 400);
    
        } catch (\Exception $e) {
            return response()->json([
                "status"  => false,
                "message" => "Something went wrong",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
{
    try {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $otp = rand(100000, 999999);

        $user->update([
            'reset_password_otp' => $otp,
            'reset_password_otp_expires_at' => Carbon::now()->addMinutes(10)
        ]);

        Mail::send('emails.forgot-password', [
            'otp' => $otp,
            'name' => $user->name
        ], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('Reset Your Password');
        });

        return response()->json([
            "status" => true,
            "message" => "Password reset OTP sent to email"
        ]);

    } catch (\Exception $e) {

        return response()->json([
            "status" => false,
            "message" => "Something went wrong",
            "error" => $e->getMessage()
        ], 500);
    }
}

public function verifyOtp(Request $request)
{
    try {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('reset_password_otp', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        if (Carbon::now()->gt($user->reset_password_otp_expires_at)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired'
            ], 400);
        }

        return response()->json([
            "status" => true,
            "message" => "OTP verified successfully"
        ]);

    } catch (\Exception $e) {

        return response()->json([
            "status" => false,
            "message" => "Something went wrong",
            "error" => $e->getMessage()
        ], 500);
    }
}

public function resetPassword(Request $request)
{
    try {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('reset_password_otp', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'reset_password_otp' => null,
            'reset_password_otp_expires_at' => null
        ]);

        return response()->json([
            "status" => true,
            "message" => "Password reset successfully"
        ]);

    } catch (\Exception $e) {

        return response()->json([
            "status" => false,
            "message" => "Something went wrong",
            "error" => $e->getMessage()
        ], 500);
    }
}
}
