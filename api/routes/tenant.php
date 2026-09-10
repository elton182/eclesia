<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthWebController;
use App\Http\Controllers\Api\EccCasalController;
use App\Http\Controllers\Api\EccEquipeController;
use App\Http\Controllers\Api\IgrejaController;
use App\Http\Controllers\Api\PastoralController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SiteAdminController;
use App\Http\Controllers\Api\SiteComunicadoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateTenantApi;
use App\Http\Middleware\InitializeTenancyBySlug;
use App\Http\Middleware\SetIgrejaFromHeader;
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

    Route::prefix('public/site')->group(function () {
        Route::get('/', [PublicSiteController::class, 'show']);
        Route::get('pages/{slug}', [PublicSiteController::class, 'page']);
        Route::get('comunicados', [PublicSiteController::class, 'comunicados']);
        Route::get('comunicados/{id}', [PublicSiteController::class, 'comunicado']);
        Route::get('pastorais', [PublicSiteController::class, 'pastorais']);
        Route::get('igrejas', [PublicSiteController::class, 'igrejas']);
        Route::get('igrejas/{slug}', [PublicSiteController::class, 'igreja']);
        Route::get('forms/{slug}', [PublicSiteController::class, 'form']);
        Route::post('forms/{slug}/submissions', [PublicSiteController::class, 'submitForm'])
            ->middleware('throttle:10,1');
    });

    // Só usuário do tenant (token no DB do tenant)
    Route::post('web/refresh', [AuthWebController::class, 'refresh']);
    Route::post('web/me', [AuthWebController::class, 'me'])->middleware(['cookie.to.token', 'auth:sanctum', SetIgrejaFromHeader::class]);
    Route::post('web/logout', [AuthWebController::class, 'logout'])->middleware(['cookie.to.token', 'auth:sanctum']);
});

Route::middleware([
    'api',
    'cookie.to.token',
    AuthenticateTenantApi::class,
    SetIgrejaFromHeader::class,
])->prefix('api/v1')->group(function () {
    Route::get('roles', [RolePermissionController::class, 'roles']);
    Route::get('permissions', [RolePermissionController::class, 'permissions']);
    Route::apiResource('igrejas', IgrejaController::class);

    Route::post('users/{user}/roles', [UserController::class, 'assignRole']);
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
    Route::delete('users/{user}/roles', [UserController::class, 'removeRole']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('ecc/equipes', EccEquipeController::class);
    Route::post('ecc/casais/import', [EccCasalController::class, 'import']);
    Route::post('ecc/casais/{id}/swap', [EccCasalController::class, 'swap']);
    Route::apiResource('ecc/casais', EccCasalController::class);

    Route::get('site/settings', [SiteAdminController::class, 'settings']);
    Route::put('site/settings', [SiteAdminController::class, 'updateSettings']);
    Route::get('site/pages', [SiteAdminController::class, 'pages']);
    Route::post('site/pages', [SiteAdminController::class, 'storePage']);
    Route::get('site/pages/{page}', [SiteAdminController::class, 'showPage']);
    Route::put('site/pages/{page}', [SiteAdminController::class, 'updatePage']);
    Route::delete('site/pages/{page}', [SiteAdminController::class, 'destroyPage']);
    Route::get('site/media', [SiteAdminController::class, 'media']);
    Route::post('site/media', [SiteAdminController::class, 'storeMedia']);
    Route::get('site/forms', [SiteAdminController::class, 'forms']);
    Route::post('site/forms', [SiteAdminController::class, 'storeForm']);
    Route::get('site/forms/{form}', [SiteAdminController::class, 'showForm']);
    Route::put('site/forms/{form}', [SiteAdminController::class, 'updateForm']);
    Route::delete('site/forms/{form}', [SiteAdminController::class, 'destroyForm']);
    Route::get('site/forms/{form}/submissions', [SiteAdminController::class, 'submissions']);

    Route::apiResource('site/comunicados', SiteComunicadoController::class)
        ->parameters(['comunicados' => 'comunicado']);
    Route::apiResource('site/pastorais', PastoralController::class)
        ->parameters(['pastorais' => 'pastoral']);
});
