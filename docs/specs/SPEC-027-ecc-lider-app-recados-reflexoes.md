# SPEC-027 — App do líder: Minha equipe, Recados e Reflexões

**Status:** approved  
**Data:** 2026-10-23  
**Depende de:** SPEC-017 (home do líder), SPEC-003 (papéis), SPEC-019 (simular visão)

## Contexto

A home do líder (SPEC-017) é só revisão cadastral. O perfil precisa de uma experiência **mobile-first**, com comunicação (recados da secretaria) e espiritualidade (reflexões do diácono), sem virar painel administrativo.

Decisões de UX (produto, 2026-10-23):

1. **Minha equipe** é sempre a tela padrão ao entrar — mesmo com recados/reflexões novos.
2. Indicador **discreto** de novidades no ícone de Recados (ponto ou contador); sem modal automático.
3. **Reflexões** são conteúdo editorial: título, passagem bíblica, texto, imagem opcional e data; histórico das anteriores.
4. **Recados** têm validade: urgência, fixar e data em que deixam de aparecer.
5. Barra inferior **fixa no celular** com as três áreas; o conteúdo central rola.
6. Botão “Ver tour” vira **ícone de ajuda no cabeçalho**; o aviso de inclusão/exclusão fica como texto secundário **no final da lista** de casais.

## Objetivo

1. Shell do líder exclusivo (e visão simulada SPEC-019): três áreas — Minha equipe, Recados, Reflexões — com bottom nav no mobile.
2. Mural de recados publicados pela secretaria (permissão `ecc.recados.manage`), filtrados por validade e escopo.
3. Área de reflexões publicadas por quem tem `ecc.reflexoes.manage` (diácono / admin), visíveis a todos os líderes da igreja.
4. Contagem de recados novos no ícone, com base no último acesso do cliente (`desde` / `localStorage`).
5. Depósito mensal (SPEC-020) permanece na aba Minha equipe.

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| Recado | `ecc_recados` | `igreja_id`, `titulo`, `corpo`, `urgente`, `fixado`, `escopo` (`paroquia`\|`etapa`\|`equipe`), `equipe_id?`, `etapa?` (1–3), `publicado_em`, `valido_ate?`, `status` (`rascunho`\|`publicado`), `criado_por_user_id` |
| Reflexão | `ecc_reflexoes` | `igreja_id`, `titulo`, `passagem_biblica?`, `texto`, `imagem_path?`, `publicado_em` (date), `status`, `criado_por_user_id` |

Regras de feed (líder / `ecc.*.view`):

- Só `status=publicado`.
- Recado some do feed se `valido_ate` &lt; agora (timezone app).
- Escopo `paroquia`: todos os líderes da igreja.
- Escopo `equipe`: só líderes vinculados à `equipe_id`.
- Escopo `etapa`: líderes cujas equipes têm ao menos um casal com etapa ECC igual (1–3).
- Ordenação recados: `fixado` ↓, `urgente` ↓, `publicado_em` ↓.
- Reflexões: ordem `publicado_em` ↓ (histórico completo das publicadas).
- Imagem da reflexão: disk `public`, path tenant-aware; download autenticado.

## Permissões

| Permissão | Quem | Uso |
|-----------|------|-----|
| `ecc.recados.view` | `lider-equipe` (+ admins) | Feed / detalhe |
| `ecc.recados.manage` | `admin-igreja`, `admin-tenant` | CRUD secretaria |
| `ecc.reflexoes.view` | `lider-equipe` (+ admins) | Lista / detalhe / imagem |
| `ecc.reflexoes.manage` | `admin-igreja`, `admin-tenant` | Publicar como diácono |

## Critérios de aceite (testáveis)

- [ ] Migration tenant: `ecc_recados`, `ecc_reflexoes`.
- [ ] Seed: permissões acima; `lider-equipe` ganha `*.view`; admins ganham `*.view` + `*.manage`.
- [ ] `ViewAsLiderService::LIDER_PERMISSIONS` inclui `ecc.recados.view` e `ecc.reflexoes.view`.
- [ ] `GET /ecc/recados` — feed ativo para view; manage vê todos (filtro `status` opcional).
- [ ] `GET /ecc/recados/novidades?desde=` — `{ data: { count } }` só de publicados válidos no escopo do usuário.
- [ ] `POST/PUT/DELETE /ecc/recados` — manage; 403 para líder.
- [ ] Escopo equipe: líder de outra equipe não vê o recado; líder da equipe vê.
- [ ] Recado expirado (`valido_ate` no passado) some do feed do líder.
- [ ] `GET /ecc/reflexoes` — publicadas para view; manage lista todas.
- [ ] `POST/PUT/DELETE /ecc/reflexoes` (+ upload imagem) — manage; 403 para líder.
- [ ] `GET /ecc/reflexoes/{id}/imagem` — stream auth + view/manage.
- [ ] Front líder: bottom nav (Minha equipe | Recados | Reflexões); rota padrão `/ecc/minha-equipe`.
- [ ] Front: ponto/contador no ícone Recados sem abrir avisos sozinho.
- [ ] Front: tour = ícone de ajuda no cabeçalho; texto de inclusão/exclusão no final da lista.
- [ ] Front manage: CRUD simples de recados e reflexões nas rotas `/ecc/recados` e `/ecc/reflexoes` (botões quando `*.manage`).
- [ ] Testes API (401/403/422/sucesso/escopo) + Vitest (badge novidades, tour target, nav).

## Fora de escopo

- Push / e-mail de novo recado.
- Papel canônico `diacono` (usa-se `ecc.reflexoes.manage` no admin).
- Leitura confirmada no servidor (likes/comentários: SPEC-028).
- Dark mode dedicado (seguir DESIGN-GUIDE / tokens do tenant).

## Evolução prevista

Secretaria publica por paróquia, etapa do ECC ou equipe; reflexões continuam alcançando todos os participantes da igreja. O modelo de `escopo` já cobre isso nesta fatia.

## Contrato

`api/docs/specs/openapi.yaml` — `/ecc/recados*`, `/ecc/reflexoes*`.

## Notas

- Shell “cara de app”: composição limpa, bottom nav fixa no mobile, sem sidebar para `somente lider` / `view_as`.
- “Novidades” = recados com `publicado_em` &gt; último `desde` guardado no cliente ao visitar a aba Recados.
- Atualiza SPEC-017: passo “rever” aponta para o ícone de ajuda; texto de limite no rodapé da lista.
