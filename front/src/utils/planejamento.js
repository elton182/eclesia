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

/** Cores de tag por status da solicitação (UI). */
export const STATUS_SOLICITACAO_STYLE = Object.freeze({
  proposta: { bg: 'rgba(42,20,24,0.08)', fg: 'rgba(42,20,24,0.72)' },
  confirmado: { bg: 'rgba(34,120,72,0.12)', fg: '#1f6b3a' },
  ajuste_solicitado: { bg: 'var(--color-accent-soft)', fg: 'var(--color-accent-dark)' },
  reagendado: { bg: 'rgba(60,100,160,0.12)', fg: '#2a4a7a' },
  recusado: { bg: 'rgba(160,40,40,0.12)', fg: '#8a2424' },
  cotidiano: { bg: 'rgba(42,20,24,0.06)', fg: 'rgba(42,20,24,0.55)' },
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
 * @param {string|undefined|null} status
 * @returns {{ bg: string, fg: string }}
 */
export function styleStatusSolicitacao(status) {
  return STATUS_SOLICITACAO_STYLE[status] || STATUS_SOLICITACAO_STYLE.proposta
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
 * Filtra eventos cuja data_inicio cai na semana Dom–Sáb iniciada em `semanaInicioIso`.
 * @param {Array<{ data_inicio?: string }>|null|undefined} eventos
 * @param {string} semanaInicioIso YYYY-MM-DD (domingo)
 * @returns {typeof eventos}
 */
export function filterEventosPorSemana(eventos, semanaInicioIso) {
  const start = (semanaInicioIso || '').slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(start)) return [...(eventos || [])]
  const end = (() => {
    const [y, m, d] = start.split('-').map(Number)
    const dt = new Date(y, m - 1, d + 6)
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`
  })()
  return (eventos || []).filter((ev) => {
    const day = (ev.data_inicio || '').slice(0, 10)
    return day >= start && day <= end
  })
}

/** Paleta estável para tags de pastoral (visão gestor). */
export const PASTORAL_CORES = Object.freeze([
  '#4E1220',
  '#B4703F',
  '#2a6b5a',
  '#2a4a7a',
  '#8a2424',
  '#5c4a2a',
  '#3d5a40',
  '#6B1C2B',
  '#7a3e5c',
  '#1f5a6b',
])

/**
 * Cor de tag para pastoral pelo índice na lista.
 * @param {number} index
 * @returns {string}
 */
export function corPastoralPorIndice(index) {
  const i = Number(index)
  if (!Number.isFinite(i) || i < 0) return PASTORAL_CORES[0]
  return PASTORAL_CORES[i % PASTORAL_CORES.length]
}

/**
 * Mapa pastoral_id → cor a partir da lista ordenada.
 * @param {Array<{ id?: string }>|null|undefined} pastorais
 * @returns {Record<string, string>}
 */
export function mapaCoresPastorais(pastorais) {
  /** @type {Record<string, string>} */
  const out = {}
  ;(pastorais || []).forEach((p, idx) => {
    if (p?.id) out[p.id] = corPastoralPorIndice(idx)
  })
  return out
}

/**
 * Mantém só eventos de pastorais marcadas como visíveis.
 * Se `visiveis` for null/undefined, não filtra.
 * @param {Array<{ pastoral_id?: string }>|null|undefined} eventos
 * @param {Set<string>|string[]|null|undefined} visiveis
 * @returns {typeof eventos}
 */
export function filterEventosPorPastoraisVisiveis(eventos, visiveis) {
  if (visiveis == null) return [...(eventos || [])]
  const set = visiveis instanceof Set ? visiveis : new Set(visiveis)
  if (!set.size) return []
  return (eventos || []).filter((ev) => ev.pastoral_id && set.has(ev.pastoral_id))
}

/**
 * Alterna id no conjunto (imuta → novo Set).
 * @param {Set<string>|string[]|null|undefined} atuais
 * @param {string} id
 * @returns {Set<string>}
 */
export function togglePastoralVisivel(atuais, id) {
  const next = new Set(atuais instanceof Set ? atuais : atuais || [])
  if (!id) return next
  if (next.has(id)) next.delete(id)
  else next.add(id)
  return next
}

/**
 * @param {{ local_ids?: string[], locais?: Array<{ id?: string }|string> }|null|undefined} evento
 * @returns {string[]}
 */
export function localIdsDoEvento(evento) {
  if (!evento) return []
  if (Array.isArray(evento.local_ids) && evento.local_ids.length) {
    return evento.local_ids.filter(Boolean).map(String)
  }
  return (evento.locais || [])
    .map((l) => (typeof l === 'string' ? l : l?.id))
    .filter(Boolean)
    .map(String)
}

/**
 * Intervalo [inicio, fim) em ms — espelha PlanejamentoIntervalo da API.
 * @param {{
 *   data_inicio?: string,
 *   data_fim?: string|null,
 *   hora_inicio?: string|null,
 *   hora_fim?: string|null,
 * }} evento
 * @returns {{ inicio: number, fim: number }|null}
 */
export function intervaloEventoMs(evento) {
  const dataInicio = (evento?.data_inicio || '').slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dataInicio)) return null
  const dataFimRaw = evento?.data_fim ? String(evento.data_fim).slice(0, 10) : null
  const dataFim = dataFimRaw && /^\d{4}-\d{2}-\d{2}$/.test(dataFimRaw) ? dataFimRaw : null
  const horaInicio = formatHoraCurta(evento?.hora_inicio) || null
  const horaFim = formatHoraCurta(evento?.hora_fim) || null

  const inicio = Date.parse(`${dataInicio}T${horaInicio || '00:00'}:00`)
  if (Number.isNaN(inicio)) return null

  if (horaFim === null && horaInicio !== null && dataFim === null) {
    return { inicio, fim: inicio + 60 * 60 * 1000 }
  }

  const fim = Date.parse(`${dataFim || dataInicio}T${horaFim || horaInicio || '23:59'}:00`)
  if (Number.isNaN(fim)) return null
  return { inicio, fim }
}

/**
 * Anota cada evento com `tem_conflito` e `conflitos[]` (local + horário sobreposto).
 * Não muta a lista original.
 * @param {Array|null|undefined} eventos
 * @returns {Array}
 */
export function anotarConflitosEventos(eventos) {
  const list = Array.isArray(eventos) ? eventos : []
  /** @type {Array<{ ev: any, locais: string[], intervalo: { inicio: number, fim: number }|null }>} */
  const prepared = list.map((ev) => ({
    ev,
    locais: localIdsDoEvento(ev),
    intervalo: intervaloEventoMs(ev),
  }))

  return prepared.map((row, i) => {
    /** @type {Array<Record<string, unknown>>} */
    const conflitos = []
    if (row.locais.length && row.intervalo) {
      for (let j = 0; j < prepared.length; j++) {
        if (i === j) continue
        const other = prepared[j]
        if (!other.intervalo || !other.locais.length) continue
        const shared = row.locais.filter((id) => other.locais.includes(id))
        if (!shared.length) continue
        const a = row.intervalo
        const b = other.intervalo
        if (a.inicio < b.fim && b.inicio < a.fim) {
          conflitos.push({
            id: other.ev.id,
            titulo: other.ev.titulo,
            pastoral_id: other.ev.pastoral_id,
            pastoral_nome: other.ev.pastoral_nome || other.ev.pastoral?.nome,
            data_inicio: (other.ev.data_inicio || '').slice(0, 10),
            data_fim: other.ev.data_fim ? String(other.ev.data_fim).slice(0, 10) : null,
            hora_inicio: formatHoraCurta(other.ev.hora_inicio) || null,
            hora_fim: formatHoraCurta(other.ev.hora_fim) || null,
            local_ids: shared,
          })
        }
      }
    }
    return {
      ...row.ev,
      tem_conflito: conflitos.length > 0,
      conflitos,
    }
  })
}

/**
 * Conta eventos com conflito na lista (já anotada ou com conflitos[]).
 * @param {Array<{ tem_conflito?: boolean, conflitos?: unknown[] }>|null|undefined} eventos
 * @returns {number}
 */
export function contarEventosComConflito(eventos) {
  return (eventos || []).filter(
    (ev) => ev.tem_conflito === true || (Array.isArray(ev.conflitos) && ev.conflitos.length > 0),
  ).length
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

/**
 * Mapa pastoral_id → token em claro dos links ativos (GET .../pastorais/tokens).
 * @param {Array<{ pastoral_id?: string, token?: string|null, ativo?: boolean }>} tokens
 * @returns {Record<string, string>}
 */
export function mapPastoralLinks(tokens) {
  /** @type {Record<string, string>} */
  const out = {}
  for (const t of Array.isArray(tokens) ? tokens : []) {
    if (t && t.pastoral_id && t.ativo !== false && t.token) {
      out[t.pastoral_id] = t.token
    }
  }
  return out
}

/** Primeira hora (incluída) da grade semanal com eixo de horas. */
export const GRADE_SEMANA_HORA_INICIO = 6
/** Última hora (incluída como rótulo; grade vai até fim+1h). */
export const GRADE_SEMANA_HORA_FIM = 22

/**
 * Rótulos HH:00 da grade semanal (24h).
 * @param {number} [inicio]
 * @param {number} [fim]
 * @returns {string[]}
 */
export function horasGradeSemana(inicio = GRADE_SEMANA_HORA_INICIO, fim = GRADE_SEMANA_HORA_FIM) {
  const a = Math.max(0, Math.min(23, Math.floor(Number(inicio))))
  const b = Math.max(a, Math.min(23, Math.floor(Number(fim))))
  /** @type {string[]} */
  const out = []
  for (let h = a; h <= b; h++) {
    out.push(`${String(h).padStart(2, '0')}:00`)
  }
  return out
}

/**
 * Minutos desde 00:00 a partir de "HH:MM" / "HH:MM:SS".
 * @param {string|null|undefined} hora
 * @returns {number|null}
 */
export function minutosDoDia(hora) {
  const curta = formatHoraCurta(hora)
  if (!curta) return null
  const m = curta.match(/^(\d{2}):(\d{2})$/)
  if (!m) return null
  return Number(m[1]) * 60 + Number(m[2])
}

/**
 * Posição percentual de um evento na grade horária (top + height).
 * Sem hora → null (vai para faixa “sem horário”).
 *
 * @param {{ hora_inicio?: string|null, hora_fim?: string|null }} evento
 * @param {{ inicioHora?: number, fimHora?: number, minHeightPct?: number }} [opts]
 * @returns {{ topPct: number, heightPct: number }|null}
 */
export function layoutEventoGradeSemana(evento, opts = {}) {
  const inicioHora = opts.inicioHora ?? GRADE_SEMANA_HORA_INICIO
  const fimHora = opts.fimHora ?? GRADE_SEMANA_HORA_FIM
  const spanMin = Math.max(60, (fimHora + 1 - inicioHora) * 60)
  const startBase = inicioHora * 60
  const ini = minutosDoDia(evento?.hora_inicio)
  if (ini === null) return null
  let fim = minutosDoDia(evento?.hora_fim)
  if (fim === null || fim <= ini) fim = ini + 60
  const topMin = Math.max(0, Math.min(spanMin, ini - startBase))
  const endMin = Math.max(topMin + 15, Math.min(spanMin, fim - startBase))
  const topPct = (topMin / spanMin) * 100
  const heightPct = Math.max(opts.minHeightPct ?? 3, ((endMin - topMin) / spanMin) * 100)
  return { topPct, heightPct }
}

/**
 * Particiona eventos do dia em com horário / sem horário.
 * @param {Array<{ hora_inicio?: string|null }>|null|undefined} eventos
 */
export function particionarEventosGradeSemana(eventos) {
  /** @type {typeof eventos} */
  const comHora = []
  /** @type {typeof eventos} */
  const semHora = []
  for (const ev of eventos || []) {
    if (minutosDoDia(ev?.hora_inicio) !== null) comHora.push(ev)
    else semHora.push(ev)
  }
  return { comHora, semHora }
}
