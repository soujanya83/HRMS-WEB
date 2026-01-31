<?php

namespace App\Http\Controllers\API\V1\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use App\Models\OrganizationAttendanceRule;

class OrganizationAttendanceRuleController extends Controller
{
    public function index()
{
    try {
        // Get logged-in user ID
        $user_id = Auth::user()->id;

        // Get employee's organization
        $employee = Employee::where('user_id', $user_id)->select('organization_id')->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee not found.'
            ], 404);
        }

        $organization_id = $employee->organization_id;

        // Get attendance rules for the organization
        $rules = OrganizationAttendanceRule::where('organization_id', $organization_id)->get();

        return response()->json([
            'status' => true,
            'message' => 'Attendance Rules Retrived Successfully',
            'data' => $rules
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // Fetch rules by organization id
    public function getByOrganization($organization_id)
    {
        try {
            $rules = OrganizationAttendanceRule::where('organization_id', $organization_id)->get();

            return response()->json([
                'status' => true,
                'message' => 'Attendance Rules Retrieved Successfully',
                'data' => $rules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function store(Request $request)
    {
        try {
            $user_id = Auth::id();
            $user = Auth::user();
            $organizationId = $user->organizations()->first()?->id;
            // $organizationIds = $user->organizations->pluck('id');


            // $employee = Employee::where('user_id', $user_id)->first();
            $rules = OrganizationAttendanceRule::where('organization_id', $organizationId)->get();

            if ($rules->isNotEmpty()) {
                return response()->json(['status' => false, 'message' => 'Organization already has an attendance rule.'], 404);
            }

            $validated = $request->validate([
                'shift_name' => 'nullable|string|max:50',
                'check_in' => 'required|date_format:H:i',
                'check_out' => 'required|date_format:H:i|after:check_in',
                'break_start' => 'nullable|date_format:H:i',
                'break_end' => 'nullable|date_format:H:i|after:break_start',
                'late_grace_minutes' => 'nullable|integer|min:0',
                'half_day_after_minutes' => 'nullable|integer|min:0',
                'allow_overtime' => 'boolean',
                'overtime_rate' => 'nullable|numeric|min:0',
                'weekly_off_days' => 'nullable|string',
                'flexible_hours' => 'boolean',
                'absent_after_minutes' => 'nullable|integer|min:0',
                'is_remote_applicable' => 'boolean',
                'rounding_minutes' => 'nullable|integer|min:0',
                'cross_midnight' => 'boolean',
                'late_penalty_amount' => 'nullable|numeric|min:0',
                'absent_penalty_amount' => 'nullable|numeric|min:0',
                'relaxation' => 'nullable|string|max:255',
                'policy_notes' => 'nullable|string',
                'policy_version' => 'nullable|string|max:50',
                'is_active' => 'boolean',
            ]);

            $validated['organization_id'] = $organizationId;
            $validated['created_by'] = $user_id;

            $rule = OrganizationAttendanceRule::create($validated);

            return response()->json(['status' => true, 'message' => 'Attendance rule created.', 'data' => $rule]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create rule.', 'error' => $e->getMessage()], 500);
        }
    }

      public function show($id)
    {
        try {
            $rule = OrganizationAttendanceRule::find($id);

            if (!$rule) {
                return response()->json(['status' => false, 'message' => 'Rule not found.'], 404);
            }

            return response()->json(['status' => true, 'data' => $rule]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong.', 'error' => $e->getMessage()], 500);
        }
    }

    // Update a rule
    public function update(Request $request, $id)
    {
        try {
            $rule = OrganizationAttendanceRule::find($id);

            if (!$rule) {
                return response()->json(['status' => false, 'message' => 'Rule not found.'], 404);
            }

            $validated = $request->validate([
                'shift_name' => 'nullable|string|max:50',
                'check_in' => 'nullable|date_format:H:i',
                'check_out' => 'nullable|date_format:H:i|after:check_in',
                'break_start' => 'nullable|date_format:H:i',
                'break_end' => 'nullable|date_format:H:i|after:break_start',
                'late_grace_minutes' => 'nullable|integer|min:0',
                'half_day_after_minutes' => 'nullable|integer|min:0',
                'allow_overtime' => 'boolean',
                'overtime_rate' => 'nullable|numeric|min:0',
                'weekly_off_days' => 'nullable|string',
                'flexible_hours' => 'boolean',
                'absent_after_minutes' => 'nullable|integer|min:0',
                'is_remote_applicable' => 'boolean',
                'rounding_minutes' => 'nullable|integer|min:0',
                'cross_midnight' => 'boolean',
                'late_penalty_amount' => 'nullable|numeric|min:0',
                'absent_penalty_amount' => 'nullable|numeric|min:0',
                'relaxation' => 'nullable|string|max:255',
                'policy_notes' => 'nullable|string',
                'policy_version' => 'nullable|string|max:50',
                'is_active' => 'boolean',
            ]);

            $rule->update($validated);

            return response()->json(['status' => true, 'message' => 'Attendance rule updated.', 'data' => $rule]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update rule.', 'error' => $e->getMessage()], 500);
        }
    }

    // Delete a rule
    public function destroy($id)
    {
        try {
            $rule = OrganizationAttendanceRule::find($id);

            if (!$rule) {
                return response()->json(['status' => false, 'message' => 'Rule not found.'], 404);
            }

            $rule->delete();

            return response()->json(['status' => true, 'message' => 'Attendance rule deleted.']);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete rule.', 'error' => $e->getMessage()], 500);
        }
    }

}
