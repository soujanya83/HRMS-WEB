<?php

namespace App\Http\Controllers\API\V1\Employee;


use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeDocument;
use App\Models\Employee\EmploymentHistory;
use App\Models\Employee\ProbationPeriod;
use App\Models\Employee\EmployeeExit;
use App\Models\User;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Recruitment\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $employees = Employee::with([
                'user', 'organization', 'department', 'designation', 'applicant', 'manager'
            ])->orderBy('joining_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Employees retrieved successfully',
                'data' => $employees
            ], 200);
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve employees', $e);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $employee = Employee::with([
                'user', 'organization', 'department', 'designation', 'applicant', 'manager',
                'documents', 'employmentHistory', 'probationPeriod', 'exitDetails'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Employee retrieved successfully',
                'data' => $employee
            ], 200);
        } catch (Exception $e) {
            return $this->notFound('Employee not found', $e);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'user_id' => 'required|exists:users,id|unique:employees,user_id',
                'applicant_id' => 'nullable|exists:applicants,id|unique:employees,applicant_id',
                'department_id' => 'required|exists:departments,id',
                'designation_id' => 'required|exists:designations,id',
                'reporting_manager_id' => 'nullable|exists:employees,id',
                'employee_code' => 'required|string|unique:employees,employee_code|max:50',
                'first_name' => 'required|string|max:190',
                'last_name' => 'required|string|max:190',
                'personal_email' => 'required|email|max:190|unique:employees,personal_email',
                'date_of_birth' => 'required|date',
                'gender' => 'required|in:Male,Female,Other',
                'phone_number' => 'required|string|max:20',
                'address' => 'required|string|max:1000',
                'joining_date' => 'required|date',
                'employment_type' => 'required|in:Full-time,Part-time,Contract,Internship',
                'status' => 'sometimes|in:Active,On Probation,On Leave,Terminated',
                'tax_file_number' => 'nullable|string|max:100',
                'superannuation_fund_name' => 'nullable|string|max:255',
                'superannuation_member_number' => 'nullable|string|max:100',
                'bank_bsb' => 'nullable|string|max:10',
                'bank_account_number' => 'nullable|string|max:30',
                'visa_type' => 'nullable|string|max:50',
                'visa_expiry_date' => 'nullable|date',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:30',
            ]);

            $validated['status'] = $validated['status'] ?? 'On Probation';

            $employee = Employee::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (Exception $e) {
            return $this->serverError('Failed to create employee', $e);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);

            $validated = $request->validate([
                'organization_id' => 'sometimes|exists:organizations,id',
                'user_id' => 'sometimes|exists:users,id|unique:employees,user_id,' . $id,
                'applicant_id' => 'nullable|exists:applicants,id|unique:employees,applicant_id,' . $id,
                'department_id' => 'sometimes|exists:departments,id',
                'designation_id' => 'sometimes|exists:designations,id',
                'reporting_manager_id' => 'nullable|exists:employees,id',
                'employee_code' => 'sometimes|string|unique:employees,employee_code,' . $id,
                'first_name' => 'sometimes|string|max:190',
                'last_name' => 'sometimes|string|max:190',
                'personal_email' => 'sometimes|email|max:190|unique:employees,personal_email,' . $id,
                'date_of_birth' => 'sometimes|date',
                'gender' => 'sometimes|in:Male,Female,Other',
                'phone_number' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:1000',
                'joining_date' => 'sometimes|date',
                'employment_type' => 'sometimes|in:Full-time,Part-time,Contract,Internship',
                'status' => 'sometimes|in:Active,On Probation,On Leave,Terminated',
                'tax_file_number' => 'nullable|string|max:100',
                'superannuation_fund_name' => 'nullable|string|max:255',
                'superannuation_member_number' => 'nullable|string|max:100',
                'bank_bsb' => 'nullable|string|max:10',
                'bank_account_number' => 'nullable|string|max:30',
                'visa_type' => 'nullable|string|max:50',
                'visa_expiry_date' => 'nullable|date',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:30',
            ]);

            $employee->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $employee
            ], 200);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (Exception $e) {
            return $this->serverError('Failed to update employee', $e);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee soft-deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return $this->serverError('Failed to soft-delete employee', $e);
        }
    }

    public function getTrashed(): JsonResponse
    {
        $employees = Employee::onlyTrashed()->with(['user', 'organization'])->get();
        return response()->json(['success' => true, 'data' => $employees], 200);
    }

    public function restore($id): JsonResponse
    {
        try {
            $employee = Employee::onlyTrashed()->findOrFail($id);
            $employee->restore();

            return response()->json([
                'success' => true,
                'message' => 'Employee restored successfully',
                'data' => $employee
            ], 200);
        } catch (Exception $e) {
            return $this->serverError('Failed to restore employee', $e);
        }
    }

    public function forceDelete($id): JsonResponse
    {
        try {
            $employee = Employee::onlyTrashed()->findOrFail($id);
            $employee->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Employee permanently deleted'
            ], 200);
        } catch (Exception $e) {
            return $this->serverError('Failed to permanently delete employee', $e);
        }
    }

    public function getByStatus($status): JsonResponse
    {
        $valid = ['Active', 'On Probation', 'On Leave', 'Terminated'];
        if (!in_array($status, $valid)) {
            return response()->json(['success' => false, 'message' => 'Invalid status', 'valid_statuses' => $valid], 400);
        }
        $employees = Employee::where('status', $status)->with(['organization', 'department', 'designation'])->get();
        return response()->json(['success' => true, 'data' => $employees], 200);
    }

    public function getByDepartment($id): JsonResponse
    {
        $employees = Employee::where('department_id', $id)->with(['department', 'designation', 'organization'])->get();
        return response()->json(['success' => true, 'data' => $employees], 200);
    }

    public function getByDesignation($id): JsonResponse
    {
        $employees = Employee::where('designation_id', $id)->with(['department', 'designation', 'organization'])->get();
        return response()->json(['success' => true, 'data' => $employees], 200);
    }

    public function getByOrganization($id): JsonResponse
    {
        $employees = Employee::where('organization_id', $id)->with(['organization', 'department', 'designation'])->get();
        return response()->json(['success' => true, 'data' => $employees], 200);
    }

    public function getByManager($id): JsonResponse
    {
        $manager = Employee::findOrFail($id);
        $employees = Employee::where('reporting_manager_id', $id)->with(['manager'])->get();
        return response()->json(['success' => true, 'data' => $employees, 'manager' => $manager->first_name . ' ' . $manager->last_name], 200);
    }

    public function profile($id): JsonResponse
    {
        $employee = Employee::with([
            'user', 'organization', 'department', 'designation', 'documents', 'employmentHistory',
            'probationPeriod', 'exitDetails', 'manager', 'applicant'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $employee], 200);
    }

    public function documents($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $documents = $employee->documents;
        return response()->json(['success' => true, 'data' => $documents], 200);
    }

    public function employmentHistory($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $history = $employee->employmentHistory;
        return response()->json(['success' => true, 'data' => $history], 200);
    }

    public function probationPeriod($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $probation = $employee->probationPeriod;
        return response()->json(['success' => true, 'data' => $probation], 200);
    }

    public function exitDetails($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $exit = $employee->exitDetails;
        return response()->json(['success' => true, 'data' => $exit], 200);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:Active,On Probation,On Leave,Terminated'
        ]);
        $employee->update(['status' => $validated['status']]);
        return response()->json(['success' => true, 'message' => 'Status updated', 'data' => $employee], 200);
    }

    public function updateManager(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'reporting_manager_id' => 'required|exists:employees,id'
        ]);
        $employee->update(['reporting_manager_id' => $validated['reporting_manager_id']]);
        return response()->json(['success' => true, 'message' => 'Manager updated', 'data' => $employee], 200);
    }

    public function addDocument(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'document_name' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,docx,jpeg,png|max:5120'
        ]);
        $file = $request->file('document');
        $path = $file->store('employee_docs', 'public');
        $doc = $employee->documents()->create([
            'document_name' => $validated['document_name'],
            'document_url' => Storage::url($path)
        ]);
        return response()->json(['success' => true, 'message' => 'Document uploaded', 'data' => $doc], 201);
    }

    public function deleteDocument($id, $docId): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $doc = $employee->documents()->findOrFail($docId);
        if ($doc->document_url) {
            $filePath = str_replace('/storage/', '', $doc->document_url);
            Storage::disk('public')->delete($filePath);
        }
        $doc->delete();
        return response()->json(['success' => true, 'message' => 'Document deleted'], 200);
    }

    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employees' => 'required|array|min:1',
            'employees.*.organization_id' => 'required|exists:organizations,id',
            'employees.*.user_id' => 'required|exists:users,id',
            // Add other required validations per row...
        ]);
        $created = [];
        DB::beginTransaction();
        try {
            foreach ($validated['employees'] as $row) {
                $created[] = Employee::create($row);
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => count($created) . ' employees created', 'data' => $created], 201);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->serverError('Failed to bulk create employees', $e);
        }
    }

    // Helper error responses
    private function validationError($e)
    {
        return response()->json([
            'success' => false, 'message' => 'Validation error', 'errors' => $e->errors()
        ], 422);
    }
    private function serverError($msg, $e)
    {
        return response()->json([
            'success' => false, 'message' => $msg, 'error' => $e->getMessage()
        ], 500);
    }
    private function notFound($msg, $e)
    {
        return response()->json([
            'success' => false, 'message' => $msg, 'error' => $e->getMessage()
        ], 404);
    }
}
