<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ChildSafeCodeOfConductForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChildSafeCodeOfConductFormController extends Controller
{
    public function show($id)
    {
        return response()->json(ChildSafeCodeOfConductForm::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(ChildSafeCodeOfConductForm::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer',
            'organization_id' => 'required|integer',
            'name' => 'required|string',
            'signature_base64' => 'nullable|string',
            'signature_date' => 'nullable|date',
        ]);

        if (!empty($validatedData['signature_base64'])) {
            $validatedData['signature_path'] = $this->saveSignature($validatedData['signature_base64']);
        }
        unset($validatedData['signature_base64']);

        $form = ChildSafeCodeOfConductForm::updateOrCreate(
            ['employee_id' => $validatedData['employee_id']],
            $validatedData
        );

        return response()->json($form, 201);
    }

    public function update(Request $request, $id)
    {
        $form = ChildSafeCodeOfConductForm::findOrFail($id);
        
        $validatedData = $request->validate([
            'name' => 'sometimes|string',
            'signature_base64' => 'sometimes|string|nullable',
            'signature_date' => 'sometimes|date|nullable',
        ]);

        // Process Base64 Signature for Update
        if (!empty($validatedData['signature_base64']) && !str_starts_with($validatedData['signature_base64'], 'http')) {
            if ($form->signature_path) {
                Storage::disk('public')->delete($form->signature_path);
            }
            $validatedData['signature_path'] = $this->saveSignature($validatedData['signature_base64']);
        }
        unset($validatedData['signature_base64']);

        $form->update($validatedData);

        return response()->json($form);
    }

    public function destroy($id)
    {
        $form = ChildSafeCodeOfConductForm::findOrFail($id);
        
        if ($form->signature_path) {
            Storage::disk('public')->delete($form->signature_path);
        }

        $form->delete();

        return response()->json(['message' => 'Child Safe Code of Conduct form deleted successfully']);
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

        $fileName = 'child_safe_conduct_signatures/sig_' . Str::random(10) . '.' . $type;
        Storage::disk('public')->put($fileName, $base64Image);

        return $fileName;
    }
}