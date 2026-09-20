# SPEC-012 — Calendário oficial mensal da paróquia

**Status:** approved  
**Data:** 2026-09-18  
**Atualizado:** 2026-09-20  
**Plano:** [PLAN-003](../plans/PLAN-003-calendario-oficial-sessao-branding.md)  
**ADR:** [ADR-0006](../architecture/ADR-0006-calendario-locais-celebracao.md)

## Contexto

O padre monta mensalmente o calendário litúrgico oficial (missas, celebrações, festas, casamentos) com celebrantes (padres, diáconos, ministros). Na última semana do mês abre o próximo mês em rascunho, coleta indisponibilidades e fecha gerando PDF no formato da grade oficial.

## Objetivo

1. Catálogo de locais de celebração e slots padrão por igreja.
2. Calendário mensal com status: `rascunho` → `coleta` → `montagem` → `fechado`.
3. Itens da grade (fds/semana/festa/casamento/obs_movel) com celebrante e notas.
4. Coleta de indisponibilidades via usuários convidados ou link público (token).
5. Exportação PDF do mês fechado (ou em montagem).
6. Observações fixas estruturadas (N por mês: título + descrição), referenciáveis na célula.
7. Tempo litúrgico por domingo do mês (rótulo manual na grade de FDS).

## Critérios de aceite (testáveis)

- [ ] CRUD `calendario/locais` e `calendario/slots-padrao` scoped por `igreja_id`.
- [ ] CRUD `calendario/mensais` (ano+mês únicos por igreja); copiar slots/obs do mês anterior opcional.
- [ ] Transições de status válidas; `fechado` impede edição de itens.
- [ ] CRUD itens por seção; celebrante via `pessoa_id` e/ou `celebrante_nome`.
- [ ] Abrir coleta: gerar link tokenizado; endpoint público registra indisponibilidades sem auth.
- [ ] Padre lista indisponibilidades ao montar.
- [ ] `GET .../mensais/{id}/pdf` retorna `application/pdf` com cabeçalho do mês.
- [ ] Permissões: `calendario.gerir`, `calendario.colaborar`; seed nos papéis adequados.
- [ ] Isolamento tenant/igreja; ULID nas URLs.
- [ ] CRUD observações do mês (`titulo`, `descricao`, `ordem`); item pode ter `observacao_id`; PDF numera `(N)` pela ordem.
- [ ] Tempos litúrgicos gerados por domingo ao criar o mês; edição do `rotulo`; PDF imprime linha “Tempo Litúrgico” na grade FDS.
- [ ] Excluir calendário mensal (`DELETE .../mensais/{id}`).
- [ ] Copiar calendário para o próximo mês (`POST .../mensais/{id}/copiar-proximo`): cria rascunho do mês seguinte com observações e celebrantes da grade (por dia da semana + semana do mês); falha se o mês destino já existir.

## Fora de escopo

- Publicação automática em `evento_agenda`.
- Calendários de pastorais.
- App nativo dedicado.
- Cálculo automático do rótulo litúrgico (orbe / tempo comum etc.).

## Contrato de API

OpenAPI: paths `/calendario/*` e `/public/calendario/coleta/{token}`.

## Modelo (observações e tempo litúrgico)

```
CalendarioMensal
  ├─ itens[]
  │     observacao_id?  → CalendarioObservacao
  ├─ observacoes[]      (titulo, descricao, ordem)
  └─ tempos_liturgicos[] (data_domingo, rotulo)
```

- **Observações fixas:** entidade `calendario_observacoes`; no PDF, lista numerada; na célula, `(N)` derivado da `ordem` (1-based) da observação ligada.
- **Tempo litúrgico:** metadado do domingo (sábado da mesma semana herda o rótulo na renderização); preenchimento manual.
- **Observações móveis:** permanecem como itens `obs_movel` (datados).

## Notas

- Locais ≠ Igrejas (ADR-0006).
- Campo legado `observacoes_fixas` (texto livre) foi substituído por `calendario_observacoes`.
