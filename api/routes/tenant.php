<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppBrandingController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CalendarioController;
use App\Http\Controllers\Api\AuthWebController;
use App\Http\Controllers\Api\EccCasalController;
use App\Http\Controllers\Api\EccEquipeController;
use App\Http\Controllers\Api\EccEquipeServicoController;
use App\Http\Controllers\Api\EccEventoController;
use App\Http\Controllers\Api\EccFinanceiroController;
use App\Http\Controllers\Api\EventoTipoController;
use App\Http\Controllers\Api\IgrejaController;
use App\Http\Controllers\Api\PastoralController;
use App\Http\Controllers\Api\PessoaFotoController;
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

    Route::prefix('public/calendario')->group(function () {
        Route::get('coleta/{token}', [CalendarioController::class, 'showColetaPublica']);
        Route::post('coleta/{token}', [CalendarioController::class, 'storeColetaPublica'])
            ->middleware('throttle:30,1');
    });

    // Foto: URL assinada + ?tenant= (img não envia X-Tenant/Bearer). Spec SPEC-006 / ADR-0002.
    Route::get('pessoas/{id}/foto', [PessoaFotoController::class, 'show'])
        ->middleware('signed')
        ->name('pessoas.foto.show');

    // Logo do app: URL assinada + ?tenant= (SPEC-014).
    Route::get('app/branding/logo', [AppBrandingController::class, 'showLogo'])
        ->middleware('signed')
        ->name('app.branding.logo.show');
    Route::get('app/branding/logo-diocese', [AppBrandingController::class, 'showLogoDiocese'])
        ->middleware('signed')
        ->name('app.branding.logo-diocese.show');

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
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::apiResource('igrejas', IgrejaController::class);

    Route::post('users/{user}/roles', [UserController::class, 'assignRole']);
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
    Route::delete('users/{user}/roles', [UserController::class, 'removeRole']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('ecc/equipes', EccEquipeController::class)->names('ecc.equipes');
    Route::get('ecc/equipes-servico', [EccEquipeServicoController::class, 'index']);
    Route::post('ecc/casais/import', [EccCasalController::class, 'import']);
    Route::post('ecc/casais/{id}/swap', [EccCasalController::class, 'swap']);
    Route::apiResource('ecc/casais', EccCasalController::class)->names('ecc.casais');

    Route::post('ecc/eventos/{id}/participantes', [EccEventoController::class, 'addParticipante']);
    Route::put('ecc/eventos/{id}/participantes', [EccEventoController::class, 'updateParticipante']);
    Route::delete('ecc/eventos/{id}/participantes', [EccEventoController::class, 'removeParticipante']);
    Route::get('ecc/eventos/{id}/itens-compra', [EccEventoController::class, 'listItens']);
    Route::post('ecc/eventos/{id}/itens-compra', [EccEventoController::class, 'storeItem']);
    Route::post('ecc/eventos/{id}/itens-compra/{itemId}/doar', [EccEventoController::class, 'doarItem']);
    Route::post('ecc/eventos/{id}/itens-compra/{itemId}/comprar', [EccEventoController::class, 'comprarItem']);
    Route::post('ecc/eventos/{id}/itens-compra/{itemId}/desfazer', [EccEventoController::class, 'desfazerItem']);
    Route::delete('ecc/eventos/{id}/itens-compra/{itemId}', [EccEventoController::class, 'destroyItem']);
    Route::get('ecc/eventos/{id}/caixa', [EccEventoController::class, 'caixa']);
    Route::post('ecc/eventos/{id}/caixa/doacoes', [EccEventoController::class, 'doarDinheiro']);
    // Prefixo URI "ecc/" não entra no nome — sem ->names(), colide com apiResource('eventos').
    Route::apiResource('ecc/eventos', EccEventoController::class)->names('ecc.eventos');

    Route::get('ecc/financeiro', [EccFinanceiroController::class, 'index']);
    Route::get('ecc/financeiro/contas', [EccFinanceiroController::class, 'listContas']);
    Route::post('ecc/financeiro/contas', [EccFinanceiroController::class, 'storeConta']);
    Route::put('ecc/financeiro/contas/{id}', [EccFinanceiroController::class, 'updateConta']);
    Route::delete('ecc/financeiro/contas/{id}', [EccFinanceiroController::class, 'destroyConta']);
    Route::post('ecc/financeiro/lancamentos', [EccFinanceiroController::class, 'storeLancamento']);
    Route::put('ecc/financeiro/lancamentos/{id}', [EccFinanceiroController::class, 'updateLancamento']);
    Route::delete('ecc/financeiro/lancamentos/{id}', [EccFinanceiroController::class, 'destroyLancamento']);
    Route::post('ecc/financeiro/transferencias', [EccFinanceiroController::class, 'transferir']);
    Route::post('ecc/financeiro/transportar', [EccFinanceiroController::class, 'transportar']);

    Route::get('eventos/tipos', [EventoTipoController::class, 'index']);
    Route::post('eventos/tipos', [EventoTipoController::class, 'store']);
    Route::put('eventos/tipos/{id}', [EventoTipoController::class, 'update']);
    Route::delete('eventos/tipos/{id}', [EventoTipoController::class, 'destroy']);
    Route::post('eventos/{id}/participantes', [EccEventoController::class, 'addParticipante']);
    Route::put('eventos/{id}/participantes', [EccEventoController::class, 'updateParticipante']);
    Route::delete('eventos/{id}/participantes', [EccEventoController::class, 'removeParticipante']);
    Route::get('eventos/{id}/itens-compra', [EccEventoController::class, 'listItens']);
    Route::post('eventos/{id}/itens-compra', [EccEventoController::class, 'storeItem']);
    Route::post('eventos/{id}/itens-compra/{itemId}/doar', [EccEventoController::class, 'doarItem']);
    Route::post('eventos/{id}/itens-compra/{itemId}/comprar', [EccEventoController::class, 'comprarItem']);
    Route::post('eventos/{id}/itens-compra/{itemId}/desfazer', [EccEventoController::class, 'desfazerItem']);
    Route::delete('eventos/{id}/itens-compra/{itemId}', [EccEventoController::class, 'destroyItem']);
    Route::get('eventos/{id}/caixa', [EccEventoController::class, 'caixa']);
    Route::post('eventos/{id}/caixa/doacoes', [EccEventoController::class, 'doarDinheiro']);
    Route::apiResource('eventos', EccEventoController::class)->names('eventos');

    Route::get('calendario/locais', [CalendarioController::class, 'indexLocais']);
    Route::get('calendario/evento-tipos', [CalendarioController::class, 'indexEventoTipos']);
    Route::post('calendario/evento-tipos', [CalendarioController::class, 'storeEventoTipo']);
    Route::put('calendario/evento-tipos/{id}', [CalendarioController::class, 'updateEventoTipo']);
    Route::delete('calendario/evento-tipos/{id}', [CalendarioController::class, 'destroyEventoTipo']);
    Route::post('calendario/locais', [CalendarioController::class, 'storeLocal']);
    Route::put('calendario/locais/{id}', [CalendarioController::class, 'updateLocal']);
    Route::delete('calendario/locais/{id}', [CalendarioController::class, 'destroyLocal']);
    Route::get('calendario/slots-padrao', [CalendarioController::class, 'indexSlots']);
    Route::post('calendario/slots-padrao', [CalendarioController::class, 'storeSlot']);
    Route::put('calendario/slots-padrao/{id}', [CalendarioController::class, 'updateSlot']);
    Route::delete('calendario/slots-padrao/{id}', [CalendarioController::class, 'destroySlot']);
    Route::get('calendario/mensais', [CalendarioController::class, 'indexMensais']);
    Route::post('calendario/mensais', [CalendarioController::class, 'storeMensal']);
    Route::get('calendario/mensais/{id}', [CalendarioController::class, 'showMensal']);
    Route::put('calendario/mensais/{id}', [CalendarioController::class, 'updateMensal']);
    Route::delete('calendario/mensais/{id}', [CalendarioController::class, 'destroyMensal']);
    Route::post('calendario/mensais/{id}/copiar-proximo', [CalendarioController::class, 'copiarProximo']);
    Route::post('calendario/mensais/{id}/status', [CalendarioController::class, 'transitionStatus']);
    Route::post('calendario/mensais/{id}/itens', [CalendarioController::class, 'storeItem']);
    Route::put('calendario/itens/{itemId}', [CalendarioController::class, 'updateItem']);
    Route::delete('calendario/itens/{itemId}', [CalendarioController::class, 'destroyItem']);
    Route::post('calendario/mensais/{id}/observacoes', [CalendarioController::class, 'storeObservacao']);
    Route::put('calendario/observacoes/{id}', [CalendarioController::class, 'updateObservacao']);
    Route::delete('calendario/observacoes/{id}', [CalendarioController::class, 'destroyObservacao']);
    Route::put('calendario/tempos-liturgicos/{id}', [CalendarioController::class, 'updateTempoLiturgico']);
    Route::post('calendario/mensais/{id}/coleta-links', [CalendarioController::class, 'storeColetaLink']);
    Route::post('calendario/mensais/{id}/indisponibilidades', [CalendarioController::class, 'storeIndisponibilidades']);
    Route::get('calendario/mensais/{id}/pdf', [CalendarioController::class, 'pdf']);

    Route::post('pessoas/{id}/foto', [PessoaFotoController::class, 'store']);
    Route::delete('pessoas/{id}/foto', [PessoaFotoController::class, 'destroy']);

    Route::get('app/branding', [AppBrandingController::class, 'show']);
    Route::patch('app/branding', [AppBrandingController::class, 'update']);
    Route::post('app/branding/logo', [AppBrandingController::class, 'storeLogo']);
    Route::delete('app/branding/logo', [AppBrandingController::class, 'destroyLogo']);
    Route::post('app/branding/logo-diocese', [AppBrandingController::class, 'storeLogoDiocese']);
    Route::delete('app/branding/logo-diocese', [AppBrandingController::class, 'destroyLogoDiocese']);

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
        ->parameters(['comunicados' => 'comunicado'])
        ->names('site.comunicados');
    Route::apiResource('site/pastorais', PastoralController::class)
        ->parameters(['pastorais' => 'pastoral'])
        ->names('site.pastorais');
});
