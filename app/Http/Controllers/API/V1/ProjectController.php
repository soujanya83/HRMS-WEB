<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::with([
            'manager:id,first_name,last_name,employee_code',
            'creator:id,first_name,last_name,employee_code'
        ])->latest()->get();


        return response()->json([
            'status' => true,
            'data' => $projects
        ]);
    }

    /**
     * Store a new project.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // ✅ Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'manager_id' => 'required|exists:employees,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'in:not_started,in_progress,completed,on_hold',
                'details_file' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // ✅ Handle file upload
            if ($request->hasFile('details_file')) {
                $file = $request->file('details_file');
                $folderPath = public_path('assets/projects'); // public/assets/projects/

                // Create folder if not exists
                if (!File::exists($folderPath)) {
                    File::makeDirectory($folderPath, 0777, true, true);
                }

                // Generate unique filename
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

                // Move file to folder
                $file->move($folderPath, $filename);

                // Save relative path in DB
                $data['details_file'] = 'assets/projects/' . $filename;
            }

            $employee = Employee::where('user_id', Auth::user()->id)->first();
            $organization_id = $employee->organization_id;
            $created_by = $employee->id;
            $data['organization_id'] = $organization_id;
            $data['created_by'] = $created_by;
            // dd($data);

            // ✅ Create Project
            $project = Project::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Project created successfully.',
                'data' => $project,
            ], 201);
        } catch (\Exception $e) {
            // Log error for debugging
            Log::error('Error creating project: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating the project.',
                'error' => $e->getMessage(), // optional: hide in production
            ], 500);
        }
    }

    /**
     * Show a specific project.
     */
    public function show($id): JsonResponse
    {
        $project = Project::with(['manager:id,first_name,last_name,employee_code', 'tasks','creator:id,first_name,last_name,employee_code'])->find($id);

        if (!$project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $project
        ]);
    }

    /**
     * Update an existing project.
     */
 public function update(Request $request, $id): JsonResponse
{
    try {
        // 🔍 Find Project
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        // ✅ Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'manager_id' => 'sometimes|required|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'in:not_started,in_progress,completed,on_hold',
            'details_file' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // ✅ Handle new file upload (replace old one)
        if ($request->hasFile('details_file')) {
            $file = $request->file('details_file');
            $folderPath = public_path('assets/projects');

            // Create folder if missing
            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0777, true, true);
            }

            // Delete old file if exists
            if (!empty($project->details_file) && File::exists(public_path($project->details_file))) {
                File::delete(public_path($project->details_file));
            }

            // Generate unique new filename
            $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

            // Move new file to folder
            $file->move($folderPath, $filename);

            // Save relative path
            $data['details_file'] = 'assets/projects/' . $filename;
        }

        // ✅ Update project
        $project->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Project updated successfully.',
            'data' => $project
        ]);

    } catch (\Exception $e) {
        Log::error('Error updating project: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'An error occurred while updating the project.',
            'error' => $e->getMessage(), // hide in production
        ], 500);
    }
}

    /**
     * Delete a project.
     */
    public function destroy($id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found.'
            ], 404);
        }

        $project->delete();

        return response()->json([
            'status' => true,
            'message' => 'Project deleted successfully.'
        ]);
    }
}
