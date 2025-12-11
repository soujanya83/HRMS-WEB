<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaxSlabs;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TaxSlabsController extends Controller
{
      public function index()
    {
        try {
            $userId = Auth::id();
            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
            }

            $slabs = TaxSlabs::with('organization')->where('organization_id', $employee->organization_id)
                ->orderBy('financial_year', 'desc')
                ->orderBy('min_income')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Tax slabs retrieved successfully.',
                'data' => $slabs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch tax slabs.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ➕ Create new tax slab
     */
    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'country_code' => 'required|string|max:5',
                'financial_year' => 'required|string|max:9',
                'tax_regime' => 'required|in:old,new',
                'min_income' => 'required|numeric|min:0',
                'max_income' => 'nullable|numeric|gt:min_income',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'surcharge' => 'nullable|numeric|min:0|max:100',
                'cess' => 'nullable|numeric|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            $validated['organization_id'] = $employee->organization_id;

            $slab = TaxSlabs::create($validated);

            return response()->json([
                'status' => true,
                'message' => 'Tax slab created successfully.',
                'data' => $slab
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating tax slab.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔍 Show single tax slab
     */
    public function show($id)
    {
        try {
            $userId = Auth::id();
            $employee = Employee::where('user_id', $userId)->first();
            $slab = TaxSlabs::with('organization')->where('id',$id)->first();

            return response()->json([
                'status' => true,
                'message' => 'Tax slab retrieved successfully.',
                'data' => $slab
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Tax slab not found.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching tax slab.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✏️ Update tax slab
     */
    public function update(Request $request, $id)
    {
        try {
            $slab = TaxSlabs::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'country_code' => 'sometimes|string|max:5',
                'financial_year' => 'sometimes|string|max:9',
                'tax_regime' => 'sometimes|in:old,new',
                'min_income' => 'sometimes|numeric|min:0',
                'max_income' => 'nullable|numeric|gt:min_income',
                'tax_rate' => 'sometimes|numeric|min:0|max:100',
                'surcharge' => 'nullable|numeric|min:0|max:100',
                'cess' => 'nullable|numeric|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
            }

            $slab->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Tax slab updated successfully.',
                'data' => $slab
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Tax slab not found.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating tax slab.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ❌ Delete tax slab
     */
    public function destroy($id)
    {
        try {
            $slab = TaxSlabs::findOrFail($id);
            $slab->delete();

            return response()->json([
                'status' => true,
                'message' => 'Tax slab deleted successfully.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Tax slab not found.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting tax slab.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
