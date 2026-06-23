<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PidtdcForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PidtdcFormController extends Controller
{
    private $signatureFields = [
        'appointee_signature' => 'appointee',
        'nominated_supervisor_signature' => 'nom_sup',
        'declarant_signature' => 'declarant',
        'witness_signature' => 'witness',
        'checklist_ns_signature' => 'chk_ns',
        'checklist_rp_signature' => 'chk_rp'
    ];

    public function show($id)
    {
        return response()->json(PidtdcForm::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(PidtdcForm::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        // Handle the 6 possible Base64 signatures
        foreach ($this->signatureFields as $field => $prefix) {
            $base64Key = "{$field}_base64";
            $pathKey = "{$field}_path";
            
            if (!empty($data[$base64Key])) {
                $data[$pathKey] = $this->saveSignature($data[$base64Key], $prefix);
                unset($data[$base64Key]);
            }
        }

        $form = PidtdcForm::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            $data
        );

        return response()->json($form, 201);
    }

    public function update(Request $request, $id)
    {
        $form = PidtdcForm::findOrFail($id);
        $data = $request->validate($this->validationRules(true));

        foreach ($this->signatureFields as $field => $prefix) {
            $base64Key = "{$field}_base64";
            $pathKey = "{$field}_path";

            if (!empty($data[$base64Key]) && !str_starts_with($data[$base64Key], 'http')) {
                // Delete old image
                if ($form->$pathKey) Storage::disk('public')->delete($form->$pathKey);
                // Save new image
                $data[$pathKey] = $this->saveSignature($data[$base64Key], $prefix);
            }
            unset($data[$base64Key]);
        }

        $form->update($data);
        return response()->json($form);
    }

    public function destroy($id)
    {
        $form = PidtdcForm::findOrFail($id);
        
        // Clean up all signature images
        foreach ($this->signatureFields as $field => $prefix) {
            $pathKey = "{$field}_path";
            if ($form->$pathKey) Storage::disk('public')->delete($form->$pathKey);
        }
        
        $form->delete();
        return response()->json(['message' => 'PIDTDC form deleted successfully']);
    }

    private function saveSignature($base64Image, $prefix)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            $decoded = base64_decode($base64Image);
            
            $fileName = 'pidtdc_signatures/' . $prefix . '_' . Str::random(10) . '.' . $type;
            Storage::disk('public')->put($fileName, $decoded);
            return $fileName;
        }
        throw new \Exception("Invalid Base64 string for {$prefix}");
    }

    private function validationRules($isUpdate = false)
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'employee_id' => "$req|integer",
            'organization_id' => "$req|integer",
            
            // Page 1
            'appointee_name' => 'nullable|string',
            'appointee_signature_base64' => 'nullable|string',
            'appointee_signature_date' => 'nullable|date',
            'nominated_supervisor_name' => 'nullable|string',
            'nominated_supervisor_signature_base64' => 'nullable|string',
            'nominated_supervisor_signature_date' => 'nullable|date',
            
            // Page 2
            'compliance_actions_details' => 'nullable|string',
            'has_suspended_certificate' => 'nullable|boolean',
            'suspended_certificate_details' => 'nullable|string',
            'has_prohibition_notice' => 'nullable|boolean',
            'prohibition_notice_details' => 'nullable|string',
            'has_refused_licence' => 'nullable|boolean',
            'refused_licence_details' => 'nullable|string',
            'declarant_full_name' => 'nullable|string',
            'declarant_address' => 'nullable|string',
            'declarant_dob' => 'nullable|date',
            'declarant_signature_base64' => 'nullable|string',
            'declarant_signature_date' => 'nullable|date',
            'witness_name' => 'nullable|string',
            'witness_signature_base64' => 'nullable|string',
            
            // Page 3
            'checklist_employee_name' => 'nullable|string',
            'checklist_data' => 'nullable|array', // validates that it's a JSON object/array
            'checklist_comments' => 'nullable|string',
            'checklist_ns_signature_base64' => 'nullable|string',
            'checklist_ns_signature_date' => 'nullable|date',
            'checklist_rp_signature_base64' => 'nullable|string',
            'checklist_rp_signature_date' => 'nullable|date',
        ];
    }
}