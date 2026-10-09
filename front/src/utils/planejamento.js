/**
 * Helpers do calendário anual de planejamento (SPEC-018).
 * Independente do calendário oficial (SPEC-012).
 */

export const PLANEJAMENTO_STATUS = Object.freeze({
  rascunho: 'Rascunho',
  coleta: 'Coleta',
  revisao: 'Revisão',
  fechado: 'Fechado',
})

export const STATUS_SOLICITACAO = Object.freeze({
  proposta: 'Proposta',
  confirmado: 'Confirmado',
  ajuste_solicitado: 'Ajuste solicitado',
  reagendado: 'Reagendado',
  recusado: 'Recusado',
  cotidiano: 'Cotidiano',
})

export const PAPEL_MEMBRO = Object.freeze({
  coordenador: 'Coordenador',
  membro: 'Membro',
})

/**
 * @param {string} status
 * @returns {string}
 */
export function labelStatusPlanejamento(status) {
  return PLANEJAMENTO_STATUS[status] || status || ''
}

/**
 * @param {string} status
 * @returns {string}
 */
export function labelStatusSolicitacao(status) {
  return STATUS_SOLICITACAO[status] || status || ''
}

/**
 * @param {string} papel
 * @returns {string}
 */
export function labelPapelMembro(papel) {
  return PAPEL_MEMBRO[papel] || papel || ''
}

/**
 * Transições permitidas a partir do status do ano (gestor).
 * @param {string} status
 * @returns {string[]}
 */
export function proximosStatusPlanejamento(status) {
  switch (status) {
    case 'rascunho':
      return ['coleta']
    case 'coleta':
      return ['revisao', 'rascunho']
    case 'revisao':
      return ['fechado', 'coleta']
    case 'fechado':
      return ['revisao']
    default:
      return []
  }
}

/**
 * Agrupa eventos por data_inicio (YYYY-MM-DD).
 * @param {Array<{ data_inicio?: string }>|null|undefined} eventos
 * @returns {Map<string, typeof eventos>}
 */
export function groupEventosByDay(eventos) {
  const map = new Map()
  for (const ev of eventos || []) {
    const key = (ev.data_inicio || '').slice(0, 10)
    if (!key) continue
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(ev)
  }
  return map
}

/**
 * Filtra eventos por mês (1–12).
 * @param {Array<{ data_inicio?: string }>|null|undefined} eventos
 * @param {number} mes
 * @returns {typeof eventos}
 */
export function filterEventosPorMes(eventos, mes) {
  const m = Number(mes)
  if (!m || m < 1 || m > 12) return [...(eventos || [])]
  const pad = String(m).padStart(2, '0')
  return (eventos || []).filter((ev) => {
    const d = (ev.data_inicio || '').slice(0, 10)
    return d.length >= 7 && d.slice(5, 7) === pad
  })
}

/**
 * Coordenador pode propor novo evento neste status do ano?
 * @param {string} anoStatus
 * @param {{ canGerir?: boolean, canPropor?: boolean }} perms
 * @returns {boolean}
 */
export function canCriarEventoPlanejamento(anoStatus, perms = {}) {
  if (perms.canGerir) {
    return anoStatus !== 'fechado'
  }
  if (!perms.canPropor) return false
  return anoStatus === 'coleta'
}

/**
 * Pode editar/excluir este evento?
 * @param {string} anoStatus
 * @param {{ status_solicitacao?: string }|null|undefined} evento
 * @param {{ canGerir?: boolean, canPropor?: boolean, pastoralIds?: string[] }} perms
 * @returns {boolean}
 */
export function canEditarEventoPlanejamento(anoStatus, evento, perms = {}) {
  if (perms.canGerir) return anoStatus !== 'fechado'
  if (anoStatus === 'fechado') return false
  if (!perms.canPropor || !evento) return false

  const pastoralIds = perms.pastoralIds || []
  if (evento.pastoral_id && pastoralIds.length && !pastoralIds.includes(evento.pastoral_id)) {
    return false
  }

  if (anoStatus === 'coleta') return true
  if (anoStatus === 'revisao') return evento.status_solicitacao === 'ajuste_solicitado'
  return false
}

/**
 * Gestor pode alterar status_solicitacao.
 * @param {string} anoStatus
 * @param {{ canGerir?: boolean }} perms
 * @returns {boolean}
 */
export function canAlterarStatusSolicitacao(anoStatus, perms = {}) {
  if (!perms.canGerir) return false
  return anoStatus === 'coleta' || anoStatus === 'revisao' || anoStatus === 'rascunho'
}

/**
 * Monta mensagem de alerta a partir de conflitos[] da API.
 * @param {Array<{ titulo?: string, data_inicio?: string, pastoral?: { nome?: string }, pastoral_nome?: string }>|null|undefined} conflitos
 * @returns {string}
 */
export function formatConflitosAlert(conflitos) {
  const list = conflitos || []
  if (!list.length) return ''
  const parts = list.slice(0, 3).map((c) => {
    const titulo = (c.titulo && String(c.titulo).trim()) || 'Evento'
    const pastoral = c.pastoral?.nome || c.pastoral_nome || ''
    const data = (c.data_inicio || '').slice(0, 10)
    return [titulo, pastoral, data].filter(Boolean).join(' · ')
  })
  const extra = list.length > 3 ? ` (+${list.length - 3})` : ''
  return `Conflito de local/horário com: ${parts.join('; ')}${extra}. A gravação foi mantida.`
}

/**
 * Preview curto de um evento na célula do calendário.
 * @param {{
 *   hora_inicio?: string|null,
 *   titulo?: string|null,
 *   pastoral?: { nome?: string }|null,
 *   pastoral_nome?: string|null,
 * }} evento
 * @returns {string}
 */
export function labelEventoPlanejamentoPreview(evento) {
  if (!evento) return ''
  const hora = formatHoraCurta(evento.hora_inicio)
  const titulo = (evento.titulo && String(evento.titulo).trim()) || ''
  const pastoral =
    (evento.pastoral?.nome && String(evento.pastoral.nome).trim()) ||
    (evento.pastoral_nome && String(evento.pastoral_nome).trim()) ||
    ''
  return [hora, titulo || pastoral].filter(Boolean).join(' · ')
}

/**
 * @param {string|null|undefined} hora
 * @returns {string}
 */
export function formatHoraCurta(hora) {
  if (!hora) return ''
  const m = String(hora).match(/^(\d{1,2}):(\d{2})/)
  if (!m) return String(hora)
  return `${m[1].padStart(2, '0')}:${m[2]}`
}

/**
 * Filtra lista de usuários por nome/e-mail.
 * @param {Array<{ name?: string, email?: string }>|null|undefined} users
 * @param {string} query
 * @returns {typeof users}
 */
export function filterUsersBySearch(users, query) {
  const q = (query || '').trim().toLowerCase()
  if (!q) return [...(users || [])]
  return (users || []).filter((u) => {
    const name = (u.name || '').toLowerCase()
    const email = (u.email || '').toLowerCase()
    return name.includes(q) || email.includes(q)
  })
}

/**
 * Extrai array `data` de respostas Laravel Resource.
 * @param {unknown} body
 * @returns {Array}
 */
export function unwrapList(body) {
  if (!body || typeof body !== 'object') return []
  const b = /** @type {{ data?: unknown }} */ (body)
  if (Array.isArray(b.data)) return b.data
  if (Array.isArray(body)) return body
  return []
}

/**
 * Extrai objeto `data` de resposta.
 * @param {unknown} body
 * @returns {Record<string, unknown>|null}
 */
export function unwrapItem(body) {
  if (!body || typeof body !== 'object') return null
  const b = /** @type {{ data?: unknown }} */ (body)
  if (b.data && typeof b.data === 'object' && !Array.isArray(b.data)) {
    return /** @type {Record<string, unknown>} */ (b.data)
  }
  if (!Array.isArray(body) && !('data' in b && Array.isArray(b.data))) {
    return /** @type {Record<string, unknown>} */ (body)
  }
  return null
}

/**
 * IDs de pastorais do usuário (pivot no /me ou lista local).
 * @param {{ pastorais?: Array<{ id: string }>, pastoral_ids?: string[] }|null|undefined} user
 * @returns {string[]}
 */
export function pastoralIdsDoUsuario(user) {
  if (!user) return []
  if (Array.isArray(user.pastoral_ids)) return user.pastoral_ids.filter(Boolean)
  if (Array.isArray(user.pastorais)) return user.pastorais.map((p) => p.id).filter(Boolean)
  return []
}
