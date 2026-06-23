<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffRecord;
use Illuminate\Http\Request;

class StaffRecordController extends Controller
{
    // Fetch a specific staff record by its ID
    public function show($id)
    {
        $record = StaffRecord::findOrFail($id);
        return response()->json($record);
    }

    // Fetch the staff record for a specific employee
    public function getByEmployee($employeeId)
    {
        $record = StaffRecord::where('employee_id', $employeeId)->firstOrFail();
        return response()->json($record);
    }

    // Create a new staff record
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer',
            'organization_id' => 'required|integer',
            'name' => 'required|string',
            'dob' => 'nullable|date',
            'email' => 'nullable|email',
            'mobile_number' => 'nullable|string',
            'address' => 'nullable|string',
            'relevant_qualifications' => 'nullable|string',
            'qualifications_copies_attached' => 'required|boolean',
            'other_approved_training' => 'nullable|string',
            'training_copies_attached' => 'required|boolean',
            'wwc_wwvp_check_number' => 'nullable|string',
            'status_check_date' => 'nullable|date',
            'certified_supervisor_number' => 'nullable|string',
        ]);

        // Use updateOrCreate so an employee doesn't get duplicate forms
        $record = StaffRecord::updateOrCreate(
            ['employee_id' => $validatedData['employee_id']],
            $validatedData
        );

        return response()->json($record, 201);
    }

    // Update an existing staff record by ID
    public function update(Request $request, $id)
    {
        $record = StaffRecord::findOrFail($id);
        
        $validatedData = $request->validate([
            'name' => 'sometimes|string',
            'dob' => 'sometimes|date|nullable',
            'email' => 'sometimes|email|nullable',
            'mobile_number' => 'sometimes|string|nullable',
            'address' => 'sometimes|string|nullable',
            'relevant_qualifications' => 'sometimes|string|nullable',
            'qualifications_copies_attached' => 'sometimes|boolean',
            'other_approved_training' => 'sometimes|string|nullable',
            'training_copies_attached' => 'sometimes|boolean',
            'wwc_wwvp_check_number' => 'sometimes|string|nullable',
            'status_check_date' => 'sometimes|date|nullable',
            'certified_supervisor_number' => 'sometimes|string|nullable',
        ]);

        $record->update($validatedData);

        return response()->json($record);
    }

    // Delete a staff record
    public function destroy($id)
    {
        $record = StaffRecord::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Staff record deleted successfully']);
    }
}