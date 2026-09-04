<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthAdminController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| Rotas centrais (landlord)
|--------------------------------------------------------------------------
|
| Domínios em CENTRAL_DOMAINS. Gestão de tenants e super-admins.
|
*/

Route::group(['prefix' => 'v1'], function () {
    Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'context' => 'central',
        ]);
    });

    Route::post('admin/login', [AuthAdminController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('admin/me', [AuthAdminController::class, 'me']);
        Route::post('admin/logout', [AuthAdminController::class, 'logout']);

        Route::apiResource('admin/tenants', TenantController::class);
    });
});
