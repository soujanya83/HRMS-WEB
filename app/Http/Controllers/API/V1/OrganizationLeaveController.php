<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\OrganizationLeave;
use App\Models\Employee\Leave;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrganizationLeaveController extends Controller
{

    /**
     * Display a listing of the company leaves.
     */
    public function index(): JsonResponse
    {
        try {
            $leaves = OrganizationLeave::orderBy('id', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Leave types fetched successfully.',
                'data' => $leaves
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching leave types.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created leave type.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'leave_type' => 'required|string|max:100|unique:organization_leaves,leave_type',
                'granted_days' => 'required|integer|min:0',
                'carry_forward' => 'boolean',
                'max_carry_forward' => 'nullable|integer|min:0',
                'paid' => 'boolean',
                'requires_approval' => 'boolean',
                'is_active' => 'boolean',
                'allow_half_day' => 'boolean',
                'gender_applicable' => 'nullable|in:male,female,all',
                'description' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

       $employee = Employee::select('organization_id')
            ->where('user_id', auth()->id())
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee record not found for this user.'
            ], 404);
        }

        $created_by = Employee::where('user_id',auth()->id())->first();
        $validated = $validator->validated();
        $validated['organization_id'] = $employee->organization_id;
        $validated['created_by'] = $created_by->id;

        // ✅ Create record
        $leave = OrganizationLeave::create($validated);


            return response()->json([
                'status' => true,
                'message' => 'Leave type created successfully.',
                'data' => $leave
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while creating leave type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific leave type.
     */
    public function show($id): JsonResponse
    {
        try {
            $leave = OrganizationLeave::find($id);

            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave type not found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Leave type fetched successfully.',
                'data' => $leave
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching leave type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a leave type.
     */
 public function update(Request $request, $id): JsonResponse
{
    try {
        // 1️⃣ Find the leave record
        $leave = OrganizationLeave::find($id);

        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave type not found.'
            ], 404);
        }

        // 2️⃣ Validate input from form-data
        $validator = Validator::make($request->all(), [
            'leave_type' => 'sometimes|string|max:100|unique:organization_leaves,leave_type,' . $id,
            'granted_days' => 'sometimes|integer|min:0',
            'carry_forward' => 'sometimes|boolean',
            'max_carry_forward' => 'nullable|integer|min:0',
            'paid' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'allow_half_day' => 'sometimes|boolean',
            'gender_applicable' => 'nullable|in:male,female,all',
            'description' => 'nullable|string|max:500',
        ]);
     

        // 3️⃣ Handle validation failure
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }
$validated = $validator->validated();
        // dd($validated);
        // 4️⃣ Update using validated data
        $leave->update($validator->validated());

        // 5️⃣ Return success
        return response()->json([
            'status' => true,
            'message' => 'Leave type updated successfully.',
            'data' => $leave
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong while updating leave type.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function partialUpdate(Request $request, $id)
{
    try {
        // Validate input
        $validated = $request->validate([
            'is_active' => 'boolean',
            'carry_forward' => 'boolean',
            'paid' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_half_day' => 'boolean',
        ]);

        // Find the leave record
        $leave = OrganizationLeave::find($id);
        if (!$leave) {
            return response()->json([
                'status' => false,
                'message' => 'Leave not found.'
            ], 404);
        }

        // Update only fields present in the request
        foreach ($validated as $key => $value) {
            if ($request->has($key)) {
                $leave->$key = $value;
            }
        }

        $leave->save();

        return response()->json([
            'status' => true,
            'message' => 'Leave updated successfully.',
            'data' => $leave
        ]);

    } catch (\Exception $e) {
        Log::error('Partial Update Error: '.$e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong.'
        ], 500);
    }
}

    /**
     * Delete a leave type.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $leave = OrganizationLeave::find($id);

            if (!$leave) {
                return response()->json([
                    'status' => false,
                    'message' => 'Leave type not found.'
                ], 404);
            }

            $leave->delete();

            return response()->json([
                'status' => true,
                'message' => 'Leave type deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while deleting leave type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
