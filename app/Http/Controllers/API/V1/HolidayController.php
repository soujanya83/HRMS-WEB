<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HolidayModel;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class HolidayController extends Controller
{
       public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
            }

            $holidays = HolidayModel::where('organization_id', $employee->organization_id)
                ->orderBy('holiday_date', 'asc')
                ->get();

            return response()->json(['status' => true, 'data' => $holidays]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch holidays.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created holiday.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
            }

            $validated = $request->validate([
                'holiday_name' => 'required|string|max:255',
                'holiday_date' => 'required|date',
                'holiday_type' => 'required|in:National,Regional,Company',
                'is_recurring' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            $validated['organization_id'] = $employee->organization_id;
            $validated['created_by'] = $employee->id;

            $holiday = HolidayModel::create($validated);

            return response()->json(['status' => true, 'message' => 'Holiday created successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create holiday.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a specific holiday.
     */
    public function show($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            return response()->json(['status' => true, 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch holiday details.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a holiday.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            $validated = $request->validate([
                'holiday_name' => 'nullable|string|max:255',
                'holiday_date' => 'nullable|date',
                'holiday_type' => 'nullable|in:National,Regional,Company',
                'is_recurring' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ]);

            $holiday->update($validated);

            return response()->json(['status' => true, 'message' => 'Holiday updated successfully.', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update holiday.', 'error' => $e->getMessage()], 500);
        }
    }

    public function partialUpdate(Request $request, $id): JsonResponse
    {
        try {
            // 🔍 Find holiday record
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found.'
                ], 404);
            }

            // ✅ Validate only the fields that might be passed
            $validated = $request->validate([
                'is_recurring' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            // Default message
            $message = 'Holiday updated successfully.';

            // 🟢 Handle is_active toggle
            if ($request->has('is_active')) {
                $holiday->is_active = $validated['is_active'];
                $message = $validated['is_active']
                    ? 'Holiday activated successfully.'
                    : 'Holiday deactivated successfully.';
            }

            // 🔁 Handle is_recurring toggle
            if ($request->has('is_recurring')) {
                $holiday->is_recurring = $validated['is_recurring'];
                $message = $validated['is_recurring']
                    ? 'Holiday recurring enabled successfully.'
                    : 'Holiday recurring disabled successfully.';
            }

            // 💾 Save only if any changes are made
            if ($holiday->isDirty()) {
                $holiday->save();
            }

            // ✅ Response
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $holiday
            ]);
        } catch (\Exception $e) {
            // ❌ Exception handler
            return response()->json([
                'status' => false,
                'message' => 'Failed to update holiday.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete a holiday.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $holiday = HolidayModel::find($id);

            if (!$holiday) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }

            $holiday->delete();

            return response()->json(['status' => true, 'message' => 'Holiday deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete holiday.', 'error' => $e->getMessage()], 500);
        }
    }
}
