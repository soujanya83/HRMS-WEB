<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class AuthController extends Controller
{
     // Register API
     public function register(Request $request)
     {
         $request->validate([
             'name' => 'required|string',
             'email' => 'required|string|email|unique:users',
             'password' => 'required|min:6',
         ]);
 
         $user = User::create([
             'name'     => $request->name,
             'email'    => $request->email,
             'password' => bcrypt($request->password),
         ]);
 
         return response()->json([
             'message' => 'User registered successfully!',
             'user'    => $user,
         ], 201);
     }
 
     // Login API
     public function login(Request $request)
     {
         if (!Auth::attempt($request->only('email', 'password'))) {
             return response()->json(['message' => 'Invalid credentials'], 401);
         }
 
         $user = Auth::user();
         $token = $user->createToken('auth_token')->plainTextToken;
 
         return response()->json([
             'message' => 'Login successful',
             'access_token' => $token,
             'token_type' => 'Bearer',
             'user' => $user,
         ]);
     }
 
     // Get logged-in user
     public function me(Request $request)
     {
         return response()->json($request->user());
     }
 
     // Logout API
     public function logout(Request $request)
     {
         $request->user()->tokens()->delete();
         return response()->json(['message' => 'Logged out']);
     }
}
