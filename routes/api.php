<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\OrganizationController;
use App\Http\Controllers\API\V1\DepartmentController;
use App\Http\Controllers\API\V1\DesignationController;
use App\Http\Controllers\API\V1\Recruitment\JobOpeningController;
use App\Http\Controllers\API\V1\Recruitment\ApplicantController;
use App\Http\Controllers\API\V1\Recruitment\InterviewController;
use App\Http\Controllers\API\V1\Recruitment\JobOfferController;
use App\Http\Controllers\API\V1\Recruitment\OnboardingTaskController;
use App\Http\Controllers\API\V1\Recruitment\OnboardingTemplateController;
use App\Http\Controllers\API\V1\Recruitment\OnboardingTemplateTaskController;
use App\Http\Controllers\API\V1\Recruitment\OnboardingAutomationController;
use App\Http\Controllers\API\V1\Employee\EmployeeController;
use App\Http\Controllers\API\V1\Attendance\AttendanceController;
use App\Http\Controllers\API\V1\Employee\LeaveController;
use Illuminate\Http\Request;
use App\Http\Controllers\API\V1\OrganizationLeaveController;
use App\Http\Controllers\API\V1\Attendance\OrganizationAttendanceRuleController;
use App\Http\Controllers\API\V1\HolidayController;
use App\Http\Controllers\API\V1\ProjectController;
use App\Http\Controllers\API\V1\TaskController;

use App\Http\Controllers\API\V1\Attendance\OvertimeRequestController;
use App\Http\Controllers\API\V1\Employee\TimesheetController;
use App\Http\Controllers\API\V1\SalaryComponentTypesController;
use App\Http\Controllers\API\V1\SalaryStructureController;
use App\Http\Controllers\API\V1\SalaryComponentController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', function (Request $request) {
            return response()->json($request->user());
        });

        // Organization Routes
        Route::apiResource('organizations', OrganizationController::class);

        // Nested Department Routes
        Route::apiResource('organizations.departments', DepartmentController::class)->shallow();

        // Nested Designation Routes
        Route::apiResource('departments.designations', DesignationController::class)->shallow();

        Route::apiResource('attendances', AttendanceController::class);

        Route::apiResource('organization-leaves', OrganizationLeaveController::class);
        Route::apiResource('organization-attendance-rule', OrganizationAttendanceRuleController::class);
        Route::apiResource('organization-holiday', HolidayController::class);
        Route::apiResource('organization-project', ProjectController::class);
        Route::apiResource('organization/employee/tasks', TaskController::class);
        Route::apiResource('organization/employee/timesheet', TimesheetController::class);

        Route::patch('organization-holiday/{id}/partial', [HolidayController::class, 'partialUpdate']);
        Route::apiResource('employee-overtime', OvertimeRequestController::class);
        Route::apiResource('organization/salarycomponents/types', SalaryComponentTypesController::class);
        Route::apiResource('organization/salarycomponents', SalaryComponentController::class);
        Route::apiResource('organization/employee/salary', SalaryStructureController::class);

        Route::apiResource('organization/employee/salarystructure', SalaryStructureController::class);

        Route::prefix('recruitment/job-openings')->group(function () {
            // Standard CRUD operations
            Route::get('/', [JobOpeningController::class, 'index']);
            Route::post('/', [JobOpeningController::class, 'store']);
            Route::get('/{id}', [JobOpeningController::class, 'show']);
            Route::put('/{id}', [JobOpeningController::class, 'update']);
            Route::patch('/{id}', [JobOpeningController::class, 'update']);
            Route::delete('/{id}', [JobOpeningController::class, 'destroy']);

            // Additional custom endpoints
            Route::get('/status/{status}', [JobOpeningController::class, 'getByStatus']);
            Route::get('/active/list', [JobOpeningController::class, 'getActiveJobOpenings']);
        });

        // Applicant API Routes
        Route::prefix('recruitment/applicants')->group(function () {
            // Standard CRUD operations
            Route::get('/', [ApplicantController::class, 'index']);
            Route::post('/', [ApplicantController::class, 'store']);
            Route::get('/{id}', [ApplicantController::class, 'show']);
            Route::put('/{id}', [ApplicantController::class, 'update']);
            Route::patch('/{id}', [ApplicantController::class, 'update']);
            Route::delete('/{id}', [ApplicantController::class, 'destroy']);

            // Additional custom endpoints
            Route::get('/job-opening/{jobOpeningId}', [ApplicantController::class, 'getByJobOpening']);
            Route::get('/status/{status}', [ApplicantController::class, 'getByStatus']);
            Route::patch('/{id}/status', [ApplicantController::class, 'updateStatus']);
            Route::get('/{id}/resume/download', [ApplicantController::class, 'downloadResume']);
        });

        Route::prefix('recruitment/interviews')->group(function () {
            // Standard CRUD operations
            Route::get('/', [InterviewController::class, 'index']);
            Route::post('/', [InterviewController::class, 'store']);
            Route::get('/{id}', [InterviewController::class, 'show']);
            Route::put('/{id}', [InterviewController::class, 'update']);
            Route::patch('/{id}', [InterviewController::class, 'update']);
            Route::delete('/{id}', [InterviewController::class, 'destroy']);

            // Additional custom endpoints
            Route::get('/applicant/{applicantId}', [InterviewController::class, 'getByApplicant']);
            Route::get('/status/{status}', [InterviewController::class, 'getByStatus']);
            Route::get('/interviewer/{interviewerId}', [InterviewController::class, 'getByInterviewer']);
            Route::get('/upcoming/list', [InterviewController::class, 'getUpcoming']);
            Route::patch('/{id}/status', [InterviewController::class, 'updateStatus']);
            Route::patch('/{id}/feedback', [InterviewController::class, 'addFeedback']);
        });

        Route::prefix('recruitment/job-offers')->group(function () {
            // Standard CRUD operations
            Route::get('/', [JobOfferController::class, 'index']);
            Route::post('/', [JobOfferController::class, 'store']);
            Route::get('/{id}', [JobOfferController::class, 'show']);
            Route::put('/{id}', [JobOfferController::class, 'update']);
            Route::patch('/{id}', [JobOfferController::class, 'update']);
            Route::delete('/{id}', [JobOfferController::class, 'destroy']);

            // Additional custom endpoints
            Route::get('/status/{status}', [JobOfferController::class, 'getByStatus']);
            Route::get('/job-opening/{jobOpeningId}', [JobOfferController::class, 'getByJobOpening']);
            Route::get('/expired/list', [JobOfferController::class, 'getExpired']);
            Route::get('/pending/list', [JobOfferController::class, 'getPendingOffers']);
            Route::patch('/{id}/status', [JobOfferController::class, 'updateStatus']);
            Route::get('/{id}/offer-letter/download', [JobOfferController::class, 'downloadOfferLetter']);
        });

        Route::prefix('recruitment/onboarding-tasks')->group(function () {
            Route::get('/', [OnboardingTaskController::class, 'index']);
            Route::post('/', [OnboardingTaskController::class, 'store']);
            Route::get('/{id}', [OnboardingTaskController::class, 'show']);
            Route::put('/{id}', [OnboardingTaskController::class, 'update']);
            Route::patch('/{id}', [OnboardingTaskController::class, 'update']);
            Route::delete('/{id}', [OnboardingTaskController::class, 'destroy']);

            Route::get('/applicant/{applicantId}', [OnboardingTaskController::class, 'getByApplicant']);
            Route::get('/status/{status}', [OnboardingTaskController::class, 'getByStatus']);
            Route::patch('/{id}/complete', [OnboardingTaskController::class, 'markCompleted']);
            Route::get('/overdue/list', [OnboardingTaskController::class, 'getOverdue']);
            Route::get('/upcoming/list', [OnboardingTaskController::class, 'getUpcoming']);
        });

        // Onboarding Template API Routes
        Route::prefix('recruitment/onboarding-templates')->group(function () {
            Route::get('/', [OnboardingTemplateController::class, 'index']);
            Route::post('/', [OnboardingTemplateController::class, 'store']);
            Route::get('/{id}', [OnboardingTemplateController::class, 'show']);
            Route::put('/{id}', [OnboardingTemplateController::class, 'update']);
            Route::patch('/{id}', [OnboardingTemplateController::class, 'update']);
            Route::delete('/{id}', [OnboardingTemplateController::class, 'destroy']);

            Route::get('/organization/{organizationId}', [OnboardingTemplateController::class, 'getByOrganization']);
            Route::post('/{id}/clone', [OnboardingTemplateController::class, 'clone']);
        });

        // Onboarding Template Task API Routes
        Route::prefix('recruitment/onboarding-template-tasks')->group(function () {
            Route::get('/', [OnboardingTemplateTaskController::class, 'index']);
            Route::post('/', [OnboardingTemplateTaskController::class, 'store']);
            Route::get('/{id}', [OnboardingTemplateTaskController::class, 'show']);
            Route::put('/{id}', [OnboardingTemplateTaskController::class, 'update']);
            Route::patch('/{id}', [OnboardingTemplateTaskController::class, 'update']);
            Route::delete('/{id}', [OnboardingTemplateTaskController::class, 'destroy']);

            Route::get('/template/{templateId}', [OnboardingTemplateTaskController::class, 'getByTemplate']);
            Route::get('/role/{role}', [OnboardingTemplateTaskController::class, 'getByRole']);
            Route::post('/bulk-create', [OnboardingTemplateTaskController::class, 'bulkCreate']);
        });

        // Onboarding Automation API Routes
        Route::prefix('recruitment/onboarding-automation')->group(function () {
            Route::post('/generate-tasks', [OnboardingAutomationController::class, 'generateTasksFromTemplate']);
            Route::post('/auto-generate-new-hires', [OnboardingAutomationController::class, 'autoGenerateForNewHires']);
            Route::get('/dashboard', [OnboardingAutomationController::class, 'getDashboard']);
        });

        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::get('/{id}', [EmployeeController::class, 'show']);
            Route::put('/{id}', [EmployeeController::class, 'update']);
            Route::patch('/{id}', [EmployeeController::class, 'update']);
            Route::delete('/{id}', [EmployeeController::class, 'destroy']);
            Route::get('/trashed', [EmployeeController::class, 'getTrashed']);
            Route::patch('/{id}/restore', [EmployeeController::class, 'restore']);
            Route::delete('/{id}/force', [EmployeeController::class, 'forceDelete']);
            Route::get('/status/{status}', [EmployeeController::class, 'getByStatus']);
            Route::get('/department/{id}', [EmployeeController::class, 'getByDepartment']);
            Route::get('/designation/{id}', [EmployeeController::class, 'getByDesignation']);
            Route::get('/organization/{id}', [EmployeeController::class, 'getByOrganization']);
            Route::get('/manager/{id}', [EmployeeController::class, 'getByManager']);
            Route::get('/{id}/profile', [EmployeeController::class, 'profile']);
            Route::get('/{id}/documents', [EmployeeController::class, 'documents']);
            Route::get('/{id}/employment-history', [EmployeeController::class, 'employmentHistory']);
            Route::get('/{id}/probation', [EmployeeController::class, 'probationPeriod']);
            Route::get('/{id}/exit', [EmployeeController::class, 'exitDetails']);
            Route::patch('/{id}/status', [EmployeeController::class, 'updateStatus']);
            Route::patch('/{id}/manager', [EmployeeController::class, 'updateManager']);
            Route::post('/{id}/documents', [EmployeeController::class, 'addDocument']);
            Route::delete('/{id}/documents/{docId}', [EmployeeController::class, 'deleteDocument']);
            Route::post('/bulk', [EmployeeController::class, 'bulkCreate']);
        });

        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('/store', [AttendanceController::class, 'store']);
            Route::post('/update', [AttendanceController::class, 'update']);
            Route::get('/show/{attendance}', [AttendanceController::class, 'show']);
            Route::post('attendances/bulk', [AttendanceController::class, 'bulkStore']);
            Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
            Route::post('/clock-out', [AttendanceController::class, 'clockOut']);
            Route::delete('/destroy/{attendance}', [AttendanceController::class, 'destroy']);

            // Extra work on holiday
            Route::post('/work-on-holiday', [AttendanceController::class, 'RequestWorkOnHoliday']);
            Route::get('/work-on-holiday', [AttendanceController::class, 'ShowHolidayRequests']);
            Route::post('/approve-work-on-holiday', [AttendanceController::class, 'ApproveWorkOnHoliday']);
        });

        Route::prefix('leave')->group(function () {
            Route::get('/', [LeaveController::class, 'index']);
            Route::post('/store', [LeaveController::class, 'store']);
            Route::post('/store/{id}', [LeaveController::class, 'store']);
            Route::put('/leaves/{id}', [LeaveController::class, 'update']);
            Route::put('/approve-leave/{id}', [LeaveController::class, 'approve_leave']);
            Route::get('/show/{id}', [LeaveController::class, 'show']);
            Route::delete('/destroy/{id}', [LeaveController::class, 'destroy']);
            Route::get('/leaveBalance', [LeaveController::class, 'leaveBalance']);
        });
    });
});
