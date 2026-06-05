<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\SuperannuationForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SuperannuationFormController extends Controller
{
    public function show($id)
    {
        return response()->json(SuperannuationForm::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(SuperannuationForm::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        if (!empty($request->signature_base64)) {
            $data['signature_path'] = $this->uploadSignature($request->signature_base64);
        }

        $form = SuperannuationForm::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            $data
        );

        return response()->json($form, 201);
    }

    public function update(Request $request, $id)
    {
        $form = SuperannuationForm::findOrFail($id);
        $data = $request->validate($this->validationRules(true));

        if (!empty($request->signature_base64) && !str_starts_with($request->signature_base64, 'http')) {
            // Delete old one if updating
            if ($form->signature_path) Storage::disk('public')->delete($form->signature_path);
            $data['signature_path'] = $this->uploadSignature($request->signature_base64);
        }

        $form->update($data);
        return response()->json($form);
    }

    public function destroy($id)
    {
        $form = SuperannuationForm::findOrFail($id);
        if ($form->signature_path) Storage::disk('public')->delete($form->signature_path);
        $form->delete();

        return response()->json(['message' => 'Superannuation form choice cleared successfully']);
    }

    private function uploadSignature($base64Str)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $type)) {
            $base64Str = substr($base64Str, strpos($base64Str, ',') + 1);
            $type = strtolower($type[1]);
            $decoded = base64_decode($base64Str);
            
            $fileName = 'super_signatures/sig_' . Str::random(10) . '.' . $type;
            Storage::disk('public')->put($fileName, $decoded);
            return $fileName;
        }
        throw new \Exception('Invalid dynamic canvas layout base64 string provided');
    }

    private function validationRules($isUpdate = false)
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'employee_id' => "$req|integer",
            'organization_id' => "$req|integer",
            'super_choice_type' => "$req|in:existing_fund,default_fund,smsf",

            // Section B rules
            'b_super_fund_name' => 'nullable|required_if:super_choice_type,existing_fund|string',
            'b_super_fund_abn' => 'nullable|required_if:super_choice_type,existing_fund|string',
            'b_usi' => 'nullable|required_if:super_choice_type,existing_fund|string',
            'b_member_account_number' => 'nullable|required_if:super_choice_type,existing_fund|string',
            'b_account_name' => 'nullable|required_if:super_choice_type,existing_fund|string',
            'b_letter_of_compliance_attached' => 'nullable|boolean',

            // Section C rules
            'c_business_name' => 'nullable|string',
            'c_business_abn' => 'nullable|string',
            'c_super_fund_name' => 'nullable|string',
            'c_super_fund_abn' => 'nullable|string',
            'c_usi' => 'nullable|string',
            'c_choose_default_fund_checkbox' => 'nullable|boolean',

            // Section D rules
            'd_smsf_name' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_smsf_abn' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_smsf_esa' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_account_name' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_bank_account_name' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_bsb_code' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_account_number' => 'nullable|required_if:super_choice_type,smsf|string',
            'd_provided_evidence_ato' => 'nullable|boolean',

            // Global
            'signature_base64' => 'nullable|string',
            'declaration_date' => 'nullable|date',
        ];
    }
}