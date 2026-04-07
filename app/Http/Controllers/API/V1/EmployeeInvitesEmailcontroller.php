<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmployeeInviteMail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Employee\Employee;
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
            $rawPassword = 12345678;

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
        $link = "https://chrispp.com/apply/" . $employee->id;

        // ✅ Email Data
        $data = [
            'name' => $request->name,
            'last_name' => $request->last_name,
            'link' => $link
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
}
