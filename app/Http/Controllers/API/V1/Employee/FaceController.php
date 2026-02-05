<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;  
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaceController extends Controller
{
    // Register a face embedding for an employee
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'face_embedding' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::find($request->employee_id);
        $responseMessage = $employee->face_embedding ? 'Face updated successfully' : 'Face registered successfully';
        $employee->face_embedding = $request->face_embedding;
        $employee->save();

        return response()->json(['message' => $responseMessage, 'data' => $employee], 201);
    }

    // Get all registered faces
    public function index()
    {
        $faces = Employee::whereNotNull('face_embedding')->get(['id', 'face_embedding']);
        return response()->json(['data' => $faces]);
    }
}
