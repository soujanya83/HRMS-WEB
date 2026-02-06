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

        if (!$request->filled('face_embedding') && $request->filled('profile_image_url')) {
            $employee->profile_image_url = $request->profile_image_url;
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile image updated successfully',
                'data'    => [
                    'employee_id'        => $employee->id,
                    'is_face_registered' => $employee->is_face_registered,
                    'profile_image_url'  => $employee->profile_image_url,
                ]
            ], 200);
        }


        if ($request->filled('face_embedding')) {

            if ($employee->is_face_registered) {

                if ($request->filled('profile_image_url')) {
                    $employee->profile_image_url = $request->profile_image_url;
                    $employee->save();

                    return response()->json([
                        'success' => true,
                        'message' => 'Profile image updated successfully. Face already registered. Please contact organisation. ',
                        'data'    => [
                            'employee_id'        => $employee->id,
                            'is_face_registered' => true,
                            'profile_image_url'  => $employee->profile_image_url,
                        ]
                    ], 200);
                }

                
                return response()->json([
                    'employee_id'        => $employee->id,
                    'success' => false,
                    'message' => 'Face already registered. Please contact organisation.'
                ], 409);
            }

            // First-time face registration
            $employee->face_embedding = $request->face_embedding;
            $both = $request->filled('profile_image_url');
            if ($both) {
                $employee->profile_image_url = $request->profile_image_url;
            }
            $employee->is_face_registered = true;
            $employee->save();

            return response()->json([
                'employee_id'        => $employee->id,
                'success' => true,
                'message' => $both ? 'Face and profile image registered successfully' : 'Face registered successfully',
                'data'    => [
                    'employee_id'        => $employee->id,
                    'is_face_registered' => true,
                    'profile_image_url'  => $employee->profile_image_url,
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
