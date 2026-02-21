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
use App\Http\Controllers\API\V1\Attendance\ManualAttendanceController;
use App\Http\Controllers\API\V1\Employee\LeaveController;
use App\Http\Controllers\API\V1\Employee\EmployeeDocumentController;
use App\Http\Controllers\API\V1\Employee\EmployeeExitController;
use App\Http\Controllers\API\V1\Employee\EmploymentHistoryController;
use App\Http\Controllers\API\V1\Employee\ProbationPeriodController;
use App\Http\Controllers\API\V1\Employee\OffboardingTaskController;
use App\Http\Controllers\API\V1\Employee\OffboardingTemplateController;
use App\Http\Controllers\API\V1\Employee\OffboardingTemplateTaskController;
use App\Http\Controllers\API\V1\Rostering\ShiftController;
use App\Http\Controllers\API\V1\Rostering\RosterController;
use App\Http\Controllers\API\V1\Rostering\RosterPeriodController;
use App\Http\Controllers\API\V1\Rostering\ShiftSwapRequestController;
use App\Http\Controllers\API\V1\Performance\PerformanceReviewCycleController;
use App\Http\Controllers\API\V1\Performance\PerformanceGoalController;
use App\Http\Controllers\API\V1\Performance\GoalKeyResultController;
use App\Http\Controllers\API\V1\Performance\PerformanceReviewController;
use App\Http\Controllers\API\V1\Performance\PerformanceFeedbackController;
use Illuminate\Http\Request;
use App\Http\Controllers\API\V1\OrganizationLeaveController;
use App\Http\Controllers\API\V1\Attendance\OrganizationAttendanceRuleController;
use App\Http\Controllers\API\V1\HolidayController;
use App\Http\Controllers\API\V1\ProjectController;
use App\Http\Controllers\API\V1\TaskController;
use App\Http\Controllers\API\V1\ModuleController;


use App\Http\Controllers\API\V1\Attendance\OvertimeRequestController;
use App\Http\Controllers\API\V1\Employee\TimesheetController;
use App\Http\Controllers\API\V1\SalaryComponentTypesController;
use App\Http\Controllers\API\V1\SalaryStructureController;
use App\Http\Controllers\API\V1\SalaryComponentController;
use App\Http\Controllers\API\V1\TaxSlabsController;
use App\Models\Payrolls;
use App\Http\Controllers\API\V1\PayrollsController;
use App\Http\Controllers\API\V1\SalaryRevisionsController as SalaryRevisions;
use App\Http\Controllers\API\V1\BonusesController as BonusController;
use App\Http\Controllers\API\V1\EmploymentTypeController;
use App\Http\Controllers\API\V1\Xero\PayrunController;
use App\Http\Controllers\API\V1\Xero\XeroConnectionController;
use App\Http\Controllers\API\V1\Xero\XeroEmployeeController;
use App\Http\Controllers\API\V1\ProfilePinController;
use App\Http\Controllers\API\V1\Employee\FaceController;

use App\Http\Controllers\API\V1\{
    RoleController,
    PermissionController,
    RolePermissionController,
    UserOrganizationRoleController,
    MeController
};


// Route::get('/xero/connect', [XeroConnectionController::class, 'connect']);
// Route::get('/xero/callback', [XeroConnectionController::class, 'callback']);




Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);


    // Profile Pin APIs
    Route::prefix('profile-pin')->group(function () {
        // Public endpoints
        Route::post('/forgot', [ProfilePinController::class, 'forgotPin']);
        Route::post('/verify-otp-reset', [ProfilePinController::class, 'verifyOtpAndResetPin']);
        // Authenticated endpoints
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/create', [ProfilePinController::class, 'createPin']);
            Route::post('/verify', [ProfilePinController::class, 'verifyPin']);
        });
    });

    // Face Embedding APIs
    Route::post('/employees/register-face', [FaceController::class, 'register']);
    Route::get('/faces', [FaceController::class, 'index']);

    
    Route::middleware('auth:sanctum')->group(function () {

         Route::get('/xero/connect', [XeroConnectionController::class, 'connect']);

         Route::get('/xero/status', [XeroConnectionController::class, 'status']);



            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{id}', [RoleController::class, 'update']);
            Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

            // Permissions
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::post('/permissions', [PermissionController::class, 'store']);
            Route::put('/permissions/{id}', [PermissionController::class, 'update']);
            Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

            // Role ↔ Permissions
            Route::get('/roles/{roleId}/permissions', [RolePermissionController::class, 'index']);
            Route::post('/roles/{roleId}/permissions', [RolePermissionController::class, 'sync']);

            // User ↔ Org Roles
            Route::get('/organizations/{orgId}/users/{userId}/roles', [UserOrganizationRoleController::class, 'index']);
            Route::post('/organizations/{orgId}/users/{userId}/roles', [UserOrganizationRoleController::class, 'store']);
            Route::delete('/organizations/{orgId}/users/{userId}/roles/{roleName}', [UserOrganizationRoleController::class, 'destroy']);

            // Me (Frontend)
            Route::get('/me/roles/{orgId}', [MeController::class, 'roles']);
            Route::get('/me/permissions/{orgId}', [MeController::class, 'permissions']);




        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', function (Request $request) {
            return response()->json($request->user());
        });

        // Organization Routes
        // Route::middleware('org.role:superadmin')->group(function () {
        Route::apiResource('organizations', OrganizationController::class);
        // protected routes here
        // });
        Route::get('/fund-suggestions', [OrganizationController::class, 'search']);

        // employeement type
        Route::apiResource('employment-types', EmploymentTypeController::class);
        // Nested Department Routes
        Route::apiResource('organizations.departments', DepartmentController::class)->shallow();

        // Nested Designation Routes
        Route::apiResource('organizations.designations', DesignationController::class)->shallow();

        Route::apiResource('attendances', AttendanceController::class);

        Route::apiResource('organization-leaves', OrganizationLeaveController::class);
        Route::apiResource('organization-attendance-rule', OrganizationAttendanceRuleController::class);
        Route::get('getbyorganization/{id}', [OrganizationAttendanceRuleController::class, 'getByOrganization']);
        Route::apiResource('organization-holiday', HolidayController::class);
        Route::apiResource('organization-project', ProjectController::class);
        Route::apiResource('organization/employee/tasks', TaskController::class);
        Route::apiResource('organization/employee/timesheet', TimesheetController::class);
        Route::get('employee/timesheet/getpayperiod/{employeeId}', [TimesheetController::class, 'getpayperiod']);
        Route::post('employee/timesheet', [TimesheetController::class, 'CreateTimeSheetManually']);
        Route::post('employee/timesheet/review',[TimesheetController::class,'reviewTimesheet']);
      Route::post('employee/timesheet/payrun',[TimesheetController::class,'createPayRun']);



        Route::post('/timesheets/generate', [TimesheetController::class, 'generate']);
        Route::post('/timesheets/submit', [TimesheetController::class, 'submit'])->name('timesheets.submit');

        Route::get('/timesheets/{organizationId}', [TimesheetController::class, 'index']);
        Route::post('/timesheets/{id}', [TimesheetController::class, 'update']);

        Route::post('/xero/timesheets/push', [XeroEmployeeController::class, 'pushApproved']);
        Route::post('/xero/timesheet/push-employee', [XeroEmployeeController::class, 'pushApprovedForEmployee'])->name('xero.timesheet.push.employee');

        Route::get('/available-pay-periods', [XeroEmployeeController::class, 'getAvailablePayPeriods']);
        Route::get('/pay-periods', [XeroEmployeeController::class, 'get_all_pay_periods']);



         Route::post('/xero/payruns/create', [XeroEmployeeController::class, 'create']);
         Route::get('/xero/payruns', [XeroEmployeeController::class, 'show'])->name('xero.payruns');
         Route::post('/xero-payruns/by-organization', [XeroEmployeeController::class, 'getByOrganization']);
         Route::post('/xero/payruns/{id}/approve', [XeroEmployeeController::class, 'approve']);

         Route::post('/xero/payslips/sync', [XeroEmployeeController::class, 'syncPayslips']);
         Route::post('/xero-payslips/by-organization', [XeroEmployeeController::class, 'getByOrganizationpayslip']);


                 Route::get('/xero/payslips', [XeroEmployeeController::class, 'payslipget']);

            Route::get('/xero/payslips/{id}', [XeroEmployeeController::class, 'employeeshow']);

        //  Route::get('/xero/payslips', [XeroEmployeeController::class, 'payslips']);

        //leave of XEROOOOO ----------------

        // 1. Sync Leave Types (Admin Config Page)
            Route::post('/xero/leaves/sync-types', [XeroEmployeeController::class, 'syncLeaveTypes']);

            // 2. Get All Leaves / Employee Leaves (History)
            Route::get('/xero/leaves', [XeroEmployeeController::class, 'index']);

            // 3. Apply/Push Leave to Xero (Manager Approval Action)
            Route::post('/xero/leaves/apply', [XeroEmployeeController::class, 'applyLeave']);




        

        Route::get('employee/payrun/{organizationId}', [PayrunController::class, 'getPayrun']);

        Route::patch('organization-holiday/{id}/partial', [HolidayController::class, 'partialUpdate']);
        Route::apiResource('employee-overtime', OvertimeRequestController::class);
        Route::apiResource('organization/salarycomponents/types', SalaryComponentTypesController::class);
        Route::apiResource('organization/salary/components', SalaryComponentController::class);
        Route::apiResource('organization/employee/salary', SalaryStructureController::class);
        Route::apiResource('organization/tax-slabs', TaxSlabsController::class);
        Route::apiResource('payrolls', PayrollsController::class);
        Route::apiResource('salary-revisions', SalaryRevisions::class);

        Route::prefix('bonuses')->group(function () {

            Route::get('/', [BonusController::class, 'index']);
            Route::post('/', [BonusController::class, 'store']);
            Route::get('/{id}', [BonusController::class, 'show']);
            Route::put('/{id}', [BonusController::class, 'update']);
            Route::delete('/{id}', [BonusController::class, 'destroy']);

            // Approval actions
            Route::post('/{id}/approve', [BonusController::class, 'approve']);
            Route::post('/{id}/reject', [BonusController::class, 'reject']);
        });



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
            Route::post('/basic/store-update',[EmployeeController::class, 'storeOrUpdateBasic']);
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
            Route::get('/get-attendance/{employee_id}/{date}', [AttendanceController::class, 'getEmployeeAttendance']);
            Route::put('/approve-or-reject-employee-attendance-change-request/{Id}', [AttendanceController::class, 'approveAttendanceChange']);
            Route::get('/manual-change-requests', [AttendanceController::class, 'getAttendancechangeRequests']);

            // Extra work on holiday
            Route::post('/work-on-holiday', [AttendanceController::class, 'RequestWorkOnHoliday']);
            Route::get('/work-on-holiday', [AttendanceController::class, 'ShowHolidayRequests']);
            Route::post('/approve-work-on-holiday', [AttendanceController::class, 'ApproveWorkOnHoliday']);
            Route::get('/employee-attendance-summary', [AttendanceController::class, 'EmployeeAttendanceSummary']);

            Route::get('attendance/by-employee-date', [AttendanceController::class,'getByEmployeeAndDate']);

        });

        Route::prefix('manual-attendance')->group(function(){

        Route::post('/store',[ManualAttendanceController::class,'store']);

        Route::get('/list/{id}',[ManualAttendanceController::class,'index']);

        Route::get('/view/{id}',[ManualAttendanceController::class,'show']);

        Route::post('/update/{id}',[ManualAttendanceController::class,'update']);

        Route::delete('/delete/{id}',[ManualAttendanceController::class,'destroy']);

        Route::post('/approve/{id}',[ManualAttendanceController::class,'approve']);

        Route::post('/reject/{id}',[ManualAttendanceController::class,'reject']);

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
            Route::get('/leaves-summary', [LeaveController::class, 'getLeavesSummary']);
            Route::get('/xero-leave-types/{organization_id}', [LeaveController::class, 'getXeroLeaveTypes']);
            Route::post('/assign-employee-leave-types', [LeaveController::class, 'assignEmployeeleaveType']);
        });

        // Employee Documents
        Route::prefix('employee-documents')->group(function () {
            Route::get('/', [EmployeeDocumentController::class, 'index']);
            Route::post('/', [EmployeeDocumentController::class, 'store']);
            Route::get('/{id}', [EmployeeDocumentController::class, 'show']);
            Route::put('/{id}', [EmployeeDocumentController::class, 'update']);
            Route::patch('/{id}', [EmployeeDocumentController::class, 'update']);
            Route::delete('/{id}', [EmployeeDocumentController::class, 'destroy']);
            Route::get('/by-employee/{employeeId}', [EmployeeDocumentController::class, 'byEmployee']);
        });

        // Employee Exit
        Route::prefix('employee-exits')->group(function () {
            Route::get('/', [EmployeeExitController::class, 'index']);
            Route::post('/', [EmployeeExitController::class, 'store']);
            Route::get('/{id}', [EmployeeExitController::class, 'show']);
            Route::put('/{id}', [EmployeeExitController::class, 'update']);
            Route::patch('/{id}', [EmployeeExitController::class, 'update']);
            Route::delete('/{id}', [EmployeeExitController::class, 'destroy']);
            Route::get('/by-employee/{employeeId}', [EmployeeExitController::class, 'byEmployee']);
        });

        // Employment History
        Route::prefix('employment-history')->group(function () {
            Route::get('/', [EmploymentHistoryController::class, 'index']);
            Route::post('/', [EmploymentHistoryController::class, 'store']);
            Route::get('/{id}', [EmploymentHistoryController::class, 'show']);
            Route::put('/{id}', [EmploymentHistoryController::class, 'update']);
            Route::patch('/{id}', [EmploymentHistoryController::class, 'update']);
            Route::delete('/{id}', [EmploymentHistoryController::class, 'destroy']);
            Route::get('/by-employee/{employeeId}', [EmploymentHistoryController::class, 'byEmployee']);
        });

        // Probation Periods
        Route::prefix('probation-periods')->group(function () {
            Route::get('/', [ProbationPeriodController::class, 'index']);
            Route::post('/', [ProbationPeriodController::class, 'store']);
            Route::get('/{id}', [ProbationPeriodController::class, 'show']);
            Route::put('/{id}', [ProbationPeriodController::class, 'update']);
            Route::patch('/{id}', [ProbationPeriodController::class, 'update']);
            Route::delete('/{id}', [ProbationPeriodController::class, 'destroy']);
            Route::get('/by-employee/{employeeId}', [ProbationPeriodController::class, 'byEmployee']);
        });


        Route::prefix('offboarding-tasks')->group(function () {
            Route::get('/', [OffboardingTaskController::class, 'index']);
            Route::post('/', [OffboardingTaskController::class, 'store']);
            Route::get('/{id}', [OffboardingTaskController::class, 'show']);
            Route::put('/{id}', [OffboardingTaskController::class, 'update']);
            Route::patch('/{id}', [OffboardingTaskController::class, 'update']);
            Route::delete('/{id}', [OffboardingTaskController::class, 'destroy']);
            Route::patch('/{id}/complete', [OffboardingTaskController::class, 'markCompleted']);
            Route::get('/overdue/list', [OffboardingTaskController::class, 'overdue']);
        });

        Route::prefix('offboarding-templates')->group(function () {
            Route::get('/', [OffboardingTemplateController::class, 'index']);
            Route::post('/', [OffboardingTemplateController::class, 'store']);
            Route::get('/{id}', [OffboardingTemplateController::class, 'show']);
            Route::put('/{id}', [OffboardingTemplateController::class, 'update']);
            Route::patch('/{id}', [OffboardingTemplateController::class, 'update']);
            Route::delete('/{id}', [OffboardingTemplateController::class, 'destroy']);
            Route::post('/{id}/clone', [OffboardingTemplateController::class, 'clone']);
        });

        Route::prefix('offboarding-template-tasks')->group(function () {
            Route::get('/', [OffboardingTemplateTaskController::class, 'index']);
            Route::post('/', [OffboardingTemplateTaskController::class, 'store']);
            Route::get('/{id}', [OffboardingTemplateTaskController::class, 'show']);
            Route::put('/{id}', [OffboardingTemplateTaskController::class, 'update']);
            Route::patch('/{id}', [OffboardingTemplateTaskController::class, 'update']);
            Route::delete('/{id}', [OffboardingTemplateTaskController::class, 'destroy']);
            Route::get('/template/{templateId}', [OffboardingTemplateTaskController::class, 'byTemplate']);
        });
        Route::prefix('shifts')->group(function () {
            Route::get('/', [ShiftController::class, 'index']);
            Route::post('/', [ShiftController::class, 'store']);
             Route::get('/calendar', [ShiftController::class, 'calendar']);
             Route::get('/trashed', [ShiftController::class, 'trashed']);
            Route::get('/{id}', [ShiftController::class, 'show']);
            Route::put('/{id}', [ShiftController::class, 'update']);
            Route::patch('/{id}', [ShiftController::class, 'update']);
            Route::delete('/{id}', [ShiftController::class, 'destroy']);
            
            Route::patch('/{id}/restore', [ShiftController::class, 'restore']);
           
        });

        Route::prefix('rosters')->group(function () {
            Route::get('/', [RosterController::class, 'index']);
            Route::post('/', [RosterController::class, 'store']);
            Route::get('/{id}', [RosterController::class, 'show']);
            Route::put('/{id}', [RosterController::class, 'update']);
            Route::patch('/{id}', [RosterController::class, 'update']);
            Route::delete('/{id}', [RosterController::class, 'destroy']);
            Route::post('/bulk', [RosterController::class, 'bulkStore']);
            Route::get('/employee/{employeeId}', [RosterController::class, 'byEmployee']);
            Route::post('bulk-assign', [RosterController::class, 'bulkAssign']);
            Route::get('period/{periodId}', [RosterController::class, 'byPeriod']);
        });


         Route::prefix('periods')->group(function () {
        Route::get('/', [RosterPeriodController::class, 'index']);
        Route::post('/', [RosterPeriodController::class, 'store']);
        Route::post('{id}/publish', [RosterPeriodController::class, 'publish']);
        Route::post('{id}/lock', [RosterPeriodController::class, 'lock']);
    });



        Route::prefix('shift-swap-requests')->group(function () {
            Route::get('/', [ShiftSwapRequestController::class, 'index']);
            Route::post('/', [ShiftSwapRequestController::class, 'store']);
            Route::get('/{id}', [ShiftSwapRequestController::class, 'show']);
            Route::put('/{id}', [ShiftSwapRequestController::class, 'update']);
            Route::patch('/{id}', [ShiftSwapRequestController::class, 'update']);
            Route::delete('/{id}', [ShiftSwapRequestController::class, 'destroy']);
            Route::patch('/{id}/approve', [ShiftSwapRequestController::class, 'approve']);
            Route::patch('/{id}/reject', [ShiftSwapRequestController::class, 'reject']);
            Route::get('/employee/{employeeId}', [ShiftSwapRequestController::class, 'byEmployee']);
        });


        Route::prefix('performance-review-cycles')->group(function () {
            Route::get('/', [PerformanceReviewCycleController::class, 'index']);
            Route::post('/', [PerformanceReviewCycleController::class, 'store']);
            Route::get('/{id}', [PerformanceReviewCycleController::class, 'show']);
            Route::put('/{id}', [PerformanceReviewCycleController::class, 'update']);
            Route::patch('/{id}', [PerformanceReviewCycleController::class, 'update']);
            Route::delete('/{id}', [PerformanceReviewCycleController::class, 'destroy']);
            Route::get('/status/{status}', [PerformanceReviewCycleController::class, 'status']);
        });

    Route::prefix('performance-goals')->group(function () {
        Route::get('/', [PerformanceGoalController::class, 'index']);
        Route::post('/', [PerformanceGoalController::class, 'store']);
        Route::get('/{id}', [PerformanceGoalController::class, 'show']);
        Route::put('/{id}', [PerformanceGoalController::class, 'update']);
        Route::patch('/{id}', [PerformanceGoalController::class, 'update']);
        Route::delete('/{id}', [PerformanceGoalController::class, 'destroy']);
        Route::get('/cycle/{cycleId}', [PerformanceGoalController::class, 'byCycle']);
        Route::get('/status/{status}', [PerformanceGoalController::class, 'byStatus']);
        Route::post('/bulk', [PerformanceGoalController::class, 'bulkAssign']);
    });

//  Route::prefix('goal-key-results')->group(function () {
//         Route::patch('/bulk', [GoalKeyResultController::class, 'bulkUpdate']);
//         Route::get('/', [GoalKeyResultController::class, 'index']);
//         Route::post('/', [GoalKeyResultController::class, 'store']);
//         Route::get('/{id}', [GoalKeyResultController::class, 'show']);
//         Route::put('/{id}', [GoalKeyResultController::class, 'update']);
//         Route::patch('/{id}', [GoalKeyResultController::class, 'update'])
//         ->whereNumber('id');
//         Route::delete('/{id}', [GoalKeyResultController::class, 'destroy']);
//     });

    // Route::prefix('performance-reviews')->group(function () {
    //     Route::get('/', [PerformanceReviewController::class, 'index']);
    //     Route::post('/', [PerformanceReviewController::class, 'store']);
    //     Route::get('/{id}', [PerformanceReviewController::class, 'show']);
    //     Route::put('/{id}', [PerformanceReviewController::class, 'update']);
    //     Route::patch('/{id}', [PerformanceReviewController::class, 'update']);
    //     Route::delete('/{id}', [PerformanceReviewController::class, 'destroy']);
    //     Route::patch('/{id}/acknowledge', [PerformanceReviewController::class, 'acknowledge']);
    //     Route::get('/employee/{employeeId}', [PerformanceReviewController::class, 'byEmployee']);
    //     Route::get('/cycle/{cycleId}', [PerformanceReviewController::class, 'byCycle']);
    // });

    // Route::prefix('performance-feedback')->group(function () {
    //     Route::get('/', [PerformanceFeedbackController::class, 'index']);
    //     Route::post('/', [PerformanceFeedbackController::class, 'store']);
    //     Route::get('/{id}', [PerformanceFeedbackController::class, 'show']);
    //     Route::put('/{id}', [PerformanceFeedbackController::class, 'update']);
    //     Route::patch('/{id}', [PerformanceFeedbackController::class, 'update']);
    //     Route::delete('/{id}', [PerformanceFeedbackController::class, 'destroy']);
    //     Route::patch('/{id}/read', [PerformanceFeedbackController::class, 'markRead']);
    //     Route::get('/receiver/{employeeId}', [PerformanceFeedbackController::class, 'forReceiver']);
    // });
        // Route::prefix('performance-goals')->group(function () {
        //     Route::get('/', [PerformanceGoalController::class, 'index']);
        //     Route::post('/', [PerformanceGoalController::class, 'store']);
        //     Route::get('/{id}', [PerformanceGoalController::class, 'show']);
        //     Route::put('/{id}', [PerformanceGoalController::class, 'update']);
        //     Route::patch('/{id}', [PerformanceGoalController::class, 'update']);
        //     Route::delete('/{id}', [PerformanceGoalController::class, 'destroy']);
        //     Route::get('/cycle/{cycleId}', [PerformanceGoalController::class, 'byCycle']);
        //     Route::get('/status/{status}', [PerformanceGoalController::class, 'byStatus']);
        //     Route::post('/bulk', [PerformanceGoalController::class, 'bulkAssign']);
        // });

     Route::prefix('goal-key-results')->group(function () {
        Route::patch('/bulk', [GoalKeyResultController::class, 'bulkUpdate']);
        Route::get('/', [GoalKeyResultController::class, 'index']);
        Route::post('/', [GoalKeyResultController::class, 'store']);
        Route::get('/{id}', [GoalKeyResultController::class, 'show']);
        Route::put('/{id}', [GoalKeyResultController::class, 'update']);
        Route::patch('/{id}', [GoalKeyResultController::class, 'update'])
        ->whereNumber('id');
        Route::delete('/{id}', [GoalKeyResultController::class, 'destroy']);
    });

        Route::prefix('performance-reviews')->group(function () {
            Route::get('/', [PerformanceReviewController::class, 'index']);
            Route::post('/', [PerformanceReviewController::class, 'store']);
            Route::get('/{id}', [PerformanceReviewController::class, 'show']);
            Route::put('/{id}', [PerformanceReviewController::class, 'update']);
            Route::patch('/{id}', [PerformanceReviewController::class, 'update']);
            Route::delete('/{id}', [PerformanceReviewController::class, 'destroy']);
            Route::patch('/{id}/acknowledge', [PerformanceReviewController::class, 'acknowledge']);
            Route::get('/employee/{employeeId}', [PerformanceReviewController::class, 'byEmployee']);
            Route::get('/cycle/{cycleId}', [PerformanceReviewController::class, 'byCycle']);
        });

        Route::prefix('performance-feedback')->group(function () {
            Route::get('/', [PerformanceFeedbackController::class, 'index']);
            Route::post('/', [PerformanceFeedbackController::class, 'store']);
            Route::get('/{id}', [PerformanceFeedbackController::class, 'show']);
            Route::put('/{id}', [PerformanceFeedbackController::class, 'update']);
            Route::patch('/{id}', [PerformanceFeedbackController::class, 'update']);
            Route::delete('/{id}', [PerformanceFeedbackController::class, 'destroy']);
            Route::patch('/{id}/read', [PerformanceFeedbackController::class, 'markRead']);
            Route::get('/receiver/{employeeId}', [PerformanceFeedbackController::class, 'forReceiver']);
        });

        // Xero Connection Routes
        Route::prefix('xero-connections')->group(function () {
            Route::get('/', [XeroConnectionController::class, 'index']);
            Route::post('/', [XeroConnectionController::class, 'store']);
            Route::get('/{id}', [XeroConnectionController::class, 'show']);
            Route::match(['put', 'post'], '/{id}', [XeroConnectionController::class, 'update']);
            Route::delete('/{id}', [XeroConnectionController::class, 'destroy']);
        });

        Route::post('/xero/sync-employee', [XeroEmployeeController::class, 'sync']);
        Route::get('/xero/employees',[XeroEmployeeController::class, 'getAllFromXero']);
        // Route::post('/xero/timesheets/push',[XeroEmployeeController::class, 'push']);
    
           
    
        // Module APIs
Route::get('modules', [ModuleController::class, 'index']);
Route::get('modules/{id}/pages', [ModuleController::class, 'pages']);
    });

});
