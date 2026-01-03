<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalaryStructure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;   
use Illuminate\Support\Facades\Auth;

class SalaryStructureController extends Controller
{
       public function index()
    {
        $structures = SalaryStructure::with(['employee', 'organization', 'components.componentType'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $structures
        ]);
    }

    // 🔹 Create
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'nullable|exists:employees,id',
                'grade_level' => 'nullable|string|max:50',
                'base_salary' => 'required|numeric|min:0',
                'currency' => 'required|string|max:10',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Auth::user()->employee;
            $organization_id = $employee->organization_id;
            $data = $validator->validated();
            $data['organization_id'] = $organization_id;

            $structure = SalaryStructure::create(  $data);

            return response()->json([
                'status' => true,
                'message' => 'Salary structure created successfully.',
                'data' => $structure
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error creating structure', 'error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Update
    public function update(Request $request, $id)
    {
        try {
            $structure = SalaryStructure::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'grade_level' => 'nullable|string|max:50',
                'base_salary' => 'numeric|min:0',
                'currency' => 'string|max:10',
                'is_active' => 'boolean'
            ]);

            $structure->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Salary structure updated.',
                'data' => $structure
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error updating structure', 'error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Delete
    public function destroy($id)
    {
        try {
            $structure = SalaryStructure::findOrFail($id);
            $structure->delete();

            return response()->json(['status' => true, 'message' => 'Structure deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error deleting structure', 'error' => $e->getMessage()], 500);
        }
    }
}
