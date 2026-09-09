/**
 * Labels de papéis canônicos para a UI.
 * @type {Record<string, string>}
 */
export const ROLE_LABELS = {
  'admin-tenant': 'Admin Organização',
  'admin-igreja': 'Admin Igreja',
  'cadastros-usuarios': 'Cadastros · Usuários',
  'cadastros-equipes': 'Cadastros · Equipes',
  'cadastros-casais': 'Cadastros · Casais',
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
 * @param {{ permissions?: string[], roles?: Array<{ name: string }> }|null|undefined} user
 * @param {string} permission
 * @param {{ isSuperAdmin?: boolean }} [opts]
 */
export function userHasPermission(user, permission, opts = {}) {
  if (opts.isSuperAdmin) return true
  if (!user) return false
  if (Array.isArray(user.permissions) && user.permissions.includes(permission)) {
    return true
  }
  // Fallback por papel (login ainda sem lista de permissions)
  const roleNames = (user.roles || []).map((r) => r.name)
  if (roleNames.includes('admin-tenant') || roleNames.includes('admin-igreja')) {
    return true
  }
  if (permission === 'telas.usuarios' && roleNames.includes('cadastros-usuarios')) return true
  if (permission === 'telas.equipes' && (roleNames.includes('cadastros-equipes') || roleNames.includes('lider-equipe'))) {
    return true
  }
  if (permission === 'telas.casais' && (roleNames.includes('cadastros-casais') || roleNames.includes('lider-equipe'))) {
    return true
  }
  return false
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
