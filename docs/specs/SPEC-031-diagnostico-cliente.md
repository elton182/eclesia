# SPEC-031 — Diagnóstico do cliente (suporte foto/PWA)

**Status:** approved  
**Data:** 2026-10-29  

## Contexto

Falhas de Galeria/Tirar foto em Android/PWA são difíceis de reproduzir. O suporte precisa de telemetria voluntária da sessão do usuário (versão do app, modo standalone, permissão de câmera, cliques e resultado do picker) sem pedir testes manuais constrangedores.

## Objetivo

1. O app coleta eventos de diagnóstico em memória durante a sessão.
2. Qualquer usuário autenticado pode enviar o pacote pelo menu do perfil (“Enviar diagnóstico”).
3. Admins (`auditoria.view`) listam e abrem os envios no tenant.

## Critérios de aceite (testáveis)

- [ ] Migration tenant: tabela `client_diagnostics` com ULID público.
- [ ] `POST /api/v1/client-diagnostics` (auth) grava meta + events (máx. 100 eventos, payload limitado); 401 sem auth.
- [ ] `GET /api/v1/client-diagnostics` lista paginada; requer `auditoria.view`; 403 sem permissão.
- [ ] `GET /api/v1/client-diagnostics/{ulid}` detalhe; 404 cross-tenant / inexistente.
- [ ] Isolamento por tenant.
- [ ] Front: buffer de eventos (foto + sessão); botão no menu do perfil; tela `/diagnosticos` para admin.
- [ ] Sem PII sensível nos events (sem nome/e-mail/telefone em claro; só user_id do ator autenticado).

## Fora de escopo

- Streaming contínuo / analytics de produto.
- Retenção automática / purge.
- Diagnóstico anônimo (sem login).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/client-diagnostics`.

## Notas

- Envio é explícito (botão); não envia sozinho em background.
- Meta inclui `app_version`, `standalone`, `camera_permission`, `user_agent`, rota atual.
