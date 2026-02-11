<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;  
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaceController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'       => 'required|integer|exists:employees,id',
            'face_embedding'    => 'nullable|array',
            'profile_image_url' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $employee = Employee::findOrFail($request->employee_id);

        $faceOld = !is_null($employee->face_embedding);
        $imgOld = !is_null($employee->profile_image_url);
        $faceNew = $request->filled('face_embedding');
        $imgNew = $request->filled('profile_image_url');

        $faceChanged = $faceNew && ($employee->face_embedding !== $request->face_embedding);
        $imgChanged = $imgNew && ($employee->profile_image_url !== $request->profile_image_url);

        // Both changed (both old, both new, or one new one updated)
        if ($faceChanged && $imgChanged) {
            $faceWasNull = is_null($employee->face_embedding);
            $imgWasNull = is_null($employee->profile_image_url);
            $employee->face_embedding = $request->face_embedding;
            $employee->profile_image_url = $request->profile_image_url;
            $employee->is_face_registered = true;
            $employee->save();
            if ($faceWasNull && ! $imgWasNull) {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => 'Image updated, face registered',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 200);
            } else if (! $faceWasNull && $imgWasNull) {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => 'Face updated, image registered',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 200);
            } else if ($faceWasNull && $imgWasNull) {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => 'Face and image registered successfully',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 201);
            } else {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => 'Face and image updated successfully',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 200);
            }
        }

        // Only face changed
        if ($faceChanged && !$imgChanged) {
            $faceWasNull = is_null($employee->face_embedding);
            $employee->face_embedding = $request->face_embedding;
            $employee->is_face_registered = true;
            $employee->save();
            $imgRegistered = !is_null($employee->profile_image_url);
            if ($faceWasNull) {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => $imgRegistered ? 'Face registered, image same as already saved one' : 'Face registered',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 201);
            } else {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => $imgRegistered ? 'Face updated, image same as already saved one' : 'Face updated',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => true,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 200);
            }
        }

        // Only image changed
        if (!$faceChanged && $imgChanged) {
            $imgWasNull = is_null($employee->profile_image_url);
            $employee->profile_image_url = $request->profile_image_url;
            $employee->save();
            $faceRegistered = !is_null($employee->face_embedding);
            if ($imgWasNull) {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => $faceRegistered ? 'Image registered, face same as already saved one' : 'Image registered',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => $faceRegistered,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 201);
            } else {
                return response()->json([
                    'employee_id' => $employee->id,
                    'success' => true,
                    'message' => $faceRegistered ? 'Image updated, face same as already saved one' : 'Image updated',
                    'data' => [
                        'employee_id' => $employee->id,
                        'is_face_registered' => $faceRegistered,
                        'profile_image_url' => $employee->profile_image_url,
                    ]
                ], 200);
            }
        }

        // Both old, nothing new or no change
        if (($faceOld || $imgOld) && !$faceChanged && !$imgChanged) {
            return response()->json([
                'employee_id' => $employee->id,
                'success' => false,
                'message' => 'Nothing new to update',
                'data' => [
                    'employee_id' => $employee->id,
                    'is_face_registered' => $employee->is_face_registered,
                    'profile_image_url' => $employee->profile_image_url,
                ]
            ], 200);
        }

        // Only face new, no image ever
        if ($faceNew && !$imgNew && !$imgOld) {
            $employee->face_embedding = $request->face_embedding;
            $employee->is_face_registered = true;
            $employee->save();
            return response()->json([
                'employee_id' => $employee->id,
                'success' => true,
                'message' => 'Face registered successfully',
                'data' => [
                    'employee_id' => $employee->id,
                    'is_face_registered' => true,
                    'profile_image_url' => $employee->profile_image_url,
                ]
            ], 201);
        }

        // Only image new, no face ever
        if (!$faceNew && $imgNew && !$faceOld) {
            $employee->profile_image_url = $request->profile_image_url;
            $employee->save();
            return response()->json([
                'employee_id' => $employee->id,
                'success' => true,
                'message' => 'Profile image registered successfully',
                'data' => [
                    'employee_id' => $employee->id,
                    'is_face_registered' => $employee->is_face_registered,
                    'profile_image_url' => $employee->profile_image_url,
                ]
            ], 201);
        }

       
        return response()->json([
            'employee_id'        => $employee->id,
            'success' => false,
            'message' => 'No face data or image provided'
        ], 422);
    }

    // Get all registered faces
    public function index()
    {
        $faces = Employee::whereNotNull('face_embedding')->get(['id', 'face_embedding']);
        return response()->json(['data' => $faces]);
    }
}
