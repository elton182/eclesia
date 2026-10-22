# SPEC-020 — Depósito mensal da equipe ECC (provisionar → aprovar)

**Status:** approved  
**Data:** 2026-10-14  
**Depende de:** SPEC-016 (financeiro ECC), SPEC-017 (home do líder), SPEC-003 (papéis)  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

O líder de equipe de perseverança deposita mensalmente a contribuição da equipe (valor agregado) e precisa registrar o valor com comprovante. Hoje o financeiro ECC (SPEC-016) só aceita lançamentos manuais por quem tem `ecc.financeiro.manage`; o líder não acessa o livro. Esta fatia liga **Minha equipe** ao livro-caixa: o depósito entra **provisionado** e, após aprovação, **confirmado** (passa a contar no saldo).

Decisões de produto (2026-10-14):

1. Unidade = **valor agregado da equipe** no mês (não por casal).
2. Valor **livre** a cada competência (sem valor fixo obrigatório).
3. Escopo = **equipes de perseverança** (`ecc_equipes`).
4. Aprovação = usuário com `ecc.financeiro.manage` (já em `admin-igreja` / `admin-tenant`).
5. Comprovante = PDF / JPG / JPEG / PNG, máx. **5 MB**, um arquivo por depósito.

## Objetivo

1. Líder da equipe (`lider-equipe` vinculado em `ecc_equipe_user`) submete depósito mensal: competência (`YYYY-MM`), valor, conta destino (padrão: Conta ECC banco), comprovante.
2. Ao submeter, cria-se lançamento de **entrada** no financeiro com status `provisionado` (não entra no saldo do livro).
3. Usuário com `ecc.financeiro.manage` **aprova** → status `confirmado` (conta no saldo) ou **rejeita** com motivo → status `rejeitado` (não conta; líder pode reenviar na mesma competência).
4. Líder lista/edita só depósitos da(s) própria(s) equipe(s); enquanto `provisionado`, pode alterar valor/comprovante/conta; após `confirmado`, imutável.
5. UI: bloco em **Minha equipe** (líder) + fila de aprovação na tela **Financeiro** (gestor).

## Modelo

| Entidade | Tabela / colunas | Notas |
|----------|------------------|-------|
| DepósitoEquipe | `ecc_financeiro_depositos` | `igreja_id`, `ecc_equipe_id`, `competencia` (date = 1º dia do mês), `valor`, `ecc_financeiro_conta_id`, `ecc_financeiro_lancamento_id`, `comprovante_path`, `status` provisionado\|confirmado\|rejeitado, `motivo_rejeicao?`, `submetido_por_user_id`, `revisado_por_user_id?`, `revisado_em?` |
| LancamentoFinanceiro | `ecc_financeiro_lancamentos` | + `status` confirmado\|provisionado\|rejeitado (default `confirmado` para lançamentos manuais/import/transferência/abertura) |

Regras:

- Escopo `igreja_id`; ULID na URL.
- Competência única por equipe entre depósitos **não rejeitados** (um `provisionado` ou `confirmado` por equipe/mês).
- Histórico do lançamento gerado: `Depósito mensal — {nome equipe} — {MM/YYYY}` (`historico` permanece `$encryptable`).
- Livro (`GET /ecc/financeiro?ano=`): soma apenas lançamentos com `status=confirmado` (e mantém regras atuais de abertura/transferência).
- Provisionados aparecem na fila de aprovação e, opcionalmente, marcados no extrato do livro sem alterar totais.
- Comprovante no disk `public` sob path do tenant (`ecc/financeiro/comprovantes/...`); download autenticado.
- Rejeição: zera efeito no saldo; `ecc_financeiro_lancamento_id` permanece com `status=rejeitado` (auditoria) ou soft-desvincula — preferência: manter lançamento `rejeitado` ligado ao depósito.
- Aprovar exige conta ativa; data do lançamento = último dia da competência (ou data da aprovação — **último dia da competência** para fechar o mês).

## Permissões

| Permissão | Quem | Uso |
|-----------|------|-----|
| `ecc.financeiro.depositos.submeter` | `lider-equipe` (+ admins) | CRUD de submissão da própria equipe |
| `ecc.financeiro.manage` | `admin-igreja`, `admin-tenant`, … | Aprovar / rejeitar; ver todos os depósitos |
| `ecc.financeiro.view` | gestores do livro | Ver depósitos (leitura) na tela Financeiro |

## Critérios de aceite (testáveis)

- [x] Migration tenant: tabela `ecc_financeiro_depositos` + coluna `status` em `ecc_financeiro_lancamentos` (default `confirmado`).
- [x] Seed: permissão `ecc.financeiro.depositos.submeter` no papel `lider-equipe` (e sync admins).
- [x] `POST /ecc/financeiro/depositos` (multipart: `equipe_id`, `competencia`, `valor`, `conta_id?`, `comprovante`) → 201; cria depósito + lançamento `provisionado`; 403 se líder de outra equipe; 422 se já existe não-rejeitado na competência.
- [x] `GET /ecc/financeiro/depositos` — líder: só suas equipes; gestor: todos (filtros `status`, `competencia`, `equipe_id`).
- [x] `GET /ecc/financeiro/depositos/{id}/comprovante` — stream do arquivo (auth + escopo).
- [x] `PUT /ecc/financeiro/depositos/{id}` — só `provisionado`; líder da equipe ou manage.
- [x] `POST /ecc/financeiro/depositos/{id}/aprovar` — `manage` → `confirmado`; 422 se não provisionado.
- [x] `POST /ecc/financeiro/depositos/{id}/rejeitar` — body `motivo`; `manage` → `rejeitado`.
- [x] Livro anual: totais **ignoram** `provisionado` e `rejeitado`.
- [x] Front líder: formulário + lista em `/ecc/minha-equipe`.
- [x] Front líder: comprovante por **arquivo** (PDF/JPG/PNG) ou **tirar foto** na hora (`capture=environment`).
- [x] Front gestor: fila “Depósitos pendentes” em `/ecc/financeiro` com aprovar/rejeitar + ver comprovante.
- [x] Testes API (401/403/422/sucesso/isolamento) + testes do util de depósitos no front.

## Fora de escopo

- Contribuição por casal / valor fixo configurável.
- PIX / integração bancária / conciliação automática.
- Módulo Financeiro geral da igreja (launcher).
- Notificações push/e-mail de pendência (pode entrar depois).

## Contrato

`api/docs/specs/openapi.yaml` — paths `/ecc/financeiro/depositos*`.

## Notas

- Atualiza o “fora de escopo” da SPEC-016 apenas como referência cruzada (contribuição mensal tipada fica coberta aqui como agregado de equipe).
- Decisão BRIEF #2 (contribuição mensal): nesta fatia = valor livre, agregado, só perseverança.
