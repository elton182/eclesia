<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthWebController;
use App\Http\Controllers\Api\EccCasalController;
use App\Http\Controllers\Api\EccEquipeController;
use App\Http\Middleware\InitializeTenancyBySlug;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do tenant
|--------------------------------------------------------------------------
|
| Identificação por header X-Tenant (slug). Ver ADR-0002.
| Health/csrf centrais ficam em routes/api.php (não duplicar aqui).
|
*/

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

    Route::post('web/login', [AuthWebController::class, 'auth']);
    Route::post('web/me', [AuthWebController::class, 'me'])->middleware('cookie.to.token');
    Route::post('web/logout', [AuthWebController::class, 'logout'])->middleware('cookie.to.token');
});

/*
 | ECC: autentica super-admin no contexto central, depois inicializa o tenant.
 */
Route::middleware([
    'api',
    'auth:sanctum',
    InitializeTenancyBySlug::class,
])->prefix('api/v1')->group(function () {
    Route::apiResource('ecc/equipes', EccEquipeController::class);
    Route::post('ecc/casais/import', [EccCasalController::class, 'import']);
    Route::apiResource('ecc/casais', EccCasalController::class);
});
