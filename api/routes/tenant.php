<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppBrandingController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ClientDiagnosticController;
use App\Http\Controllers\Api\CalendarioController;
use App\Http\Controllers\Api\AuthWebController;
use App\Http\Controllers\Api\EccCasalController;
use App\Http\Controllers\Api\EccEquipeController;
use App\Http\Controllers\Api\EccEquipeServicoController;
use App\Http\Controllers\Api\EccEventoController;
use App\Http\Controllers\Api\EccFinanceiroController;
use App\Http\Controllers\Api\EccFinanceiroDepositoController;
use App\Http\Controllers\Api\EccInteracaoController;
use App\Http\Controllers\Api\EccMuralController;
use App\Http\Controllers\Api\EccPerguntaController;
use App\Http\Controllers\Api\EccRecadoController;
use App\Http\Controllers\Api\EccReflexaoController;
use App\Http\Controllers\Api\EventoTipoController;
use App\Http\Controllers\Api\IgrejaController;
use App\Http\Controllers\Api\PastoralController;
use App\Http\Controllers\Api\PastoraisController;
use App\Http\Controllers\Api\PlanejamentoController;
use App\Http\Controllers\Api\PlanejamentoRecursosController;
use App\Http\Controllers\Api\PessoaFotoController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SiteAdminController;
use App\Http\Controllers\Api\SiteComunicadoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateTenantApi;
use App\Http\Middleware\InitializeTenancyByPathSlug;
use App\Http\Middleware\InitializeTenancyBySlug;
use App\Http\Middleware\SetIgrejaFromHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do tenant
|--------------------------------------------------------------------------
|
| Login resolve tenant pelo body (sem X-Tenant). Demais rotas exigem X-Tenant.
| HTML/sitemap/manifest: tenancy pelo slug no path (ADR-0003 / ADR-0009).
| Recursos autenticados: SuperAdmin (token central) OU User (token do tenant).
|
*/

Route::middleware(['api'])->prefix('api/v1')->group(function () {
    Route::post('web/login', [AuthWebController::class, 'auth']);
});

Route::middleware([
    'api',
    InitializeTenancyByPathSlug::class,
])->prefix('api/v1/public/site')->group(function () {
    Route::get('html/{tenantSlug}', [PublicSiteController::class, 'htmlHome']);
    Route::get('html/{tenantSlug}/{pageSlug}', [PublicSiteController::class, 'htmlPage']);
    Route::get('install-html/{tenantSlug}', [PublicSiteController::class, 'installHtml']);
    Route::get('sitemap/{tenantSlug}', [PublicSiteController::class, 'sitemap']);
    Route::get('manifest/{tenantSlug}', [PublicSiteController::class, 'manifest']);
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

    Route::prefix('public/planejamento')->group(function () {
        Route::get('{token}', [PlanejamentoController::class, 'showPublico']);
        Route::get('{token}/eventos', [PlanejamentoController::class, 'indexEventosPublicos']);
        Route::get('{token}/disponibilidade', [PlanejamentoController::class, 'disponibilidadePublica'])
            ->middleware('throttle:60,1');
        Route::post('{token}/eventos', [PlanejamentoController::class, 'storeEventoPublico'])
            ->middleware('throttle:30,1');
        Route::patch('{token}/eventos/{eventoId}', [PlanejamentoController::class, 'updateEventoPublico'])
            ->middleware('throttle:30,1');
        Route::delete('{token}/eventos/{eventoId}', [PlanejamentoController::class, 'destroyEventoPublico'])
            ->middleware('throttle:30,1');
    });

    // Foto: URL assinada + ?tenant= (img não envia X-Tenant/Bearer). Spec SPEC-006 / ADR-0002.
    Route::get('pessoas/{id}/foto', [PessoaFotoController::class, 'show'])
        ->middleware('signed')
        ->name('pessoas.foto.show');

    // Imagem de reflexão: URL assinada (SPEC-027).
    Route::get('ecc/reflexoes/{id}/imagem-publica', [EccReflexaoController::class, 'imagemAssinada'])
        ->middleware('signed')
        ->name('ecc.reflexoes.imagem.show');

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
    Route::post('web/change-password', [AuthWebController::class, 'changePassword'])
        ->middleware(['cookie.to.token', 'auth:sanctum', SetIgrejaFromHeader::class]);
    Route::post('web/view-as/lider-equipe', [AuthWebController::class, 'activateViewAsLider'])
        ->middleware(['cookie.to.token', 'auth:sanctum', SetIgrejaFromHeader::class, 'password.changed']);
    Route::delete('web/view-as', [AuthWebController::class, 'clearViewAs'])
        ->middleware(['cookie.to.token', 'auth:sanctum', SetIgrejaFromHeader::class, 'password.changed']);
    Route::post('web/logout', [AuthWebController::class, 'logout'])->middleware(['cookie.to.token', 'auth:sanctum']);
});

Route::middleware([
    'api',
    'cookie.to.token',
    AuthenticateTenantApi::class,
    SetIgrejaFromHeader::class,
    'password.changed',
])->prefix('api/v1')->group(function () {
    Route::post('broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

    Route::get('roles', [RolePermissionController::class, 'roles']);
    Route::get('permissions', [RolePermissionController::class, 'permissions']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('client-diagnostics', [ClientDiagnosticController::class, 'index']);
    Route::post('client-diagnostics', [ClientDiagnosticController::class, 'store']);
    Route::get('client-diagnostics/{id}', [ClientDiagnosticController::class, 'show']);
    Route::apiResource('igrejas', IgrejaController::class);

    Route::post('users/{user}/roles', [UserController::class, 'assignRole']);
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
    Route::delete('users/{user}/roles', [UserController::class, 'removeRole']);
    Route::apiResource('users', UserController::class);

    Route::put('ecc/equipes/{id}/casais', [EccEquipeController::class, 'syncCasais']);
    Route::post('ecc/equipes/{id}/acesso', [EccEquipeController::class, 'criarAcesso']);
    Route::put('ecc/equipes/{id}/acesso', [EccEquipeController::class, 'alterarLoginAcesso']);
    Route::post('ecc/equipes/{id}/acesso/reset-senha', [EccEquipeController::class, 'resetSenhaAcesso']);
    Route::get('ecc/equipes/pdf', [EccEquipeController::class, 'pdf']);
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
    Route::get('ecc/financeiro/anos', [EccFinanceiroController::class, 'anos']);
    Route::post('ecc/financeiro/import', [EccFinanceiroController::class, 'import']);
    Route::post('ecc/financeiro/zerar', [EccFinanceiroController::class, 'zerar']);
    Route::get('ecc/financeiro/contas', [EccFinanceiroController::class, 'listContas']);
    Route::post('ecc/financeiro/contas', [EccFinanceiroController::class, 'storeConta']);
    Route::put('ecc/financeiro/contas/{id}', [EccFinanceiroController::class, 'updateConta']);
    Route::delete('ecc/financeiro/contas/{id}', [EccFinanceiroController::class, 'destroyConta']);
    Route::post('ecc/financeiro/lancamentos', [EccFinanceiroController::class, 'storeLancamento']);
    Route::put('ecc/financeiro/lancamentos/{id}', [EccFinanceiroController::class, 'updateLancamento']);
    Route::delete('ecc/financeiro/lancamentos/{id}', [EccFinanceiroController::class, 'destroyLancamento']);
    Route::post('ecc/financeiro/transferencias', [EccFinanceiroController::class, 'transferir']);
    Route::post('ecc/financeiro/transportar', [EccFinanceiroController::class, 'transportar']);
    Route::get('ecc/financeiro/depositos', [EccFinanceiroDepositoController::class, 'index']);
    Route::post('ecc/financeiro/depositos', [EccFinanceiroDepositoController::class, 'store']);
    Route::match(['put', 'post'], 'ecc/financeiro/depositos/{id}', [EccFinanceiroDepositoController::class, 'update']);
    Route::get('ecc/financeiro/depositos/{id}/comprovante', [EccFinanceiroDepositoController::class, 'comprovante']);
    Route::post('ecc/financeiro/depositos/{id}/aprovar', [EccFinanceiroDepositoController::class, 'aprovar']);
    Route::post('ecc/financeiro/depositos/{id}/rejeitar', [EccFinanceiroDepositoController::class, 'rejeitar']);

    Route::get('ecc/mural/nao-lidos', [EccMuralController::class, 'naoLidos']);

    Route::get('ecc/perguntas/resumo', [EccPerguntaController::class, 'resumo']);
    Route::get('ecc/perguntas', [EccPerguntaController::class, 'index']);
    Route::post('ecc/perguntas', [EccPerguntaController::class, 'store']);
    Route::post('ecc/perguntas/envios/{envioId}/responder', [EccPerguntaController::class, 'responder']);
    Route::get('ecc/perguntas/{id}/envios', [EccPerguntaController::class, 'listEnvios']);
    Route::post('ecc/perguntas/{id}/envios', [EccPerguntaController::class, 'storeEnvio']);
    Route::post('ecc/perguntas/{id}/disponibilizar', [EccPerguntaController::class, 'disponibilizar']);
    Route::post('ecc/perguntas/{id}/arquivar', [EccPerguntaController::class, 'arquivar']);
    Route::get('ecc/perguntas/{id}', [EccPerguntaController::class, 'show']);
    Route::put('ecc/perguntas/{id}', [EccPerguntaController::class, 'update']);
    Route::delete('ecc/perguntas/{id}', [EccPerguntaController::class, 'destroy']);

    Route::get('ecc/recados/novidades', [EccRecadoController::class, 'novidades']);
    Route::get('ecc/recados', [EccRecadoController::class, 'index']);
    Route::post('ecc/recados', [EccRecadoController::class, 'store']);
    Route::post('ecc/recados/{id}/like', [EccInteracaoController::class, 'likeRecado']);
    Route::delete('ecc/recados/{id}/like', [EccInteracaoController::class, 'unlikeRecado']);
    Route::get('ecc/recados/{id}/comentarios', [EccInteracaoController::class, 'listComentariosRecado']);
    Route::post('ecc/recados/{id}/comentarios', [EccInteracaoController::class, 'storeComentarioRecado']);
    Route::post('ecc/recados/{id}/ler', [EccMuralController::class, 'lerRecado']);
    Route::get('ecc/recados/{id}/engajamento', [EccMuralController::class, 'engajamentoRecado']);
    Route::get('ecc/recados/{id}/anexo', [EccRecadoController::class, 'anexo']);
    Route::get('ecc/recados/{id}', [EccRecadoController::class, 'show']);
    Route::match(['put', 'post'], 'ecc/recados/{id}', [EccRecadoController::class, 'update']);
    Route::delete('ecc/recados/{id}', [EccRecadoController::class, 'destroy']);

    Route::get('ecc/reflexoes', [EccReflexaoController::class, 'index']);
    Route::post('ecc/reflexoes', [EccReflexaoController::class, 'store']);
    Route::post('ecc/reflexoes/{id}/like', [EccInteracaoController::class, 'likeReflexao']);
    Route::delete('ecc/reflexoes/{id}/like', [EccInteracaoController::class, 'unlikeReflexao']);
    Route::get('ecc/reflexoes/{id}/comentarios', [EccInteracaoController::class, 'listComentariosReflexao']);
    Route::post('ecc/reflexoes/{id}/comentarios', [EccInteracaoController::class, 'storeComentarioReflexao']);
    Route::post('ecc/reflexoes/{id}/ler', [EccMuralController::class, 'lerReflexao']);
    Route::get('ecc/reflexoes/{id}/engajamento', [EccMuralController::class, 'engajamentoReflexao']);
    Route::get('ecc/reflexoes/{id}/imagem', [EccReflexaoController::class, 'imagem']);
    Route::get('ecc/reflexoes/{id}/anexo', [EccReflexaoController::class, 'anexo']);
    Route::get('ecc/reflexoes/{id}', [EccReflexaoController::class, 'show']);
    Route::match(['put', 'post'], 'ecc/reflexoes/{id}', [EccReflexaoController::class, 'update']);
    Route::delete('ecc/reflexoes/{id}', [EccReflexaoController::class, 'destroy']);

    Route::get('ecc/interacoes/comentarios', [EccInteracaoController::class, 'listModeracao']);
    Route::post('ecc/interacoes/comentarios/{id}/aprovar', [EccInteracaoController::class, 'aprovar']);
    Route::post('ecc/interacoes/comentarios/{id}/rejeitar', [EccInteracaoController::class, 'rejeitar']);
    Route::post('ecc/interacoes/comentarios/{id}/ocultar', [EccInteracaoController::class, 'ocultar']);
    Route::delete('ecc/interacoes/comentarios/{id}', [EccInteracaoController::class, 'destroyComentario']);

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

    Route::get('pastorais', [PastoraisController::class, 'index']);
    Route::post('pastorais', [PastoraisController::class, 'store']);
    Route::get('pastorais/{id}', [PastoraisController::class, 'show']);
    Route::patch('pastorais/{id}', [PastoraisController::class, 'update']);
    Route::delete('pastorais/{id}', [PastoraisController::class, 'destroy']);
    Route::get('pastorais/{id}/membros', [PastoraisController::class, 'indexMembros']);
    Route::post('pastorais/{id}/membros', [PastoraisController::class, 'storeMembro']);
    Route::delete('pastorais/{id}/membros/{userId}', [PastoraisController::class, 'destroyMembro']);

    Route::get('planejamento/anuais', [PlanejamentoController::class, 'indexAnuais']);
    Route::post('planejamento/anuais', [PlanejamentoController::class, 'storeAnual']);
    Route::get('planejamento/anuais/{id}', [PlanejamentoController::class, 'showAnual']);
    Route::patch('planejamento/anuais/{id}', [PlanejamentoController::class, 'updateAnual']);
    Route::delete('planejamento/anuais/{id}', [PlanejamentoController::class, 'destroyAnual']);
    Route::post('planejamento/anuais/{id}/status', [PlanejamentoController::class, 'transitionStatus']);
    Route::get('planejamento/anuais/{id}/eventos', [PlanejamentoController::class, 'indexEventos']);
    Route::post('planejamento/anuais/{id}/eventos', [PlanejamentoController::class, 'storeEvento']);
    Route::get('planejamento/eventos/{eventoId}', [PlanejamentoController::class, 'showEvento']);
    Route::patch('planejamento/eventos/{eventoId}', [PlanejamentoController::class, 'updateEvento']);
    Route::delete('planejamento/eventos/{eventoId}', [PlanejamentoController::class, 'destroyEvento']);
    Route::get('planejamento/anuais/{id}/pdf', [PlanejamentoController::class, 'pdf']);
    Route::get('planejamento/anuais/{id}/disponibilidade', [PlanejamentoController::class, 'disponibilidade']);
    Route::get('planejamento/recursos', [PlanejamentoRecursosController::class, 'index']);
    Route::post('planejamento/recursos', [PlanejamentoRecursosController::class, 'store']);
    Route::patch('planejamento/recursos/{id}', [PlanejamentoRecursosController::class, 'update']);
    Route::delete('planejamento/recursos/{id}', [PlanejamentoRecursosController::class, 'destroy']);
    Route::get('planejamento/anuais/{id}/pastorais/tokens', [PlanejamentoController::class, 'indexPastoralTokens']);
    Route::post('planejamento/anuais/{id}/pastorais/{pastoralId}/token', [PlanejamentoController::class, 'storePastoralToken']);
    Route::delete('planejamento/anuais/{id}/pastorais/{pastoralId}/token', [PlanejamentoController::class, 'destroyPastoralToken']);

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
