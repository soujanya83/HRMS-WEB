<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Xero\XeroConnectionController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/xero/callback', [XeroConnectionController::class, 'callback']);