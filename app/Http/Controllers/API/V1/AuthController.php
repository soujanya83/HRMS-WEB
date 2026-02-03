<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

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
                'password' => 'required|string|min:6',
                'deviceId' => 'nullable|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation errors',
                    'errors'  => $validator->errors()
                ], 422);
            }
    
            // ✅ Check if user exists
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
            if (empty($user->device_id)) {
                // login, save device 
                $user->device_id = $request->deviceId;
                $user->save();
            } else {
                // again login..same deice
                if ($user->device_id !== $request->deviceId) {
                    return response()->json([
                        "status"  => false,
                        "message" => "Login not allowed from this device. Please use your registered device."
                    ], 403);
                }
            }

            $token = $user->createToken("API Token")->plainTextToken;
    
            return response()->json([
                "status"  => true,
                "message" => "Login successful",
                "data"    => [
                    "user"  => $user,
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
}
