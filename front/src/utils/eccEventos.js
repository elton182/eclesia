/**
 * Helpers de Eventos (lista / calendário / compras).
 */

/**
 * @param {Array<{ id?: string, codigo?: string, nome?: string, abrev?: string, cor?: string }>} tipos
 * @param {string} tipoOrId codigo ou id
 */
export function resolveTipo(tipos, tipoOrId) {
  const list = tipos || []
  return (
    list.find((t) => t.id === tipoOrId || t.codigo === tipoOrId) ||
    null
  )
}

/**
 * @param {Array} tipos
 * @param {string} tipoOrId
 * @param {string} [fallback]
 */
export function tipoLabel(tipos, tipoOrId, fallback = '') {
  return resolveTipo(tipos, tipoOrId)?.nome || fallback || tipoOrId || ''
}

/**
 * @param {Array} tipos
 * @param {string} tipoOrId
 */
export function tipoAbrev(tipos, tipoOrId) {
  const t = resolveTipo(tipos, tipoOrId)
  return t?.abrev || String(tipoOrId || '').slice(0, 4)
}

/**
 * @param {Array} tipos
 * @param {string} tipoOrId
 */
export function tipoCor(tipos, tipoOrId) {
  return resolveTipo(tipos, tipoOrId)?.cor || '#6B1C2B'
}

/**
 * @param {Array<{ tipo?: string, evento_tipo_id?: string, titulo?: string, local?: string }>} eventos
 * @param {{ tipo?: string, tipoId?: string, search?: string, tipos?: Array }} opts
 */
export function filterEventos(eventos, { tipo = '', tipoId = '', search = '', tipos = [] } = {}) {
  const q = String(search || '').trim().toLowerCase()
  return (eventos || []).filter((e) => {
    if (tipoId && e.evento_tipo_id !== tipoId) return false
    if (tipo && e.tipo !== tipo && e.evento_tipo_id !== tipo) return false
    if (!q) return true
    const hay = [e.titulo, e.local, tipoLabel(tipos, e.evento_tipo_id || e.tipo)]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return hay.includes(q)
  })
}

/**
 * @param {Array<{ inicia_em?: string }>} eventos
 * @returns {Array<{ chave: string, label: string, itens: typeof eventos }>}
 */
export function groupEventosByMonth(eventos) {
  const map = new Map()
  for (const e of eventos || []) {
    if (!e.inicia_em) continue
    const d = new Date(e.inicia_em)
    if (Number.isNaN(d.getTime())) continue
    const chave = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    if (!map.has(chave)) {
      const label = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
      map.set(chave, { chave, label, itens: [] })
    }
    map.get(chave).itens.push(e)
  }
  return [...map.values()].sort((a, b) => a.chave.localeCompare(b.chave))
}

/**
 * @param {string} status
 */
export function statusCompraLabel(status) {
  return (
    {
      pendente: 'Pendente',
      doado: 'Item doado',
      comprado: 'Comprado',
    }[status] || status
  )
}

/**
 * Busca casais por nome (ele/ela/nome).
 * @param {Array} casais
 * @param {string} search
 */
export function filterCasaisBusca(casais, search) {
  const q = String(search || '').trim().toLowerCase()
  if (!q) return casais || []
  return (casais || []).filter((c) => {
    const hay = [c.nome, c.nome_conjuge, c.ele?.nome, c.ela?.nome, c.equipe_nome]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return hay.includes(q)
  })
}

/**
 * Confirmação de exclusão: o usuário deve digitar "deletar".
 * @param {string} texto
 */
export function canConfirmDeleteEvento(texto) {
  return String(texto || '').trim().toLowerCase() === 'deletar'
}

/** Tipos de relatório imprimível do evento */
export const RELATORIO_TIPOS = [
  {
    id: 'completo',
    label: 'Relatório completo',
    descricao: 'Participantes, compras e extrato',
    secoes: ['participantes', 'compras', 'extrato'],
  },
  {
    id: 'participantes',
    label: 'Participantes e convidados',
    descricao: 'Lista de casais e nº de convidados',
    secoes: ['participantes'],
  },
  {
    id: 'compras',
    label: 'Lista de compras',
    descricao: 'Itens, quantidades e status',
    secoes: ['compras'],
  },
  {
    id: 'extrato',
    label: 'Extrato da conta corrente',
    descricao: 'Saldo, doações e saídas',
    secoes: ['extrato'],
  },
]

/**
 * @param {string} value
 */
function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/**
 * @param {number|string|null|undefined} v
 */
function formatMoneyBr(v) {
  return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/**
 * @param {string|string[]} tipoOuSecoes
 * @returns {Set<string>}
 */
export function resolveRelatorioSecoes(tipoOuSecoes = 'completo') {
  if (Array.isArray(tipoOuSecoes)) {
    return new Set(tipoOuSecoes.filter(Boolean))
  }
  const tipo = RELATORIO_TIPOS.find((t) => t.id === tipoOuSecoes) || RELATORIO_TIPOS[0]
  return new Set(tipo.secoes)
}

/**
 * HTML imprimível do relatório do evento (layout elaborado).
 *
 * @param {{
 *   evento: object,
 *   caixa?: object|null,
 *   tipoNome?: string,
 *   tipoRelatorio?: string,
 *   secoes?: string[],
 *   geradoEm?: Date|string,
 * }} opts
 */
export function buildEventoRelatorioHtml({
  evento,
  caixa = null,
  tipoNome = '',
  tipoRelatorio = 'completo',
  secoes,
  geradoEm = new Date(),
} = {}) {
  const e = evento || {}
  const ativas = secoes ? new Set(secoes) : resolveRelatorioSecoes(tipoRelatorio)
  const tipoMeta = RELATORIO_TIPOS.find((t) => t.id === tipoRelatorio)
  const tituloRelatorio = tipoMeta?.label || 'Relatório'
  const titulo = escapeHtml(e.titulo || 'Evento')
  const tipo = escapeHtml(tipoNome || e.tipo || '')
  const quando = e.inicia_em
    ? escapeHtml(new Date(e.inicia_em).toLocaleString('pt-BR', { dateStyle: 'long', timeStyle: 'short' }))
    : ''
  const local = escapeHtml(e.local || '')
  const participantes = e.participantes || []
  const itens = e.itens_compra || []
  const extrato = caixa?.extrato || []
  const totalConvidados = Number(e.convidados_total || participantes.reduce((s, p) => s + Number(p.convidados || 0), 0))
  const geradoLabel = escapeHtml(
    new Date(geradoEm).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }),
  )

  const showPart = ativas.has('participantes')
  const showCompras = ativas.has('compras') && !!e.permite_compras
  const showExtrato = ativas.has('extrato') && caixa != null

  const chips = []
  if (showPart) {
    chips.push(`${participantes.length} casais`)
    chips.push(`${totalConvidados} convidados`)
  }
  if (showCompras) chips.push(`${itens.length} itens`)
  if (showExtrato) chips.push(`Saldo ${formatMoneyBr(caixa.saldo)}`)

  const rowsPart = participantes.length
    ? participantes
        .map((p, i) => {
          const n = Number(p.convidados || 0)
          return `<tr>
            <td class="num">${i + 1}</td>
            <td><strong>${escapeHtml(p.casal_rotulo || '')}</strong></td>
            <td class="center">${n}</td>
          </tr>`
        })
        .join('')
    : '<tr><td colspan="3" class="empty">Nenhum participante cadastrado</td></tr>'

  const rowsItens = itens.length
    ? itens
        .map((it, i) => {
          const qtd = [it.qtd, it.unidade].filter(Boolean).join(' ')
          const status = statusCompraLabel(it.status)
          const badgeClass =
            it.status === 'doado' ? 'badge-ok' : it.status === 'comprado' ? 'badge-warn' : 'badge-muted'
          let detalhe = '—'
          if (it.status === 'doado' && it.doador_rotulo) detalhe = escapeHtml(it.doador_rotulo)
          else if (it.status === 'comprado' && it.valor_gasto != null) detalhe = escapeHtml(formatMoneyBr(it.valor_gasto))
          return `<tr>
            <td class="num">${i + 1}</td>
            <td><strong>${escapeHtml(it.nome || '')}</strong></td>
            <td>${escapeHtml(qtd)}</td>
            <td><span class="badge ${badgeClass}">${escapeHtml(status)}</span></td>
            <td>${detalhe}</td>
          </tr>`
        })
        .join('')
    : '<tr><td colspan="5" class="empty">Nenhum item na lista</td></tr>'

  const rowsExtrato = extrato.length
    ? extrato
        .map((m) => {
          const entrada = m.tipo === 'entrada'
          const sinal = entrada ? '+' : '−'
          const doador = m.doador?.rotulo ? escapeHtml(m.doador.rotulo) : '—'
          const data = m.created_at
            ? escapeHtml(new Date(m.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }))
            : '—'
          return `<tr>
            <td><span class="pill ${entrada ? 'pill-in' : 'pill-out'}">${entrada ? 'Entrada' : 'Saída'}</span></td>
            <td>
              <div class="cell-title">${escapeHtml(m.descricao || '')}</div>
              <div class="cell-sub">${doador}</div>
            </td>
            <td class="muted">${data}</td>
            <td class="money ${entrada ? 'pos' : 'neg'}">${sinal} ${escapeHtml(formatMoneyBr(m.valor))}</td>
          </tr>`
        })
        .join('')
    : '<tr><td colspan="4" class="empty">Sem lançamentos no caixa</td></tr>'

  const pessoasEst = participantes.length * 2 + totalConvidados

  const secaoParticipantes = showPart
    ? `
  <section class="section">
    <div class="section-head">
      <h2>Participantes e convidados</h2>
      <span class="section-tag">${participantes.length} casais · ${totalConvidados} convidados</span>
    </div>
    <div class="kpi-row">
      <div class="kpi"><div class="kpi-val">${participantes.length}</div><div class="kpi-lbl">Casais</div></div>
      <div class="kpi"><div class="kpi-val">${totalConvidados}</div><div class="kpi-lbl">Convidados</div></div>
      <div class="kpi accent"><div class="kpi-val">${pessoasEst}</div><div class="kpi-lbl">Pessoas (est.)</div></div>
    </div>
    <p class="footnote">Estimativa: 2 pessoas por casal + convidados informados.</p>
    <table>
      <thead><tr><th class="num">#</th><th>Casal</th><th class="center">Convidados</th></tr></thead>
      <tbody>${rowsPart}</tbody>
      <tfoot>
        <tr>
          <td colspan="2"><strong>Total de convidados</strong></td>
          <td class="center"><strong>${totalConvidados}</strong></td>
        </tr>
      </tfoot>
    </table>
  </section>`
    : ''

  const pendentes = itens.filter((i) => i.status === 'pendente').length
  const doados = itens.filter((i) => i.status === 'doado').length
  const comprados = itens.filter((i) => i.status === 'comprado').length

  const secaoCompras = showCompras
    ? `
  <section class="section">
    <div class="section-head">
      <h2>Lista de compras</h2>
      <span class="section-tag">${itens.length} itens</span>
    </div>
    <div class="kpi-row">
      <div class="kpi"><div class="kpi-val">${pendentes}</div><div class="kpi-lbl">Pendentes</div></div>
      <div class="kpi"><div class="kpi-val">${doados}</div><div class="kpi-lbl">Doados</div></div>
      <div class="kpi"><div class="kpi-val">${comprados}</div><div class="kpi-lbl">Comprados</div></div>
    </div>
    <table>
      <thead>
        <tr>
          <th class="num">#</th>
          <th>Item</th>
          <th>Qtd</th>
          <th>Status</th>
          <th>Doador / valor</th>
        </tr>
      </thead>
      <tbody>${rowsItens}</tbody>
    </table>
  </section>`
    : ''

  const secaoExtrato = showExtrato
    ? `
  <section class="section">
    <div class="section-head">
      <h2>Extrato da conta corrente</h2>
      <span class="section-tag">${extrato.length} lançamentos</span>
    </div>
    <div class="kpi-row">
      <div class="kpi accent"><div class="kpi-val">${escapeHtml(formatMoneyBr(caixa.saldo))}</div><div class="kpi-lbl">Saldo</div></div>
      <div class="kpi pos-box"><div class="kpi-val">${escapeHtml(formatMoneyBr(caixa.total_entradas))}</div><div class="kpi-lbl">Entradas · ${caixa.qtd_doacoes ?? extrato.filter((m) => m.tipo === 'entrada').length}</div></div>
      <div class="kpi neg-box"><div class="kpi-val">${escapeHtml(formatMoneyBr(caixa.total_saidas))}</div><div class="kpi-lbl">Saídas · ${caixa.qtd_compras_caixa ?? extrato.filter((m) => m.tipo === 'saida').length}</div></div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Descrição</th>
          <th>Data</th>
          <th class="money">Valor</th>
        </tr>
      </thead>
      <tbody>${rowsExtrato}</tbody>
    </table>
  </section>`
    : ''

  const chipsHtml = chips.map((c) => `<span class="chip">${escapeHtml(c)}</span>`).join('')

  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(tituloRelatorio)} — ${titulo}</title>
  <style>
    :root {
      --ink: #2A1418;
      --muted: #6B4A50;
      --line: #E8DFD6;
      --paper: #FFFDFA;
      --brand: #6B1C2B;
      --sand: #F6EDE4;
      --ok: #2A6B4A;
      --ok-bg: #E4F0EA;
      --bad: #8A2436;
      --bad-bg: #F3E1E1;
      --warn: #B4703F;
    }
    * { box-sizing: border-box; }
    body {
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
      color: var(--ink);
      background: #fff;
      margin: 0;
      padding: 0;
      font-size: 12.5px;
      line-height: 1.45;
    }
    .sheet { max-width: 900px; margin: 0 auto; padding: 28px 32px 40px; }
    .masthead {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      padding-bottom: 18px;
      border-bottom: 3px solid var(--brand);
      margin-bottom: 18px;
    }
    .brand-mark {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--brand);
      margin: 0 0 6px;
    }
    .report-kind {
      display: inline-block;
      background: var(--sand);
      color: var(--warn);
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: 4px 10px;
      border-radius: 999px;
      margin-bottom: 8px;
    }
    h1 {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 26px;
      font-weight: 600;
      margin: 0 0 6px;
      line-height: 1.2;
    }
    .meta { color: var(--muted); margin: 0; font-size: 12px; }
    .mast-right { text-align: right; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; margin-top: 10px; }
    .chip {
      background: var(--sand);
      color: var(--ink);
      font-size: 11px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 999px;
    }
    .section { margin-top: 28px; page-break-inside: avoid; }
    .section-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      border-bottom: 1px solid var(--line);
      padding-bottom: 6px;
      margin-bottom: 12px;
    }
    .section-head h2 {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 16px;
      margin: 0;
      font-weight: 600;
    }
    .section-tag { color: var(--muted); font-size: 11px; font-weight: 600; }
    .kpi-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 14px;
    }
    .kpi {
      background: var(--sand);
      border-radius: 10px;
      padding: 12px 14px;
    }
    .kpi.accent { background: #F5E9DA; }
    .kpi.pos-box { background: var(--ok-bg); }
    .kpi.neg-box { background: var(--bad-bg); }
    .kpi-val { font-size: 18px; font-weight: 700; color: var(--brand); }
    .pos-box .kpi-val { color: var(--ok); }
    .neg-box .kpi-val { color: var(--bad); }
    .kpi-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-top: 2px; font-weight: 600; }
    .footnote { font-size: 10px; color: var(--muted); margin: -6px 0 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px 6px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--line); }
    th {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--muted);
      font-weight: 700;
      background: #FAF7F3;
    }
    tfoot td { border-bottom: none; background: var(--sand); font-size: 12px; }
    td.num, th.num { width: 36px; color: var(--muted); }
    td.center, th.center { text-align: center; }
    td.money, th.money { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    td.pos { color: var(--ok); font-weight: 700; }
    td.neg { color: var(--bad); font-weight: 700; }
    td.muted { color: var(--muted); white-space: nowrap; }
    td.empty { color: var(--muted); font-style: italic; text-align: center; padding: 18px; }
    .cell-title { font-weight: 600; }
    .cell-sub { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .badge {
      display: inline-block;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
    }
    .badge-muted { background: #EEE8E2; color: var(--muted); }
    .badge-ok { background: var(--ok-bg); color: var(--ok); }
    .badge-warn { background: #F8E8D8; color: var(--warn); }
    .pill {
      display: inline-block;
      font-size: 10px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .pill-in { background: var(--ok-bg); color: var(--ok); }
    .pill-out { background: var(--bad-bg); color: var(--bad); }
    .footer {
      margin-top: 36px;
      padding-top: 12px;
      border-top: 1px solid var(--line);
      display: flex;
      justify-content: space-between;
      color: var(--muted);
      font-size: 10px;
    }
    @media print {
      body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
      .sheet { padding: 0; max-width: none; }
      .section { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="sheet">
    <header class="masthead">
      <div>
        <p class="brand-mark">Eclesia · Eventos</p>
        <span class="report-kind">${escapeHtml(tituloRelatorio)}</span>
        <h1>${titulo}</h1>
        <p class="meta">${tipo}${quando ? ` · ${quando}` : ''}${local ? ` · ${local}` : ''}</p>
      </div>
      <div class="mast-right">
        <p class="meta">Gerado em ${geradoLabel}</p>
        <div class="chips">${chipsHtml}</div>
      </div>
    </header>
    ${secaoParticipantes}
    ${secaoCompras}
    ${secaoExtrato}
    <footer class="footer">
      <span>Documento gerado pelo Eclesia</span>
      <span>${escapeHtml(tituloRelatorio)}</span>
    </footer>
  </div>
</body>
</html>`
}

/** Compat: catálogo legado se API ainda não carregou */
export const TIPOS_EVENTO = [
  { id: 'encontro', codigo: 'encontro', label: 'Encontro', nome: 'Encontro', abrev: 'Enc', cor: '#6B1C2B' },
  { id: 'anual', codigo: 'anual', label: 'Jornada anual', nome: 'Jornada anual', abrev: 'Anual', cor: '#C88A5E' },
  { id: 'servos', codigo: 'servos', label: 'Servos', nome: 'Servos', abrev: 'Serv', cor: '#3a5f86' },
  { id: 'perseveranca', codigo: 'perseveranca', label: 'Perseverança', nome: 'Perseverança', abrev: 'Pers', cor: '#5a7a4a' },
  { id: 'formacao', codigo: 'formacao', label: 'Formação', nome: 'Formação', abrev: 'Form', cor: '#8a6414' },
]
