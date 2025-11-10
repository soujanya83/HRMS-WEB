<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalaryComponentTypes;
use Illuminate\Support\Facades\Validator;

class SalaryComponentTypesController extends Controller
{
      public function index()
    {
        return response()->json([
            'status' => true,
            'data' => SalaryComponentTypes::orderBy('id', 'desc')->get(),
        ]);
    }

    // 🔹 Create new component
    public function store(Request $request)
    {
        try {
            $validated = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:salary_component_types,name',
                'category' => 'required|in:earning,deduction,benefit',
                'is_taxable' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:255',
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validated->errors()
                ], 422);
            }

            $component = SalaryComponentTypes::create($validated->validated());

            return response()->json([
                'status' => true,
                'message' => 'Salary component created successfully.',
                'data' => $component
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating salary component.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 🔹 Update component
    public function update(Request $request, $id)
    {
        try {
            $component = SalaryComponentTypes::findOrFail($id);

            $validated = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100|unique:salary_component_types,name,' . $component->id,
                'category' => 'sometimes|in:earning,deduction,benefit',
                'is_taxable' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:255',
            ]);

            $component->update($validated->validated());

            return response()->json([
                'status' => true,
                'message' => 'Salary component updated successfully.',
                'data' => $component
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating salary component.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 🔹 Delete
    public function destroy($id)
    {
        try {
            $component = SalaryComponentTypes::findOrFail($id);
            $component->delete();

            return response()->json([
                'status' => true,
                'message' => 'Salary component deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting salary component.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
