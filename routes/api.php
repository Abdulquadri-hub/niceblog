<?php

use App\Http\Controllers\Landlord\{
    TenantController
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('landlord')->group(function () {

    Route::controller(TenantController::class)->prefix('tenant')->group(function () {
        Route::post('register', 'save');
        Route::post('delete/{tenantId}', 'drop');
        Route::post('retry-setup/{tenantId}', 'retrySetup');
    });

});
