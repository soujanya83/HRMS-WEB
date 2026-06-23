<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ProhibitionNoticeDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            
            // New fields validation
            'declaration_person_name' => 'nullable|string',
            'declaration_person_signature_base64' => 'nullable|string',
            'witness_signature_base64' => 'nullable|string',
        ]);

        // Process Base64 Signatures
        if (!empty($validatedData['declaration_person_signature_base64'])) {
            $validatedData['declaration_person_signature_path'] = $this->saveSignature($validatedData['declaration_person_signature_base64'], 'decl_person');
            unset($validatedData['declaration_person_signature_base64']);
        }

        if (!empty($validatedData['witness_signature_base64'])) {
            $validatedData['witness_signature_path'] = $this->saveSignature($validatedData['witness_signature_base64'], 'witness');
            unset($validatedData['witness_signature_base64']);
        }

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
            
            // New fields validation
            'declaration_person_name' => 'sometimes|string|nullable',
            'declaration_person_signature_base64' => 'sometimes|string|nullable',
            'witness_signature_base64' => 'sometimes|string|nullable',
        ]);

        // Process Base64 Signatures for Update (Only replace if it's a new base64 string)
        if (!empty($validatedData['declaration_person_signature_base64']) && !str_starts_with($validatedData['declaration_person_signature_base64'], 'http')) {
            if ($declaration->declaration_person_signature_path) {
                Storage::disk('public')->delete($declaration->declaration_person_signature_path);
            }
            $validatedData['declaration_person_signature_path'] = $this->saveSignature($validatedData['declaration_person_signature_base64'], 'decl_person');
        }
        unset($validatedData['declaration_person_signature_base64']);

        if (!empty($validatedData['witness_signature_base64']) && !str_starts_with($validatedData['witness_signature_base64'], 'http')) {
            if ($declaration->witness_signature_path) {
                Storage::disk('public')->delete($declaration->witness_signature_path);
            }
            $validatedData['witness_signature_path'] = $this->saveSignature($validatedData['witness_signature_base64'], 'witness');
        }
        unset($validatedData['witness_signature_base64']);

        $declaration->update($validatedData);

        return response()->json($declaration);
    }

    // Delete a declaration
    public function destroy($id)
    {
        $declaration = ProhibitionNoticeDeclaration::findOrFail($id);
        
        // Clean up signature images from the server storage
        if ($declaration->declaration_person_signature_path) {
            Storage::disk('public')->delete($declaration->declaration_person_signature_path);
        }
        if ($declaration->witness_signature_path) {
            Storage::disk('public')->delete($declaration->witness_signature_path);
        }

        $declaration->delete();

        return response()->json(['message' => 'Declaration deleted successfully']);
    }

    /**
     * Helper to decode Base64 and save as image
     */
    private function saveSignature($base64Image, $prefix)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('Invalid image type');
            }
            $base64Image = base64_decode($base64Image);
            if ($base64Image === false) {
                throw new \Exception('Base64 decode failed');
            }
        } else {
            throw new \Exception('Invalid Base64 signature string');
        }

        $fileName = 'prohibition_signatures/' . $prefix . '_' . Str::random(10) . '.' . $type;
        Storage::disk('public')->put($fileName, $base64Image);

        return $fileName;
    }
}