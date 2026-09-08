<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthWebController;
use App\Http\Controllers\Api\EccCasalController;
use App\Http\Controllers\Api\EccEquipeController;
use App\Http\Controllers\Api\IgrejaController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateTenantApi;
use App\Http\Middleware\InitializeTenancyBySlug;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do tenant
|--------------------------------------------------------------------------
|
| Login resolve tenant pelo body (sem X-Tenant). Demais rotas exigem X-Tenant.
| Recursos autenticados: SuperAdmin (token central) OU User (token do tenant).
|
*/

Route::middleware(['api'])->prefix('api/v1')->group(function () {
    Route::post('web/login', [AuthWebController::class, 'auth']);
});

Route::middleware([
    'api',
    InitializeTenancyBySlug::class,
])->prefix('api/v1')->group(function () {
    Route::get('/tenant/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'context' => 'tenant',
            'tenant_id' => tenant('id'),
            'tenant_slug' => tenant('slug'),
        ]);
    });

    // Só usuário do tenant (token no DB do tenant)
    Route::post('web/me', [AuthWebController::class, 'me'])->middleware(['cookie.to.token', 'auth:sanctum']);
    Route::post('web/logout', [AuthWebController::class, 'logout'])->middleware(['cookie.to.token', 'auth:sanctum']);
});

Route::middleware([
    'api',
    'cookie.to.token',
    AuthenticateTenantApi::class,
])->prefix('api/v1')->group(function () {
    Route::get('roles', [RolePermissionController::class, 'roles']);
    Route::get('permissions', [RolePermissionController::class, 'permissions']);
    Route::get('igrejas', [IgrejaController::class, 'index']);

    Route::post('users/{user}/roles', [UserController::class, 'assignRole']);
    Route::delete('users/{user}/roles', [UserController::class, 'removeRole']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('ecc/equipes', EccEquipeController::class);
    Route::post('ecc/casais/import', [EccCasalController::class, 'import']);
    Route::apiResource('ecc/casais', EccCasalController::class);
});
