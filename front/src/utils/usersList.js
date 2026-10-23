/**
 * Helpers da listagem paginada de usuários (GET /users).
 */

/**
 * @param {{ page?: number, perPage?: number, q?: string }} opts
 * @returns {Record<string, string|number>}
 */
export function buildUsersListParams({ page = 1, perPage = 15, q = '' } = {}) {
  const params = {
    page: Math.max(1, Number(page) || 1),
    per_page: Math.min(100, Math.max(1, Number(perPage) || 15)),
  }
  const term = String(q || '').trim()
  if (term) params.q = term
  return params
}

/**
 * Extrai meta de paginação Laravel Resource / LengthAwarePaginator.
 * @param {unknown} body
 * @param {number} [fallbackTotal]
 * @returns {{ current_page: number, last_page: number, per_page: number, total: number }}
 */
export function parseUsersListMeta(body, fallbackTotal = 0) {
  const b = body && typeof body === 'object' ? /** @type {Record<string, unknown>} */ (body) : {}
  const meta = b.meta && typeof b.meta === 'object' ? /** @type {Record<string, unknown>} */ (b.meta) : b
  return {
    current_page: Number(meta.current_page) || 1,
    last_page: Number(meta.last_page) || 1,
    per_page: Number(meta.per_page) || 15,
    total: Number(meta.total) || fallbackTotal,
  }
}
