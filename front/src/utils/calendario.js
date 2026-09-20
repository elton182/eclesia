/**
 * Helpers do calendário oficial (SPEC-012) e agenda (lista/grade mensal).
 */

/**
 * @param {string|Date|null|undefined} iso
 * @returns {string} YYYY-MM-DD em local
 */
export function toDayKey(iso) {
  if (!iso) return ''
  const d = iso instanceof Date ? iso : new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/**
 * @param {Array<{ inicia_em?: string, id?: string }>} ocorrencias
 * @returns {Map<string, typeof ocorrencias>}
 */
export function groupOcorrenciasByDay(ocorrencias) {
  const map = new Map()
  for (const o of ocorrencias || []) {
    const key = toDayKey(o.inicia_em)
    if (!key) continue
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(o)
  }
  return map
}

/**
 * Matriz de dias do mês (domingo=0) com células null fora do mês.
 * @param {number} year
 * @param {number} monthIndex 0-11
 * @returns {Array<Array<{ day: number, key: string }|null>>}
 */
export function buildMonthGrid(year, monthIndex) {
  const first = new Date(year, monthIndex, 1)
  const startPad = first.getDay()
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()
  /** @type {Array<{ day: number, key: string }|null>} */
  const cells = []
  for (let i = 0; i < startPad; i++) cells.push(null)
  for (let d = 1; d <= daysInMonth; d++) {
    const key = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({ day: d, key })
  }
  while (cells.length % 7 !== 0) cells.push(null)
  /** @type {Array<Array<{ day: number, key: string }|null>>} */
  const weeks = []
  for (let i = 0; i < cells.length; i += 7) {
    weeks.push(cells.slice(i, i + 7))
  }
  return weeks
}

/**
 * Monta valor de input datetime-local a partir de YYYY-MM-DD.
 * @param {string} dayKey
 * @param {string} [timeHHMM]
 * @returns {string}
 */
export function toDatetimeLocal(dayKey, timeHHMM = '10:00') {
  if (!dayKey) return ''
  return `${dayKey}T${timeHHMM}`
}

export const CALENDARIO_STATUS = Object.freeze({
  rascunho: 'Rascunho',
  coleta: 'Coleta',
  montagem: 'Montagem',
  fechado: 'Fechado',
})

export const MES_NOMES = Object.freeze([
  '',
  'Janeiro',
  'Fevereiro',
  'Março',
  'Abril',
  'Maio',
  'Junho',
  'Julho',
  'Agosto',
  'Setembro',
  'Outubro',
  'Novembro',
  'Dezembro',
])

/**
 * @param {string} status
 * @returns {string}
 */
export function labelStatusCalendario(status) {
  return CALENDARIO_STATUS[status] || status
}

/**
 * @param {number} ano
 * @param {number} mes
 * @returns {string}
 */
export function labelMesCalendario(ano, mes) {
  return `${MES_NOMES[mes] || mes}/${ano}`
}

/**
 * Transições permitidas a partir do status atual.
 * @param {string} status
 * @returns {string[]}
 */
export function proximosStatusCalendario(status) {
  switch (status) {
    case 'rascunho':
      return ['coleta', 'montagem']
    case 'coleta':
      return ['montagem', 'rascunho']
    case 'montagem':
      return ['fechado', 'coleta']
    case 'fechado':
      return ['montagem']
    default:
      return []
  }
}

/**
 * Seção de grade a partir da data ISO (dom/sáb → fds).
 * @param {string} iso YYYY-MM-DD
 * @returns {'fds'|'semana'}
 */
export function secaoGradePorData(iso) {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return 'semana'
  const [y, m, d] = iso.split('-').map(Number)
  const dow = new Date(y, m - 1, d).getDay()
  return dow === 0 || dow === 6 ? 'fds' : 'semana'
}

/**
 * Agrupa itens da grade por data.
 * @param {Array<{ data?: string, secao?: string }>|null|undefined} itens
 * @param {string[]} [secoes]
 * @returns {Map<string, typeof itens>}
 */
export function agruparItensPorData(itens, secoes = ['fds', 'semana']) {
  const map = new Map()
  for (const item of itens || []) {
    if (secoes.length && !secoes.includes(item.secao)) continue
    const key = item.data || ''
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(item)
  }
  return map
}

/**
 * Indisponibilidades indexadas por data → nomes.
 * @param {Array<{ data?: string, nome_exibicao?: string }>|null|undefined} list
 * @returns {Map<string, string[]>}
 */
export function indexarIndisponibilidades(list) {
  const map = new Map()
  for (const row of list || []) {
    const key = row.data || ''
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(row.nome_exibicao || '—')
  }
  return map
}

/**
 * Células da grade mensal (Dom–Sáb). Fora do mês → iso null.
 * @param {number} ano
 * @param {number} mes 1–12
 * @returns {Array<{ iso: string|null, dia: number|null, inMonth: boolean }>}
 */
export function gradeMesCalendario(ano, mes) {
  const y = Number(ano)
  const m = Number(mes)
  if (!y || m < 1 || m > 12) return []

  const firstWeekday = new Date(y, m - 1, 1).getDay()
  const lastDay = new Date(y, m, 0).getDate()
  /** @type {Array<{ iso: string|null, dia: number|null, inMonth: boolean }>} */
  const cells = []

  for (let i = 0; i < firstWeekday; i++) {
    cells.push({ iso: null, dia: null, inMonth: false })
  }

  for (let d = 1; d <= lastDay; d++) {
    const iso = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({ iso, dia: d, inMonth: true })
  }

  while (cells.length % 7 !== 0) {
    cells.push({ iso: null, dia: null, inMonth: false })
  }

  return cells
}

/**
 * Alterna ISO na lista ordenada de datas selecionadas.
 * @param {string[]} datas
 * @param {string} iso
 * @returns {string[]}
 */
export function toggleDataSelecionada(datas, iso) {
  if (!iso) return [...(datas || [])]
  const set = new Set(datas || [])
  if (set.has(iso)) set.delete(iso)
  else set.add(iso)
  return [...set].sort()
}

/**
 * Formata YYYY-MM-DD → "15 set".
 * @param {string} iso
 * @returns {string}
 */
export function labelDiaCurto(iso) {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || ''
  const [, mm, dd] = iso.split('-')
  const mes = MES_NOMES[Number(mm)] || mm
  const curto = mes.length > 3 ? mes.slice(0, 3).toLowerCase() : mes.toLowerCase()
  return `${Number(dd)} ${curto}`
}

/**
 * Formata hora "19:30:00" | "19:30" → "19:30".
 * @param {string|null|undefined} hora
 * @returns {string}
 */
export function formatHoraCalendario(hora) {
  if (!hora) return ''
  const m = String(hora).match(/^(\d{1,2}):(\d{2})/)
  if (!m) return String(hora)
  return `${m[1].padStart(2, '0')}:${m[2]}`
}

/**
 * Linha de preview de um item: "19:00 · Missa · Pe. João".
 * @param {{
 *   hora?: string|null,
 *   celebrante_nome?: string|null,
 *   titulo?: string|null,
 *   local?: { nome?: string }|null,
 *   tipo?: { nome?: string }|null,
 * }} item
 * @returns {string}
 */
export function labelItemPreview(item) {
  if (!item) return ''
  const hora = formatHoraCalendario(item.hora)
  const tipo =
    (item.tipo?.nome && String(item.tipo.nome).trim()) ||
    (item.titulo && String(item.titulo).trim()) ||
    ''
  const nome =
    (item.celebrante_nome && String(item.celebrante_nome).trim()) ||
    (item.local?.nome && String(item.local.nome).trim()) ||
    ''
  const parts = [hora, tipo, nome].filter(Boolean)
  return parts.join(' · ')
}

/**
 * Até `max` linhas de preview para a célula do dia, ordenadas por hora.
 * @param {Array|null|undefined} itens
 * @param {number} [max=2]
 * @returns {string[]}
 */
export function previewLinhasDia(itens, max = 2) {
  const sorted = [...(itens || [])].sort((a, b) =>
    String(a.hora || '').localeCompare(String(b.hora || '')),
  )
  const lines = []
  for (const item of sorted) {
    const line = labelItemPreview(item)
    if (line) lines.push(line)
    if (lines.length >= max) break
  }
  return lines
}

/**
 * Próximo mês civil a partir de ano/mês.
 * @param {number} ano
 * @param {number} mes 1–12
 * @returns {{ ano: number, mes: number }}
 */
export function proximoMesCalendario(ano, mes) {
  if (mes >= 12) return { ano: ano + 1, mes: 1 }
  return { ano, mes: mes + 1 }
}

/**
 * Número 1-based da observação na lista ordenada (como no PDF).
 * @param {Array<{ id?: string, ordem?: number }>|null|undefined} observacoes
 * @param {string|null|undefined} observacaoId
 * @returns {number|null}
 */
export function numeroObservacao(observacoes, observacaoId) {
  if (!observacaoId || !Array.isArray(observacoes) || !observacoes.length) return null
  const ordered = [...observacoes].sort((a, b) => (a.ordem ?? 0) - (b.ordem ?? 0))
  const idx = ordered.findIndex((o) => o.id === observacaoId)
  return idx >= 0 ? idx + 1 : null
}

/**
 * Label de opção no select de observação.
 * @param {{ numero?: number, titulo?: string }} obs
 * @param {number} [fallbackNumero]
 * @returns {string}
 */
export function labelObservacaoOpcao(obs, fallbackNumero) {
  const n = obs?.numero ?? fallbackNumero
  const titulo = (obs?.titulo && String(obs.titulo).trim()) || 'Observação'
  return n ? `${n}) ${titulo}` : titulo
}

/**
 * Resumo de um dia para a grade de montagem.
 * @param {string} iso
 * @param {Map<string, Array>} itensPorData
 * @param {Map<string, string[]>} indisponiveisPorData
 * @returns {{ iso: string, itens: Array, indisponiveis: string[], temGrade: boolean, temIndisponivel: boolean }}
 */
export function resumoDiaMontagem(iso, itensPorData, indisponiveisPorData) {
  const itens = itensPorData?.get(iso) || []
  const indisponiveis = indisponiveisPorData?.get(iso) || []
  return {
    iso,
    itens,
    indisponiveis,
    temGrade: itens.length > 0,
    temIndisponivel: indisponiveis.length > 0,
  }
}
