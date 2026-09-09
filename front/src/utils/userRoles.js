/**
 * Labels de papéis canônicos para a UI.
 * @type {Record<string, string>}
 */
export const ROLE_LABELS = {
  'admin-tenant': 'Admin Organização',
  'admin-igreja': 'Admin Igreja',
  cadastros: 'Cadastros',
  'lider-equipe': 'Líder de Equipe',
}

/**
 * @param {string} name
 * @returns {string}
 */
export function roleLabel(name) {
  return ROLE_LABELS[name] || name
}

/**
 * Monta o body de PUT /users/{id}/roles a partir dos toggles da UI.
 *
 * @param {{
 *   igrejaId: string,
 *   toggles: Record<string, boolean>,
 *   equipeIds: string[],
 * }} input
 * @returns {{ igreja_id: string|null, roles: Array<{ name: string, equipe_ids?: string[] }> }}
 */
export function buildSyncRolesPayload({ igrejaId, toggles, equipeIds }) {
  const roles = []

  for (const [name, enabled] of Object.entries(toggles)) {
    if (!enabled) continue

    if (name === 'lider-equipe') {
      roles.push({ name, equipe_ids: [...equipeIds] })
    } else {
      roles.push({ name })
    }
  }

  const hasChurchRole = roles.some((r) => r.name !== 'admin-tenant')

  return {
    igreja_id: hasChurchRole ? igrejaId || null : null,
    roles,
  }
}
