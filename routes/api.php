<?php

use App\Http\Controllers\Landlord\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('landlord')->group(function () {

    Route::controller(TenantController::class)->group(function () {
        Route::post('create-tenant', 'save');
    });

});
