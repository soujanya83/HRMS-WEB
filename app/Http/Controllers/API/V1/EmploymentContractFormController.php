<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\EmploymentContractForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmploymentContractFormController extends Controller
{
    public function show($id)
    {
        return response()->json(EmploymentContractForm::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(EmploymentContractForm::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer',
            'organization_id' => 'required|integer',
            'contract_date' => 'nullable|date',
            'educator_name' => 'required|string',
            'address' => 'nullable|string',
            'position' => 'nullable|string',
            
            'disclosure_date' => 'nullable|date',
            'disclosure_signature_base64' => 'nullable|string',
            
            'employment_type' => 'nullable|string',
            'hours_per_week' => 'nullable|string',
            'commencement_date' => 'nullable|string',
            'award_classification' => 'nullable|string',
            'remuneration' => 'nullable|string',
            
            'acceptance_name' => 'nullable|string',
            'contract_signature_base64' => 'nullable|string',
            'contract_signature_date' => 'nullable|date',
            'head_of_operations_signature_base64' => 'nullable|string',
            'head_of_operations_date' => 'nullable|date',
        ]);

        // Process Disclosure Signature
        if (!empty($validatedData['disclosure_signature_base64'])) {
            $validatedData['disclosure_signature_path'] = $this->saveSignature($validatedData['disclosure_signature_base64']);
        }
        unset($validatedData['disclosure_signature_base64']);

        // Process Acceptance Contract Signature
        if (!empty($validatedData['contract_signature_base64'])) {
            $validatedData['contract_signature_path'] = $this->saveSignature($validatedData['contract_signature_base64']);
        }
        unset($validatedData['contract_signature_base64']);

        // Process Head of Operations Signature
        if (!empty($validatedData['head_of_operations_signature_base64'])) {
            $validatedData['head_of_operations_signature_path'] = $this->saveSignature($validatedData['head_of_operations_signature_base64']);
        }
        unset($validatedData['head_of_operations_signature_base64']);

        $form = EmploymentContractForm::updateOrCreate(
            ['employee_id' => $validatedData['employee_id']],
            $validatedData
        );

        return response()->json($form, 201);
    }

    public function update(Request $request, $id)
    {
        $form = EmploymentContractForm::findOrFail($id);
        
        $validatedData = $request->validate([
            'contract_date' => 'sometimes|date|nullable',
            'educator_name' => 'sometimes|string',
            'address' => 'sometimes|string|nullable',
            'position' => 'sometimes|string|nullable',
            
            'disclosure_date' => 'sometimes|date|nullable',
            'disclosure_signature_base64' => 'sometimes|string|nullable',
            
            'employment_type' => 'sometimes|string|nullable',
            'hours_per_week' => 'sometimes|string|nullable',
            'commencement_date' => 'sometimes|string|nullable',
            'award_classification' => 'sometimes|string|nullable',
            'remuneration' => 'sometimes|string|nullable',
            
            'acceptance_name' => 'sometimes|string|nullable',
            'contract_signature_base64' => 'sometimes|string|nullable',
            'contract_signature_date' => 'sometimes|date|nullable',
            'head_of_operations_signature_base64' => 'sometimes|string|nullable',
            'head_of_operations_date' => 'sometimes|date|nullable',
        ]);

        // Update Disclosure Signature
        if (!empty($validatedData['disclosure_signature_base64']) && !str_starts_with($validatedData['disclosure_signature_base64'], 'http')) {
            if ($form->disclosure_signature_path) {
                Storage::disk('public')->delete($form->disclosure_signature_path);
            }
            $validatedData['disclosure_signature_path'] = $this->saveSignature($validatedData['disclosure_signature_base64']);
        }
        unset($validatedData['disclosure_signature_base64']);

        // Update Contract Signature
        if (!empty($validatedData['contract_signature_base64']) && !str_starts_with($validatedData['contract_signature_base64'], 'http')) {
            if ($form->contract_signature_path) {
                Storage::disk('public')->delete($form->contract_signature_path);
            }
            $validatedData['contract_signature_path'] = $this->saveSignature($validatedData['contract_signature_base64']);
        }
        unset($validatedData['contract_signature_base64']);

        // Update Head of Operations Signature
        if (!empty($validatedData['head_of_operations_signature_base64']) && !str_starts_with($validatedData['head_of_operations_signature_base64'], 'http')) {
            if ($form->head_of_operations_signature_path) {
                Storage::disk('public')->delete($form->head_of_operations_signature_path);
            }
            $validatedData['head_of_operations_signature_path'] = $this->saveSignature($validatedData['head_of_operations_signature_base64']);
        }
        unset($validatedData['head_of_operations_signature_base64']);

        $form->update($validatedData);

        return response()->json($form);
    }

    public function destroy($id)
    {
        $form = EmploymentContractForm::findOrFail($id);
        
        // Delete both signature files if they exist
        if ($form->disclosure_signature_path) {
            Storage::disk('public')->delete($form->disclosure_signature_path);
        }
        if ($form->contract_signature_path) {
            Storage::disk('public')->delete($form->contract_signature_path);
        }
        if ($form->head_of_operations_signature_path) {
            Storage::disk('public')->delete($form->head_of_operations_signature_path);
        }
        $form->delete();

        return response()->json(['message' => 'Employment Contract form deleted successfully']);
    }

    /**
     * Helper to decode Base64 and save as image
     */
    private function saveSignature($base64Image)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('Invalid image type');
            }
            $base64Image = base64_decode($base64Image);
        } else {
            throw new \Exception('Invalid Base64 signature string');
        }

        $fileName = 'employment_contract_signatures/sig_' . Str::random(10) . '.' . $type;
        Storage::disk('public')->put($fileName, $base64Image);

        return $fileName;
    }
}