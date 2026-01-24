<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bonus;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

class BonusesController extends Controller
{
    public function index(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->first();
        $orgId = $employee->organization_id;

        $bonuses = Bonus::with(['employee', 'approver', 'payroll'])
            ->where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bonuses
        ]);
    }

    /**
     * Store New Bonus
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'  => 'required|exists:employees,id',
            'type'         => 'required|in:bonus,incentive,festival,other',
            'amount'       => 'required|numeric|min:1',
            'reason'       => 'nullable|string|max:255',
            'bonus_month'  => 'nullable|string|max:10', // example: 10-2025
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $employee = Employee::findOrFail($request->employee_id);
       $create_employee = Employee::where('user_id', Auth::id())->first();



        $data = $validator->validated();
        $data['organization_id'] = $employee->organization_id;
        $data['status'] = 'pending';
        $data['created_by'] =  $create_employee->id;

        $bonus = Bonus::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Bonus/Incentive created successfully',
            'data' => $bonus
        ], 201);
    }

    /**
     * Show Single Bonus
     */
    public function show($id)
    {
        $bonus = Bonus::with(['employee', 'approver', 'payroll'])->find($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bonus record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bonus
        ]);
    }

    /**
     * Update Bonus
     */
    public function update(Request $request, $id)
    {
        $bonus = Bonus::find($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bonus not found'
            ], 404);
        }

        if ($bonus->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending bonuses can be updated'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type'        => 'sometimes|in:bonus,incentive,festival,other',
            'amount'      => 'sometimes|numeric|min:1',
            'reason'      => 'nullable|string|max:255',
            'bonus_month' => 'nullable|string|max:10',
        ]);

        $bonus->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bonus updated successfully',
            'data' => $bonus
        ]);
    }

    /**
     * Approve Bonus
     */
    public function approve($id)
    {
        $bonus = Bonus::find($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bonus not found'
            ], 404);
        }

        $bonus->update([
            'status' => 'approved',
            'approved_by' => Employee::where('user_id', Auth::id())->first()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bonus approved successfully',
            'data' => $bonus
        ]);
    }

    /**
     * Reject Bonus
     */
    public function reject($id)
    {
        $bonus = Bonus::find($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bonus not found'
            ], 404);
        }

        $bonus->update([
            'status' => 'rejected',
            'approved_by' => Employee::where('user_id', Auth::id())->first()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bonus rejected successfully',
            'data' => $bonus
        ]);
    }

    /**
     * Delete Bonus
     */
    public function destroy($id)
    {
        $bonus = Bonus::find($id);

        if (!$bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Bonus not found'
            ], 404);
        }

        if ($bonus->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Approved bonuses cannot be deleted'
            ], 403);
        }

        $bonus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bonus deleted successfully'
        ]);
    }
}
