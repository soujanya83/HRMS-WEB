<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;
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



    });


});