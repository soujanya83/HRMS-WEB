<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmployeeInviteMail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Employee\Employee;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EmployeeInvitesEmailcontroller extends Controller
{
    public function sendInvite(Request $request)
{
    try {
        // ✅ Validation
        $request->validate([
            'name' => 'required|string|max:190',
            'middle_name' => 'nullable|string|max:190',
            'last_name' => 'required|string|max:190',
            'email' => 'required|email|max:190',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        DB::beginTransaction();

        $email = $request->email;
        $organizationId = $request->organization_id;

        // ✅ Check if user exists
        $user = User::where('email', $email)->first();

        $employee = null;
        $createdUser = false;

        if ($user) {

            // ✅ Check if employee exists for this user
            $employee = Employee::where('user_id', $user->id)->first();

            if (!$employee) {
                // ❗ User exists but employee not → create employee
                $employee = Employee::create([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'first_name' => $request->name,
                    'middle_name' => $request->middle_name,
                    'last_name' => $request->last_name,
                    'personal_email' => $email,
                    // 'employee_code' => 'EMP' . time(), // simple unique code
                    // 'joining_date' => now(),
                    // 'employment_type' => 'Full-time',
                    // 'status' => 'On Probation',
                ]);

                // assign role
                $user->assignRoleForOrganization('employee', $organizationId);
            }

        } else {
            // ✅ Create new user
            $rawPassword = generateStrongPassword(10); // simple random password

            $user = User::create([
                'name' => trim($request->name . ' ' . $request->last_name),
                'email' => $email,
                'password' => Hash::make($rawPassword),
            ]);

            $createdUser = true;

            // assign role
            $user->assignRoleForOrganization('employee', $organizationId);

            // ✅ Create employee
            $employee = Employee::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'first_name' => $request->name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'personal_email' => $email,
                // 'employee_code' => 'EMP' . time(),
                // 'joining_date' => now(),
                // 'employment_type' => 'Full-time',
                // 'status' => 'On Probation',
            ]);
        }

        DB::commit();

        // ✅ Generate link with employee ID
        $link = "https://chrispp.com/login";
        $organization_name = Organization::find($organizationId)->name;
    

        // ✅ Email Data
        $data = [
            'name' => $request->name,
            'password' => $rawPassword,
            'last_name' => $request->last_name,
            'organization_name' => $organization_name,
            'link' => $link,
            'email' => $email // ✅ ADD THIS
        ];

        // ✅ Send Email
        Mail::to($email)->send(new EmployeeInviteMail($data));

        return response()->json([
            'status' => true,
            'message' => 'Invite sent successfully',
            'data' => [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'link' => $link,
                'new_user_created' => $createdUser
            ]
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ]);
    }
}

private function generateStrongPassword($length = 10)
{
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_-=+;:,.?';

    // Ensure at least one from each category
    $password = 
        $lowercase[rand(0, strlen($lowercase) - 1)] .
        $uppercase[rand(0, strlen($uppercase) - 1)] .
        $numbers[rand(0, strlen($numbers) - 1)] .
        $special[rand(0, strlen($special) - 1)];

    // Remaining characters
    $all = $lowercase . $uppercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[rand(0, strlen($all) - 1)];
    }

    // Shuffle to avoid predictable pattern
    return str_shuffle($password);
}


}
