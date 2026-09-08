/**
 * Helpers de login tenant e aliases (compartilhados com testes).
 */

export function buildTenantLoginPayload(tenant, email, password) {
  return {
    tenant: String(tenant || '').trim(),
    email: String(email || '').trim(),
    password: String(password || ''),
  }
}

export function validateTenantLoginForm({ tenant, email, password }) {
  if (!tenant || !email || !password) {
    return { ok: false, error: 'Preencha organização, e-mail e senha.' }
  }
  return { ok: true }
}

export function parseTenantAliases(text) {
  return String(text || '')
    .split(/[,;\n]/)
    .map((s) => s.trim())
    .filter(Boolean)
}

/** Extrai mensagem legível de erro Axios/Laravel para formulários. */
export function extractApiError(error, fallback = 'Falha ao salvar') {
  const data = error?.response?.data
  if (data?.message) return String(data.message)
  const fieldErrors = Object.values(data?.errors || {}).flat()
  if (fieldErrors.length) return fieldErrors.join(' ')
  if (error?.message) return String(error.message)
  return fallback
}
