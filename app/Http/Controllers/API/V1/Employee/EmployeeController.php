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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Mail\WelcomeEmployee;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Passwords\PasswordBroker;
use Throwable;

use Exception;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $employees = Employee::with([
                'user',
                'organization',
                'department',
                'designation',
                'applicant',
                'manager'
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
                'user',
                'organization',
                'department',
                'designation',
                'applicant',
                'manager',
                'documents',
                'employmentHistory',
                'probationPeriod',
                'exitDetails'
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
        // Build rules dynamically to allow ignoring uniqueness on users when user_id provided
        $userId = $request->input('user_id');

        $rules = [
            'organization_id' => ['required', 'exists:organizations,id'],

            // user_id optional (if provided must exist and not already linked to an employee)
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees','user_id')],

            'applicant_id' => ['nullable', 'exists:applicants,id', Rule::unique('employees','applicant_id')],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'reporting_manager_id' => ['nullable', 'exists:employees,id'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees','employee_code')],
            'first_name' => ['required', 'string', 'max:190'],
            'last_name' => ['required', 'string', 'max:190'],

            // personal_email needs to be unique in users table unless we're using an existing user_id
            'personal_email' => ['required', 'email', 'max:190'],

            'date_of_birth' => ['required','date'],
            'gender' => ['required', Rule::in(['Male','Female','Other'])],
            'phone_number' => ['required','string','max:20'],
            'address' => ['required','string','max:1000'],
            'joining_date' => ['required','date'],
            'employment_type' => ['required', Rule::in(['Full-time','Part-time','Contract','Internship'])],
            'status' => ['nullable', Rule::in(['Active','On Probation','On Leave','Terminated'])],

            'tax_file_number' => ['nullable','string','max:100'],
            'superannuation_fund_name' => ['nullable','string','max:255'],
            'superannuation_member_number' => ['nullable','string','max:100'],

            'bank_bsb' => ['nullable','string','max:10'],
            'bank_account_number' => ['nullable','string','max:30'],

            'visa_type' => ['nullable','string','max:50'],
            'visa_expiry_date' => ['nullable','date'],

            'emergency_contact_name' => ['nullable','string','max:255'],
            'emergency_contact_phone' => ['nullable','string','max:30'],
        ];

        // If user_id provided, allow personal_email to match that user's email (ignore unique on that user id)
        if ($userId) {
            $rules['personal_email'][] = Rule::unique('users','email')->ignore($userId);
        } else {
            // if no user_id, personal_email must not exist in users table
            $rules['personal_email'][] = Rule::unique('users','email');
        }

        // Also keep employees.personal_email unique (DB might enforce it)
        $rules['personal_email'][] = Rule::unique('employees','personal_email');

        $validated = $request->validate($rules);

        // Default status
        $validated['status'] = $validated['status'] ?? 'On Probation';

        // Use DB transaction to ensure both user and employee are created atomically
        $result = DB::transaction(function () use ($validated, $userId) {

            $createdUser = null;
            $rawPassword = null;

            if ($userId) {
                // Use existing user
                $user = User::findOrFail($userId);

                // Ensure provided personal_email matches the user's email (to avoid mismatch)
                if ($user->email !== $validated['personal_email']) {
                    throw ValidationException::withMessages([
                        'personal_email' => ['Provided personal_email does not match the existing user email.']
                    ]);
                }

                $createdUser = $user;
            } else {
                // Create new user
                $rawPassword = Str::random(10);
                $createdUser = User::create([
                    'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'email' => $validated['personal_email'],
                    'password' => Hash::make($rawPassword),
                ]);
            }

            // Attach organization-specific role using pivot table.
            // You told me role id 7 corresponds to "employee".
            $organizationId = $validated['organization_id'];

            $createdUser->assignRoleForOrganization('employee', $organizationId);


            // Prepare employee data
            $employeeData = $validated;
            $employeeData['user_id'] = $createdUser->id;

            // Create Employee record
            $employee = Employee::create($employeeData);

            return [
                'user' => $createdUser,
                'employee' => $employee,
                'raw_password' => $rawPassword, // null if existing user was used
            ];
        });

                // ---- SEND EMAIL (after transaction) ----
        try {
            $createdUser = $result['user'];
            $rawPassword = $result['raw_password'] ?? null;
            $inviteLink = null;

            // Option A: generate password reset invite link (preferred secure flow)
            if ($rawPassword) {
                // create reset token and link
               $token = app(PasswordBroker::class)->createToken($createdUser);
                $frontendBase = config('app.url'); // ideally use FRONTEND_URL in env
                $inviteLink = rtrim($frontendBase, '/') . '/password/reset/' . $token . '?email=' . urlencode($createdUser->email);
            }

            // Build mailable - pass rawPassword OR inviteLink (or both).
            $mailable = new WelcomeEmployee($createdUser, $rawPassword, $inviteLink);

            // Queue the mail (recommended) so api response returns quickly
            // Mail::send($createdUser->email)->queue($mailable);

            // If you don't have queue configured and want to send immediately, use:
            Mail::to($createdUser->email)->send($mailable);

        } catch (Throwable $mailEx) {
            // Log mail error but do NOT fail the whole request
            Log::error('Failed to send welcome email: ' . $mailEx->getMessage(), [
                'user_id' => $result['user']->id ?? null,
            ]);
        }



        // Return success (include raw_password only when a user was created)
        $responseData = [
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => [
                'user' => $result['user'],
                'employee' => $result['employee'],
            ]
        ];

        if (!empty($result['raw_password'])) {
            $responseData['data']['generated_password'] = $result['raw_password'];
        }

        return response()->json($responseData, 201);

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
                'employment_type' => 'sometimes',
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
            'user',
            'organization',
            'department',
            'designation',
            'documents',
            'employmentHistory',
            'probationPeriod',
            'exitDetails',
            'manager',
            'applicant'
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
            'success' => false,
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);
    }
    private function serverError($msg, $e)
    {
        return response()->json([
            'success' => false,
            'message' => $msg,
            'error' => $e->getMessage()
        ], 500);
    }
    private function notFound($msg, $e)
    {
        return response()->json([
            'success' => false,
            'message' => $msg,
            'error' => $e->getMessage()
        ], 404);
    }

    public function SyncWithXero(Request $request): JsonResponse
    {
        // Placeholder for Xero synchronization logic
        return response()->json([
            'success' => true,
            'message' => 'Xero synchronization not yet implemented'
        ], 200);
    }
}
