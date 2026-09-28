# SPEC-017 — Home do líder de equipe ECC

**Status:** draft
**Data:** 2026-09-28
**Depende de:** SPEC-003, SPEC-015

## Contexto

Quem tem só o papel `lider-equipe` entra no mesmo launcher de quem administra o movimento (casais, eventos, calendário) e a API recusa qualquer atualização de casal. A atualização cadastral da própria equipe é o único trabalho desse perfil.

## Objetivo

1. Quem tem **somente** `lider-equipe` abre direto a tela da equipe, sem o launcher.
2. Quem acumula outro papel continua no launcher.
3. O líder atualiza a ficha dos casais já vinculados às equipes que lidera.
4. Criar, excluir, importar, trocar Ele/Ela e mover de equipe continuam com quem tem `ecc.casais.manage`.
5. Tour na primeira visita, com fechar e rever.

## Critérios de aceite (testáveis)

- [ ] Permissão `ecc.casais.atualizar` no papel `lider-equipe`, no lugar de `telas.eventos`, `ecc.eventos.view`, `ecc.escala.editar`, `telas.calendario` e `calendario.colaborar`.
- [ ] `PUT /ecc/casais/{id}` com `ecc.casais.atualizar` grava a ficha se o casal está numa equipe vinculada; casal de outra equipe → 404.
- [ ] `equipe_id` e `equipe` no payload são ignorados: o casal permanece na equipe atual.
- [ ] `POST`, `DELETE`, import e swap seguem 403 para o líder.
- [ ] Upload e remoção de foto com `ecc.casais.atualizar` só para pessoa de casal da equipe vinculada; pessoa de outra equipe → 403.
- [ ] Front: papel exclusivo `lider-equipe` redireciona `/inicio` para `/ecc/minha-equipe`. Outro papel junto mantém o launcher.
- [ ] A home lista os casais da equipe (nome, telefone, e-mail) e abre a ficha em “Atualizar cadastro”. Várias equipes: seletor no topo.
- [ ] Formulário do líder: equipe só leitura; sem novo, importar, excluir ou trocar Ele/Ela. Shell sem “Todos os módulos”, eventos e relatórios.
- [ ] Tour abre na primeira visita, cada passo tem Fechar, e “Ver tour” reinicia do passo 1. Flag `eclesia.tour.lider-equipe.v1` no `localStorage`.

## Fora de escopo

- Incluir ou retirar casais da equipe.
- Tour nas demais telas do produto.
- Eventos, escala e calendário para o papel `lider-equipe`.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — `PUT /ecc/casais/{id}` e `POST`/`DELETE /pessoas/{id}/foto`.

## Notas

- “Somente líder” = o conjunto de papéis do usuário é exatamente `lider-equipe` (pode liderar N equipes).
- A ficha é a da SPEC-015: contato, endereço, cônjuges, etapas, engajamento, habilidades, atividades e preferências.
