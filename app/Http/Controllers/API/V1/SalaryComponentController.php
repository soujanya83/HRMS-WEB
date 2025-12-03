<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalaryStructureComponents;
use Illuminate\Support\Facades\Validator;

class SalaryComponentController extends Controller
{
    public function index()
    {
        $components = SalaryStructureComponents::with(['structure', 'componentType'])->get();
        return response()->json(['status' => true, 'data' => $components]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salary_structure_id' => 'required|exists:salary_structures,id',
                'component_type_id' => 'required|exists:salary_component_types,id',
                'percentage' => 'nullable|numeric|min:0|max:100',
                'amount' => 'nullable|numeric|min:0',
                'is_custom' => 'boolean',
                'remarks' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
            }

            $component = SalaryStructureComponents::create($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Structure component added successfully.',
                'data' => $component
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error adding component.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $component = SalaryStructureComponents::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'percentage' => 'nullable|numeric|min:0|max:100',
                'amount' => 'nullable|numeric|min:0',
                'is_custom' => 'boolean',
                'remarks' => 'nullable|string|max:255'
            ]);

            $component->update($validator->validated());

            return response()->json(['status' => true, 'message' => 'Component updated.', 'data' => $component]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error updating component', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            SalaryStructureComponents::findOrFail($id)->delete();
            return response()->json(['status' => true, 'message' => 'Component deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error deleting component', 'error' => $e->getMessage()], 500);
        }
    }
}
