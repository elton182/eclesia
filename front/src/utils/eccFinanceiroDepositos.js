/**
 * Helpers do depósito mensal da equipe ECC (SPEC-020).
 */

/** @typedef {'provisionado'|'confirmado'|'rejeitado'} DepositoStatus */

/**
 * @param {DepositoStatus|string|null|undefined} status
 * @returns {string}
 */
export function labelStatusDeposito(status) {
  switch (status) {
    case 'provisionado':
      return 'Aguardando aprovação'
    case 'confirmado':
      return 'Confirmado'
    case 'rejeitado':
      return 'Rejeitado'
    default:
      return status || '—'
  }
}

/**
 * Competência atual (YYYY-MM) no fuso local.
 * @param {Date} [date]
 * @returns {string}
 */
export function competenciaAtual(date = new Date()) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  return `${y}-${m}`
}

/**
 * @param {string} competencia — YYYY-MM
 * @returns {string} — MM/YYYY
 */
export function formatCompetencia(competencia) {
  if (!competencia || !/^\d{4}-\d{2}$/.test(competencia)) return competencia || '—'
  const [y, m] = competencia.split('-')
  return `${m}/${y}`
}

/**
 * @param {Array<{ status?: string }>} depositos
 * @returns {Array}
 */
export function depositosPendentes(depositos) {
  return (depositos || []).filter((d) => d.status === 'provisionado')
}

/**
 * Monta FormData para submissão/atualização.
 * @param {{ equipe_id?: string, competencia?: string, valor: string|number, conta_id?: string, comprovante?: File|null }} fields
 * @returns {FormData}
 */
export function buildDepositoFormData(fields) {
  const fd = new FormData()
  if (fields.equipe_id) fd.append('equipe_id', fields.equipe_id)
  if (fields.competencia) fd.append('competencia', fields.competencia)
  if (fields.valor !== undefined && fields.valor !== null && fields.valor !== '') {
    fd.append('valor', String(fields.valor))
  }
  if (fields.conta_id) fd.append('conta_id', fields.conta_id)
  if (fields.comprovante) fd.append('comprovante', fields.comprovante)
  return fd
}
