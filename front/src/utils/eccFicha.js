/**
 * Labels e helpers da ficha enriquecida ECC (SPEC-015).
 */

/** @type {Record<string, string>} */
export const ATIVIDADE_STATUS_LABELS = {
  A: 'Aceitou',
  IC: 'Indicado para coordenação',
  C: 'Coordenou',
  N: 'Não aceitou',
  NA: 'Não aceita',
  NN: 'Não aceita equipe de trabalho',
}

/** @type {string[]} */
export const ATIVIDADE_STATUS_CODES = Object.keys(ATIVIDADE_STATUS_LABELS)

/**
 * @param {string|null|undefined} code
 */
export function atividadeStatusLabel(code) {
  if (!code) return '—'
  return ATIVIDADE_STATUS_LABELS[code] || code
}

/**
 * @param {number|string|null|undefined} etapa
 */
export function etapaLabel(etapa) {
  const n = Number(etapa)
  if (n === 1) return '1ª etapa'
  if (n === 2) return '2ª etapa'
  if (n === 3) return '3ª etapa'
  return `Etapa ${etapa ?? '—'}`
}

/**
 * Garante 3 slots de etapa no formulário.
 * @param {Array<{etapa?: number, ecc_numero?: string, data?: string, local?: string}>|null|undefined} etapas
 */
export function normalizeEtapasForm(etapas) {
  const byEtapa = new Map((etapas || []).map((e) => [Number(e.etapa), e]))
  return [1, 2, 3].map((etapa) => {
    const row = byEtapa.get(etapa) || {}
    return {
      etapa,
      ecc_numero: row.ecc_numero || '',
      data: row.data || '',
      local: row.local || '',
    }
  })
}

/**
 * Remove etapas totalmente vazias antes de enviar.
 * @param {Array<{etapa: number, ecc_numero?: string, data?: string, local?: string}>} etapas
 */
export function etapasPayload(etapas) {
  return (etapas || [])
    .filter((e) => e.ecc_numero || e.data || e.local)
    .map((e) => ({
      etapa: Number(e.etapa),
      ecc_numero: e.ecc_numero || null,
      data: e.data || null,
      local: e.local || null,
    }))
}

/**
 * @returns {{ecc_numero: string, equipe_servico_id: string, status: string, observacao: string}}
 */
export function emptyAtividadeRow() {
  return {
    ecc_numero: '',
    equipe_servico_id: '',
    status: 'A',
    observacao: '',
  }
}

/**
 * @returns {{equipe_servico_id: string, ordem: number|string}}
 */
export function emptyPreferenciaRow(ordem = 1) {
  return {
    equipe_servico_id: '',
    ordem,
  }
}

/**
 * Monta payload de preferências: omite vazias e deduplica equipe_servico_id
 * (mantém a primeira ocorrência e a ordem informada / índice).
 * @param {Array<{equipe_servico_id?: string, ordem?: number|string}>|null|undefined} preferencias
 * @returns {Array<{equipe_servico_id: string, ordem: number}>}
 */
export function preferenciasPayload(preferencias) {
  const seen = new Set()
  const out = []
  for (const p of preferencias || []) {
    const id = p?.equipe_servico_id ? String(p.equipe_servico_id) : ''
    if (!id || seen.has(id)) continue
    seen.add(id)
    out.push({
      equipe_servico_id: id,
      ordem: p.ordem ? Number(p.ordem) : out.length + 1,
    })
  }
  return out
}

/**
 * Equipes disponíveis para o select da linha `rowIndex`
 * (exclui equipes já escolhidas em outras linhas; mantém a seleção atual da linha).
 * @param {Array<{id: string, nome?: string}>} equipes
 * @param {Array<{equipe_servico_id?: string}>} preferencias
 * @param {number} rowIndex
 */
export function equipesDisponiveisParaPreferencia(equipes, preferencias, rowIndex) {
  const currentId = String(preferencias?.[rowIndex]?.equipe_servico_id || '')
  const selectedElsewhere = new Set(
    (preferencias || [])
      .map((p, i) => (i === rowIndex ? '' : String(p?.equipe_servico_id || '')))
      .filter(Boolean),
  )
  return (equipes || []).filter((eq) => {
    const id = String(eq.id)
    if (currentId && id === currentId) return true
    return !selectedElsewhere.has(id)
  })
}
