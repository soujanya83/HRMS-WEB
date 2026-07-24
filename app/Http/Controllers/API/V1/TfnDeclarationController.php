<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\TfnDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TfnDeclarationController extends Controller
{
    public function show($id)
    {
        return response()->json(TfnDeclaration::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(TfnDeclaration::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        // Handle Base64 Signatures
        if (!empty($data['payee_signature_base64'])) {
            $data['payee_signature_path'] = $this->saveSignature($data['payee_signature_base64'], 'payee');
        }
        if (!empty($data['payer_signature_base64'])) {
            $data['payer_signature_path'] = $this->saveSignature($data['payer_signature_base64'], 'payer');
        }

        $declaration = TfnDeclaration::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            $data
        );

        return response()->json($declaration, 201);
    }

    public function update(Request $request, $id)
    {
        $declaration = TfnDeclaration::findOrFail($id);
        $data = $request->validate($this->validationRules(true));

        // Handle Base64 Signatures on Update (Only upload if it's a new base64 string, not an existing URL)
        if (!empty($data['payee_signature_base64']) && !str_starts_with($data['payee_signature_base64'], 'http')) {
            $data['payee_signature_path'] = $this->saveSignature($data['payee_signature_base64'], 'payee');
        }
        if (!empty($data['payer_signature_base64']) && !str_starts_with($data['payer_signature_base64'], 'http')) {
            $data['payer_signature_path'] = $this->saveSignature($data['payer_signature_base64'], 'payer');
        }

        $declaration->update($data);

        return response()->json($declaration);
    }

    public function destroy($id)
    {
        $declaration = TfnDeclaration::findOrFail($id);
        
        // Optionally delete the images from storage
        if ($declaration->payee_signature_path) Storage::disk('public')->delete($declaration->payee_signature_path);
        if ($declaration->payer_signature_path) Storage::disk('public')->delete($declaration->payer_signature_path);
        
        $declaration->delete();

        return response()->json(['message' => 'TFN Declaration deleted successfully']);
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

        $fileName = 'signatures/' . $prefix . '_' . Str::random(10) . '.' . $type;
        Storage::disk('public')->put($fileName, $base64Image);

        return $fileName;
    }

    /**
     * Validation Rules
     */
    private function validationRules($isUpdate = false)
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'employee_id' => "$required|integer",
            'organization_id' => "$required|integer",
            
            // Payee
            'tfn_number' => 'nullable|string|max:9',
            'tfn_exemption_type' => 'nullable|in:applied,under_18,pensioner',
            'title' => 'nullable|string',
            'surname' => "nullable|string",
            'first_name' => "nullable|string",
            'other_names' => 'nullable|string',
            'previous_name' => 'nullable|string',
            'dob' => 'nullable|date',
            'payee_address' => 'nullable|string',
            'payee_suburb' => 'nullable|string',
            'payee_state' => 'nullable|string',
            'payee_postcode' => 'nullable|string',
            'payee_email' => 'nullable|email',
            'employment_basis' => 'nullable|in:full_time,part_time,labour_hire,superannuation,casual',
            'residency_status' => 'nullable|in:australian_resident,foreign_resident,working_holiday_maker',
            'claim_tax_free_threshold' => "nullable|boolean",
            'has_help_debt' => "nullable|boolean",
            'payee_signature_base64' => 'nullable|string',
            'payee_declaration_date' => 'nullable|date',

            // Payer
            'payer_abn' => 'nullable|string',
            'payer_branch_number' => 'nullable|string',
            'payer_applied_for_abn' => 'nullable|boolean',
            'payer_legal_name' => 'nullable|string',
            'payer_address' => 'nullable|string',
            'payer_suburb' => 'nullable|string',
            'payer_state' => 'nullable|string',
            'payer_postcode' => 'nullable|string',
            'payer_email' => 'nullable|email',
            'payer_contact_person' => 'nullable|string',
            'payer_phone' => 'nullable|string',
            'no_longer_makes_payments' => 'nullable|boolean',
            'payer_signature_base64' => 'nullable|string',
            'payer_declaration_date' => 'nullable|date',
        ];
    }
}