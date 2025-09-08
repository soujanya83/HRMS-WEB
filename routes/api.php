<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\OrganizationController;
use App\Http\Controllers\API\V1\DepartmentController;
use App\Http\Controllers\API\V1\DesignationController;

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

    });


});