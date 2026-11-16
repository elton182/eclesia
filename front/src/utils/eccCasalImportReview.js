/**
 * Helpers da revisão seletiva de importação de casais (SPEC-034).
 */

/** @type {Record<string, string>} */
export const IMPORT_FIELD_LABELS = {
  equipe: 'Equipe',
  nome: 'Nome (ele)',
  email: 'E-mail (ele)',
  telefone: 'Telefone (ele)',
  endereco: 'Endereço',
  bairro: 'Bairro',
  cidade: 'Cidade',
  uf: 'UF',
  cep: 'CEP',
  data_nascimento: 'Nascimento (ele)',
  nome_conjuge: 'Nome (ela)',
  email_conjuge: 'E-mail (ela)',
  telefone_conjuge: 'Telefone (ela)',
  data_nascimento_conjuge: 'Nascimento (ela)',
  data_casamento: 'Data de casamento',
  filhos: 'Filhos',
  observacoes: 'Observações',
  piloto: 'Piloto',
  anos_casados: 'Anos de casados',
  ecc_origem: 'ECC de origem',
  funcao_dirigente: 'Função dirigente',
  foi_coordenador_geral: 'Foi coordenador geral',
  etapas: 'Etapas',
  atividades: 'Atividades / indicações',
  preferencias: 'Preferências de serviço',
}

/**
 * @param {string} field
 */
export function importFieldLabel(field) {
  return IMPORT_FIELD_LABELS[field] || field
}

/**
 * @param {Array<object>} items
 * @returns {Array<{row_index: number, action: string, casal_id: string|null, apply_fields: string[]}>}
 */
export function buildInitialDecisions(items) {
  return (items || []).map((item) => {
    const action = item?.suggested_action || 'skip'
    const casalId = item?.suggested_casal_id || null
    const applyFields = Array.isArray(item?.suggested_apply_fields)
      ? [...item.suggested_apply_fields]
      : []

    return {
      row_index: Number(item?.row_index ?? 0),
      action,
      casal_id: casalId,
      apply_fields: applyFields,
    }
  })
}

/**
 * @param {Array<object>} decisions
 * @param {number} rowIndex
 * @param {Partial<{action: string, casal_id: string|null, apply_fields: string[]}>} patch
 */
export function patchDecision(decisions, rowIndex, patch) {
  return (decisions || []).map((d) => {
    if (Number(d.row_index) !== Number(rowIndex)) return d
    const next = { ...d, ...patch }
    if (next.action === 'create' || next.action === 'skip') {
      next.apply_fields = []
      if (next.action === 'create') next.casal_id = null
    }
    if (next.action === 'update' && !Array.isArray(next.apply_fields)) {
      next.apply_fields = []
    }
    return next
  })
}

/**
 * @param {Array<object>} decisions
 * @param {number} rowIndex
 * @param {string} field
 * @param {boolean} checked
 */
export function toggleApplyField(decisions, rowIndex, field, checked) {
  return (decisions || []).map((d) => {
    if (Number(d.row_index) !== Number(rowIndex)) return d
    const set = new Set(d.apply_fields || [])
    if (checked) set.add(field)
    else set.delete(field)
    return {
      ...d,
      action: d.action === 'skip' && checked ? 'update' : d.action,
      apply_fields: [...set],
    }
  })
}

/**
 * @param {object} summary
 */
export function formatPreviewSummary(summary) {
  const s = summary || {}
  const parts = []
  if (s.new) parts.push(`${s.new} novo(s)`)
  if (s.match) parts.push(`${s.match} com diferença`)
  if (s.identical) parts.push(`${s.identical} idêntico(s)`)
  if (s.ambiguous) parts.push(`${s.ambiguous} ambíguo(s)`)
  if (s.invalid) parts.push(`${s.invalid} inválido(s)`)
  if (s.skipped_empty) parts.push(`${s.skipped_empty} vazio(s)`)
  return parts.length ? parts.join(' · ') : 'Nenhuma linha útil'
}

/**
 * @param {Array<object>} decisions
 */
export function countPendingActions(decisions) {
  let create = 0
  let update = 0
  let skip = 0
  for (const d of decisions || []) {
    if (d.action === 'create') create += 1
    else if (d.action === 'update' && (d.apply_fields || []).length > 0 && d.casal_id) update += 1
    else if (d.action === 'update') skip += 1
    else skip += 1
  }
  return { create, update, skip }
}

/**
 * Exibe valor de diff de forma legível.
 * @param {unknown} value
 */
export function formatDiffValue(value) {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não'
  if (Array.isArray(value)) {
    if (!value.length) return '—'
    try {
      return JSON.stringify(value)
    } catch {
      return String(value)
    }
  }
  return String(value)
}

/**
 * @param {string} status
 */
export function statusBadgeLabel(status) {
  switch (status) {
    case 'new':
      return 'Novo'
    case 'match':
      return 'Diferenças'
    case 'identical':
      return 'Idêntico'
    case 'ambiguous':
      return 'Ambíguo'
    case 'invalid':
      return 'Inválido'
    default:
      return status || '—'
  }
}
