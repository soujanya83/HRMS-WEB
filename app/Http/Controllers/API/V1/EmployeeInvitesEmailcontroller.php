<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmployeeInviteMail;
use Illuminate\Support\Str;

class EmployeeInvitesEmailcontroller extends Controller
{
     public function sendInvite(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'last_name' => 'required|string',
                'email' => 'required|email',
            ]);

            // Generate unique token
            $token = Str::random(40);

            // Example onboarding link
            $link = url('/employee/onboarding?token=' . $token);

            $data = [
                'name' => $request->name,
                'last_name' => $request->last_name,
                'link' => $link
            ];

            Mail::to($request->email)->send(new EmployeeInviteMail($data));

            return response()->json([
                'status' => true,
                'message' => 'Email sent successfully',
                'link' => $link // optional for testing
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
