<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\OrganizationController;
use App\Http\Controllers\API\V1\DepartmentController;
use App\Http\Controllers\API\V1\DesignationController;
use App\Http\Controllers\API\V1\Recruitment\JobOpeningController;
use App\Http\Controllers\API\V1\Recruitment\ApplicantController;

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



    });


});