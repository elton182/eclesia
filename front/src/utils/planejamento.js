/**
 * Helpers do Planejamento Anual (SPEC-016).
 */

export const PLANEJAMENTO_ANO_STATUS = Object.freeze({
  aberto: 'Aberto',
  coleta: 'Coleta',
  fechado: 'Fechado',
})

export const PLANEJAMENTO_EVENTO_STATUS = Object.freeze({
  proposta: 'Proposta',
  em_revisao: 'Em revisão',
  aprovado: 'Aprovado',
  rejeitado: 'Rejeitado',
})

/** Cores de tag por status do evento (tokens CSS / hex do design system). */
export const PLANEJAMENTO_EVENTO_STATUS_STYLE = Object.freeze({
  proposta: {
    bg: 'rgba(42,20,24,0.08)',
    fg: 'rgba(42,20,24,0.72)',
    chip: 'var(--color-muted)',
  },
  em_revisao: {
    bg: 'var(--color-accent-soft)',
    fg: 'var(--color-accent-dark)',
    chip: 'var(--color-accent-dark)',
  },
  aprovado: {
    bg: 'rgba(34,120,72,0.12)',
    fg: '#1f6b3a',
    chip: '#1f6b3a',
  },
  rejeitado: {
    bg: 'rgba(160,40,40,0.12)',
    fg: '#8a2424',
    chip: '#8a2424',
  },
})

export const PLANEJAMENTO_ANO_STATUS_STYLE = Object.freeze({
  aberto: { bg: 'rgba(42,20,24,0.08)', fg: 'rgba(42,20,24,0.72)' },
  coleta: { bg: 'var(--color-accent-soft)', fg: 'var(--color-accent-dark)' },
  fechado: { bg: 'rgba(34,120,72,0.12)', fg: '#1f6b3a' },
})

/**
 * @param {string|undefined|null} status
 * @returns {string}
 */
export function labelAnoStatus(status) {
  return PLANEJAMENTO_ANO_STATUS[status] || status || ''
}

/**
 * @param {string|undefined|null} status
 * @returns {string}
 */
export function labelEventoStatus(status) {
  return PLANEJAMENTO_EVENTO_STATUS[status] || status || ''
}

/**
 * @param {string|undefined|null} status
 */
export function styleEventoStatus(status) {
  return (
    PLANEJAMENTO_EVENTO_STATUS_STYLE[status] || PLANEJAMENTO_EVENTO_STATUS_STYLE.proposta
  )
}

/**
 * @param {Array<{ data_inicio?: string, id?: string }>} eventos
 * @returns {Map<string, typeof eventos>}
 */
export function groupEventosByDay(eventos) {
  const map = new Map()
  for (const e of eventos || []) {
    const key = toDayKeyLocal(e.data_inicio)
    if (!key) continue
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(e)
  }
  return map
}

/**
 * @param {string|Date|null|undefined} iso
 * @returns {string} YYYY-MM-DD
 */
export function toDayKeyLocal(iso) {
  if (!iso) return ''
  const d = iso instanceof Date ? iso : new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/**
 * Move evento para outro dia preservando hora (ou meia-noite se dia_inteiro).
 * @param {string} dayKey YYYY-MM-DD
 * @param {{ data_inicio?: string, dia_inteiro?: boolean }} evento
 * @returns {{ data_inicio: string, data_fim?: string|null }}
 */
export function moveEventoToDay(dayKey, evento) {
  const prev = evento.data_inicio ? new Date(evento.data_inicio) : new Date(`${dayKey}T12:00:00`)
  const hh = evento.dia_inteiro ? '00' : String(prev.getHours()).padStart(2, '0')
  const mm = evento.dia_inteiro ? '00' : String(prev.getMinutes()).padStart(2, '0')
  const data_inicio = `${dayKey}T${hh}:${mm}:00`
  let data_fim = null
  if (evento.data_fim) {
    const fim = new Date(evento.data_fim)
    const start = new Date(evento.data_inicio || data_inicio)
    const delta = fim.getTime() - start.getTime()
    data_fim = new Date(new Date(data_inicio).getTime() + Math.max(0, delta)).toISOString()
  }
  return { data_inicio: new Date(data_inicio).toISOString(), data_fim }
}

/**
 * Transições válidas de status do ano (espelha API).
 * @param {string} from
 * @returns {string[]}
 */
export function nextAnoStatuses(from) {
  if (from === 'aberto') return ['coleta']
  if (from === 'coleta') return ['fechado', 'aberto']
  if (from === 'fechado') return ['coleta', 'aberto']
  return []
}
