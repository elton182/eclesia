/**
 * Helpers de Escalas (agenda lista/calendário).
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

/**
 * Equipes da ocorrência em que a pessoa/casal já está (informativo).
 * @param {Array<{ escala_equipe_id: string, pessoa_id?: string|null, casal_id?: string|null, equipe_nome?: string }>} atribuicoes
 * @param {{ pessoaId?: string|null, casalId?: string|null }} who
 * @returns {string[]}
 */
export function equipesJaEscalado(atribuicoes, who) {
  const out = []
  for (const a of atribuicoes || []) {
    if (who.pessoaId && a.pessoa_id === who.pessoaId) {
      out.push(a.equipe_nome || a.escala_equipe_id)
    }
    if (who.casalId && a.casal_id === who.casalId) {
      out.push(a.equipe_nome || a.escala_equipe_id)
    }
  }
  return out
}
