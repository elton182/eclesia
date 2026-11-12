/**
 * Helpers do dashboard ECC de cadastros e acessos (SPEC-033).
 */

/** @typedef {'ativo'|'inativo'|'nunca_acessou'|'sem_acesso'} StatusAcesso */

/**
 * @param {StatusAcesso|string|null|undefined} status
 * @returns {string}
 */
export function statusAcessoLabel(status) {
  switch (status) {
    case 'ativo':
      return 'Ativo'
    case 'inativo':
      return 'Inativo'
    case 'nunca_acessou':
      return 'Nunca acessou'
    case 'sem_acesso':
      return 'Sem acesso'
    default:
      return '—'
  }
}

/**
 * Tom visual do status (classes utilitárias leves).
 * @param {StatusAcesso|string|null|undefined} status
 * @returns {string}
 */
export function statusAcessoTone(status) {
  switch (status) {
    case 'ativo':
      return 'ok'
    case 'inativo':
      return 'warn'
    case 'nunca_acessou':
      return 'danger'
    case 'sem_acesso':
      return 'muted'
    default:
      return 'muted'
  }
}

/**
 * @param {string|null|undefined} iso
 * @returns {string}
 */
export function formatDateTimeBr(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * @param {'created'|'updated'|string|null|undefined} acao
 * @returns {string}
 */
export function acaoCadastroLabel(acao) {
  if (acao === 'created') return 'Criou'
  if (acao === 'updated') return 'Atualizou'
  return acao || '—'
}

/**
 * Normaliza janela de dias (API: 7–365, default 30).
 * @param {unknown} value
 * @returns {number}
 */
export function normalizeJanelaDias(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return 30
  return Math.min(365, Math.max(7, Math.round(n)))
}

/**
 * Cards de resumo a partir do payload da API.
 * @param {Record<string, number>|null|undefined} resumo
 * @returns {Array<{ key: string, label: string, value: number, hint?: string }>}
 */
export function buildResumoCards(resumo) {
  const r = resumo || {}
  const janela = r.janela_dias ?? 30
  return [
    { key: 'casais', label: 'Casais', value: r.casais_total ?? 0 },
    {
      key: 'atualizados',
      label: 'Fichas atualizadas',
      value: r.casais_atualizados_janela ?? 0,
      hint: `últimos ${janela} dias`,
    },
    {
      key: 'estagnados',
      label: 'Casais estagnados',
      value: r.casais_estagnados ?? 0,
      hint: `sem update há ${janela}+ dias`,
    },
    { key: 'equipes', label: 'Equipes', value: r.equipes_total ?? 0 },
    {
      key: 'com_acesso',
      label: 'Com acesso',
      value: r.equipes_com_acesso ?? 0,
    },
    {
      key: 'nunca',
      label: 'Nunca acessaram',
      value: r.equipes_nunca_acessaram ?? 0,
    },
    {
      key: 'inativas',
      label: 'Inativas na janela',
      value: r.equipes_inativas_janela ?? 0,
      hint: `sem login há ${janela}+ dias (ou nunca)`,
    },
    {
      key: 'sem_acesso',
      label: 'Sem conta de acesso',
      value: r.equipes_sem_acesso ?? 0,
    },
  ]
}
