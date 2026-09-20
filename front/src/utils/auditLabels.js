/**
 * Labels e formatação da trilha de auditoria (SPEC-013).
 */

/** @type {Record<string, string>} */
export const AUDIT_ACTION_LABELS = {
  created: 'Criação',
  updated: 'Edição',
  deleted: 'Exclusão',
  login_success: 'Login',
  login_failed: 'Login falhou',
  logout: 'Logout',
  roles_synced: 'Papéis sincronizados',
}

/** @type {Record<string, string>} */
export const AUDIT_TYPE_LABELS = {
  User: 'Usuário',
  Pessoa: 'Pessoa',
  Igreja: 'Igreja',
  Casal: 'Casal',
  EccEquipe: 'Equipe ECC',
  EccEvento: 'Evento ECC',
}

/**
 * @param {string|null|undefined} action
 * @returns {string}
 */
export function auditActionLabel(action) {
  if (!action) return '—'
  return AUDIT_ACTION_LABELS[action] || action
}

/**
 * @param {string|null|undefined} type
 * @returns {string}
 */
export function auditTypeLabel(type) {
  if (!type) return '—'
  const short = String(type).includes('\\') ? String(type).split('\\').pop() : type
  return AUDIT_TYPE_LABELS[short] || short
}

/**
 * @param {string|null|undefined} iso
 * @returns {string}
 */
export function formatAuditDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return String(iso)
  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Resumo curto do diff para listagem.
 * @param {{ old_values?: Record<string, unknown>|null, new_values?: Record<string, unknown>|null }} log
 * @returns {string}
 */
export function auditDiffSummary(log) {
  const keys = new Set([
    ...Object.keys(log?.old_values || {}),
    ...Object.keys(log?.new_values || {}),
  ])
  if (keys.size === 0) return '—'
  const list = [...keys].slice(0, 4)
  const more = keys.size > 4 ? ` (+${keys.size - 4})` : ''
  return list.join(', ') + more
}
