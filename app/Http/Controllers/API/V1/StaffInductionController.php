<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffInduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffInductionController extends Controller
{
    public function show($id)
    {
        return response()->json(StaffInduction::findOrFail($id));
    }

    public function getByEmployee($employeeId)
    {
        return response()->json(StaffInduction::where('employee_id', $employeeId)->firstOrFail());
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer',
            'organization_id' => 'required|integer',
            'form_data' => 'required|array',
        ]);

        // Process any base64 signatures embedded in the JSON
        $this->processSignatures($validatedData['form_data']);

        $induction = StaffInduction::updateOrCreate(
            ['employee_id' => $validatedData['employee_id']],
            $validatedData
        );

        return response()->json($induction, 201);
    }

    public function update(Request $request, $id)
    {
        $induction = StaffInduction::findOrFail($id);
        
        $validatedData = $request->validate([
            'form_data' => 'required|array',
        ]);

        // Fetch raw original paths to delete old replaced images
        $existingRawData = json_decode($induction->getRawOriginal('form_data'), true);
        
        $this->processSignatures($validatedData['form_data'], $existingRawData);

        $induction->update($validatedData);

        return response()->json($induction);
    }

    public function destroy($id)
    {
        $induction = StaffInduction::findOrFail($id);
        
        $existingRawData = json_decode($induction->getRawOriginal('form_data'), true);
        
        // Delete all associated signature files from storage
        $this->deleteStoredImages($existingRawData);

        $induction->delete();

        return response()->json(['message' => 'Staff Induction form deleted successfully']);
    }

    /**
     * Traverses the JSON object, handles base64 uploads, and strips existing URL prefixes.
     */
    private function processSignatures(&$formData, $existingRawData = null)
    {
        $baseUrl = asset('storage') . '/';

        $handleField = function (&$field, $oldPath) use ($baseUrl) {
            if (empty($field)) return;

            if (str_starts_with($field, 'data:image')) {
                // New signature drawn
                if ($oldPath) Storage::disk('public')->delete($oldPath);
                $field = $this->saveSignatureImage($field);
            } elseif (str_starts_with($field, $baseUrl)) {
                // Keep the relative path in the DB if the frontend sent the URL back intact
                $field = str_replace($baseUrl, '', $field);
            }
        };

        $sections = [
            'hr_orientation', 'policies_procedures', 'child_safe', 'work_health_safety',
            'key_people', 'centre_base', 'montessori_environment', 'active_supervision',
            'family_communication', 'team_collaboration'
        ];

        foreach ($sections as $sec) {
            if (isset($formData[$sec]['educatorSign'])) {
                $handleField($formData[$sec]['educatorSign'], $existingRawData[$sec]['educatorSign'] ?? null);
            }
            if (isset($formData[$sec]['supervisorSign'])) {
                $handleField($formData[$sec]['supervisorSign'], $existingRawData[$sec]['supervisorSign'] ?? null);
            }
        }

        if (isset($formData['declaration']['employeeSignature'])) {
            $handleField($formData['declaration']['employeeSignature'], $existingRawData['declaration']['employeeSignature'] ?? null);
        }
        if (isset($formData['declaration']['supervisorSignature'])) {
            $handleField($formData['declaration']['supervisorSignature'], $existingRawData['declaration']['supervisorSignature'] ?? null);
        }
    }

    /**
     * Helper to clean up all images in the JSON when a record is deleted
     */
    private function deleteStoredImages($rawData)
    {
        if (!$rawData) return;
        
        $paths = [];
        $sections = [
            'hr_orientation', 'policies_procedures', 'child_safe', 'work_health_safety',
            'key_people', 'centre_base', 'montessori_environment', 'active_supervision',
            'family_communication', 'team_collaboration'
        ];

        foreach ($sections as $sec) {
            if (!empty($rawData[$sec]['educatorSign'])) $paths[] = $rawData[$sec]['educatorSign'];
            if (!empty($rawData[$sec]['supervisorSign'])) $paths[] = $rawData[$sec]['supervisorSign'];
        }
        if (!empty($rawData['declaration']['employeeSignature'])) $paths[] = $rawData['declaration']['employeeSignature'];
        if (!empty($rawData['declaration']['supervisorSignature'])) $paths[] = $rawData['declaration']['supervisorSignature'];

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Decode and save base64
     */
    private function saveSignatureImage($base64Image)
    {
        preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type);
        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
        $type = strtolower($type[1]);
        
        $fileName = 'induction_signatures/sig_' . Str::random(10) . '.' . $type;
        Storage::disk('public')->put($fileName, base64_decode($base64Image));

        return $fileName;
    }
}