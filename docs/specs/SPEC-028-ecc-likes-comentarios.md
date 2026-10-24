# SPEC-028 — Likes e comentários em recados e reflexões

**Status:** approved  
**Data:** 2026-10-24  
**Depende de:** SPEC-027

## Contexto

Recados e reflexões (SPEC-027) são só leitura. O líder precisa reagir (like) e conversar (comentários); a secretaria/admin precisa **revisar** comentários antes de ficarem públicos para todos.

## Objetivo

1. Like (toggle) em recado e reflexão publicados visíveis ao usuário.
2. Comentário em texto; autor com permissão de moderação já entra **aprovado**; demais entram **pendentes**.
3. Feed público de comentários: só `aprovado` (mais os próprios `pendente` do autor).
4. Moderação: listar, aprovar, rejeitar (motivo opcional) e ocultar; excluir (autor do próprio ou moderador).
5. Contadores `likes_count`, `liked_by_me`, `comentarios_count` no payload de listagem/detalhe.

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| Like | `ecc_likes` | `igreja_id`, `alvo_tipo` (`recado`\|`reflexao`), `alvo_id` (ULID), `user_id` (bigint FK users); unique por (igreja, tipo, alvo, user) |
| Comentário | `ecc_comentarios` | idem alvo + `corpo`, `status` (`pendente`\|`aprovado`\|`rejeitado`\|`oculto`), `revisado_por_user_id?`, `revisado_em?`, `motivo_revisao?` |

## Permissões

| Permissão | Quem | Uso |
|-----------|------|-----|
| (view do alvo) | quem já tem `ecc.recados.view` / `ecc.reflexoes.view` | Like e comentar no alvo visível |
| `ecc.interacoes.moderar` | `admin-igreja`, `admin-tenant` | Fila de revisão e ações de moderação |

## Critérios de aceite (testáveis)

- [x] Migration tenant: `ecc_likes`, `ecc_comentarios` + seed da permissão.
- [x] `POST/DELETE /ecc/recados/{id}/like` e `/ecc/reflexoes/{id}/like` — like/unlike; 403 sem view; 404 se alvo fora do escopo.
- [x] `GET/POST /ecc/{recados|reflexoes}/{id}/comentarios` — lista filtrada; POST cria pendente (ou aprovado se moderar).
- [x] `GET /ecc/interacoes/comentarios?status=` — só `ecc.interacoes.moderar` (ou manage).
- [x] `POST .../comentarios/{id}/aprovar|rejeitar|ocultar` — moderar; 403 líder.
- [x] `DELETE /ecc/interacoes/comentarios/{id}` — autor do próprio (pendente/aprovado) ou moderador.
- [x] Serialize do alvo inclui `likes_count`, `liked_by_me`, `comentarios_count` (só aprovados).
- [x] Front: botão like + lista/form de comentários nas views; painel de pendentes para moderador.
- [x] Testes API + Vitest do util de status/contagem.

## Fora de escopo

- Respostas aninhadas (threads).
- Reações além de like.
- Notificações push/e-mail de novo comentário.

## Contrato

`api/docs/specs/openapi.yaml` — paths `/ecc/recados/{id}/like`, `/ecc/reflexoes/{id}/like`, `.../comentarios`, `/ecc/interacoes/comentarios*`.
