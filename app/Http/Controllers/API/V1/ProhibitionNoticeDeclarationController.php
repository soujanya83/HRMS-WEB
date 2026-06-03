<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ProhibitionNoticeDeclaration;
use Illuminate\Http\Request;

class ProhibitionNoticeDeclarationController extends Controller
{
    // Fetch a specific declaration by its ID
    public function show($id)
    {
        $declaration = ProhibitionNoticeDeclaration::findOrFail($id);
        return response()->json($declaration);
    }

    // Fetch the declaration for a specific employee
    public function getByEmployee($employeeId)
    {
        $declaration = ProhibitionNoticeDeclaration::where('employee_id', $employeeId)->firstOrFail();
        return response()->json($declaration);
    }

    // Create a new declaration
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer',
            'organization_id' => 'required|integer',
            'title' => 'nullable|string',
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'mobile_number' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'dob' => 'nullable|date',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'suburb' => 'nullable|string',
            'state' => 'nullable|string',
            'postcode' => 'nullable|string',
            'former_names' => 'nullable|string',
            'is_subject_to_prohibition' => 'required|boolean',
            'is_prohibited_other_law' => 'required|boolean',
            'declaration_place' => 'nullable|string',
            'declaration_date' => 'nullable|date',
            'witness_name' => 'nullable|string',
        ]);

        // Use updateOrCreate so an employee doesn't get duplicate forms
        $declaration = ProhibitionNoticeDeclaration::updateOrCreate(
            ['employee_id' => $validatedData['employee_id']],
            $validatedData
        );

        return response()->json($declaration, 201);
    }

    // Update an existing declaration by ID
    public function update(Request $request, $id)
    {
        $declaration = ProhibitionNoticeDeclaration::findOrFail($id);
        
        // Use similar validation as store, but 'sometimes' to allow partial updates
        $validatedData = $request->validate([
            'title' => 'sometimes|string|nullable',
            'last_name' => 'sometimes|string',
            'first_name' => 'sometimes|string',
            'mobile_number' => 'sometimes|string|nullable',
            'phone_number' => 'sometimes|string|nullable',
            'dob' => 'sometimes|date|nullable',
            'email' => 'sometimes|email|nullable',
            'address' => 'sometimes|string|nullable',
            'suburb' => 'sometimes|string|nullable',
            'state' => 'sometimes|string|nullable',
            'postcode' => 'sometimes|string|nullable',
            'former_names' => 'sometimes|string|nullable',
            'is_subject_to_prohibition' => 'sometimes|boolean',
            'is_prohibited_other_law' => 'sometimes|boolean',
            'declaration_place' => 'sometimes|string|nullable',
            'declaration_date' => 'sometimes|date|nullable',
            'witness_name' => 'sometimes|string|nullable',
        ]);

        $declaration->update($validatedData);

        return response()->json($declaration);
    }

    // Delete a declaration
    public function destroy($id)
    {
        $declaration = ProhibitionNoticeDeclaration::findOrFail($id);
        $declaration->delete();

        return response()->json(['message' => 'Declaration deleted successfully']);
    }
}