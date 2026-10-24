# SPEC-030 — ECC: caixa de perguntas (formulário customizado)

**Status:** approved  
**Data:** 2026-10-24  
**Depende de:** SPEC-017 (home do líder), SPEC-021 (acesso de equipe), SPEC-027 (shell do líder)

## Contexto

A secretaria/admin precisa solicitar informações às equipes (ex.: confirmação de presença, dados para evento) com campos variáveis. O líder responde no app; um **usuário responsável** acompanha os envios, recebe aviso em tempo real e devolve uma resposta que o líder consulta depois.

## Decisões de produto (esta fatia)

1. Admin (`ecc.perguntas.manage`) monta um **formulário** com campos: `texto_curto`, `texto_longo`, `lista` (opções fixas), escolhe o **usuário responsável** e **disponibiliza**.
2. Enquanto `disponivel`, todo líder da igreja vê o formulário na **Minha equipe** (cartão + rota dedicada) — **sem 4ª aba** na bottom nav (mantém as três áreas da SPEC-027).
3. **Um envio por equipe** por formulário (conta compartilhada da equipe / líder vinculado).
4. Ao enviar: broadcast Pusher no canal privado do responsável + registro consultável na API.
5. Responsável responde em texto; o líder **vê a resposta** ao reabrir o envio. **Sem push ao líder** nesta fatia.
6. Rascunho não aparece para o líder; arquivado some do feed (envios já feitos continuam legíveis).

## Objetivo

1. CRUD de formulários + campos ordenados.
2. Feed/envio para líderes; resposta do responsável.
3. Notificação realtime ao responsável via **Pusher Channels** (Laravel Echo no front quando as chaves estiverem configuradas).
4. Encaixe no shell atual do líder (desktop sidebar 3 itens + mobile bottom nav 3 itens) via cartão na Minha equipe e item opcional na sidebar admin.

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| Formulário | `ecc_perguntas` | `igreja_id`, `titulo`, `descricao?`, `status` (`rascunho`\|`disponivel`\|`arquivado`), `responsavel_user_id` (FK `users.id`), `disponibilizado_em?`, `criado_por_user_id` |
| Campo | `ecc_pergunta_campos` | `pergunta_id`, `ordem`, `tipo` (`texto_curto`\|`texto_longo`\|`lista`), `rotulo`, `obrigatorio`, `opcoes` JSON nullable (lista) |
| Envio | `ecc_pergunta_envios` | `pergunta_id`, `equipe_id`, `enviado_por_user_id`, `respostas` JSON `{campo_id: valor}`, `status` (`enviado`\|`respondido`), `enviado_em`, `resposta_responsavel` TEXT `$encryptable`, `respondido_em?`, `respondido_por_user_id?` |

Índice único: `(pergunta_id, equipe_id)`.

LGPD: `resposta_responsavel` e valores de campos livres em `respostas` podem conter PII — coluna TEXT; `resposta_responsavel` com `$encryptable`. JSON `respostas` armazenado como TEXT (sem índice por valor).

## Permissões

| Permissão | Quem | Uso |
|-----------|------|-----|
| `ecc.perguntas.view` | `lider-equipe` (+ admins) | Listar disponíveis, enviar, ver próprio envio + resposta |
| `ecc.perguntas.manage` | `admin-igreja`, `admin-tenant` | CRUD formulário, arquivar, ver todos os envios |
| (implícito) | usuário = `responsavel_user_id` | Listar envios do formulário e responder (mesmo sem manage) |

`ViewAsLiderService::LIDER_PERMISSIONS` inclui `ecc.perguntas.view`.

## Critérios de aceite (testáveis)

- [ ] Migration tenant: três tabelas + sync de permissões.
- [ ] Seed: permissões; líder ganha `view`; admins `view`+`manage`.
- [ ] `GET/POST /ecc/perguntas`, `GET/PUT/DELETE /ecc/perguntas/{id}`, `POST .../disponibilizar`, `POST .../arquivar`.
- [ ] Campos: criados/atualizados no body do formulário (array `campos[]`); lista exige `opcoes` ≥ 1.
- [ ] Líder: lista só `disponivel` (+ envios da(s) equipe(s) dele); 403 em manage.
- [ ] `POST /ecc/perguntas/{id}/envios` — valida obrigatórios; 422 se já existe envio da equipe; dispara evento broadcast.
- [ ] `GET /ecc/perguntas/{id}/envios` — manage ou responsável; líder só o da própria equipe via show do formulário.
- [ ] `POST /ecc/perguntas/envios/{envioId}/responder` — só responsável (ou manage); grava resposta criptografada.
- [ ] `GET /broadcasting/auth` (Sanctum + tenant) autoriza `private-eclesia.user.{ulid}` só para o próprio usuário.
- [ ] Front líder: cartão na Minha equipe com badge (pendentes / respostas novas); rotas `/ecc/perguntas` e `/ecc/perguntas/:id`.
- [ ] Front admin: CRUD na mesma rota com `*.manage`; inbox de envios para responsável.
- [ ] Sem 4ª aba no `LiderBottomNav`.
- [ ] Testes API (401/403/422/sucesso/único por equipe/broadcast) + Vitest (cartão/badge/nav inalterado).

## Fora de escopo

- Push ao líder quando o responsável responde.
- Pusher Beams / FCM nativo (só Channels + Echo in-app).
- Anexos nos campos; múltiplos envios por equipe; edição do envio após enviar.
- Escopo por etapa/equipe no formulário (todos os líderes da igreja).
- E-mail.

## Contrato

`api/docs/specs/openapi.yaml` — `/ecc/perguntas*`, schema `EccPergunta`, `EccPerguntaCampo`, `EccPerguntaEnvio`.

## Notas de UX

- Cartão compacto na Minha equipe (uma linha de ação), não dashboard novo.
- Formulário em tela full-width `max-w-2xl` como Recados.
- Desktop admin: item “Perguntas” na sidebar ECC junto de Recados/Reflexões.
