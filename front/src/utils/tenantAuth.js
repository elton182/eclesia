/**
 * Helpers de login tenant e aliases (compartilhados com testes).
 */

export const LOGIN_TENANT_STORAGE_KEY = 'eclesia_login_tenant'
/** Guarda e-mail ou username do último login (SPEC-021). */
export const LOGIN_EMAIL_STORAGE_KEY = 'eclesia_login_email'

/**
 * Persiste organização e login (e-mail ou usuário) do último login bem-sucedido (não a senha).
 * @param {{ tenant?: string, login?: string, email?: string }} credentials
 */
export function persistLoginCredentials({ tenant, login, email } = {}) {
  const t = String(tenant || '').trim()
  const e = String(login || email || '').trim()
  if (t) {
    localStorage.setItem(LOGIN_TENANT_STORAGE_KEY, t)
  } else {
    localStorage.removeItem(LOGIN_TENANT_STORAGE_KEY)
  }
  if (e) {
    localStorage.setItem(LOGIN_EMAIL_STORAGE_KEY, e)
  } else {
    localStorage.removeItem(LOGIN_EMAIL_STORAGE_KEY)
  }
}

/**
 * @returns {{ tenant: string, email: string, login: string }}
 */
export function loadLoginCredentials() {
  const login = localStorage.getItem(LOGIN_EMAIL_STORAGE_KEY) || ''
  return {
    tenant: localStorage.getItem(LOGIN_TENANT_STORAGE_KEY) || '',
    email: login,
    login,
  }
}

/**
 * Grava só o slug da organização para pré-preencher `/entrar`
 * (não altera o login lembrado).
 * @param {string} tenantSlug
 */
export function rememberTenantForLogin(tenantSlug) {
  const t = String(tenantSlug || '').trim()
  if (!t) return
  localStorage.setItem(LOGIN_TENANT_STORAGE_KEY, t)
}

/**
 * @param {string} tenant
 * @param {string} login
 * @param {string} password
 */
export function buildTenantLoginPayload(tenant, login, password) {
  return {
    tenant: String(tenant || '').trim(),
    login: String(login || '').trim(),
    password: String(password || ''),
  }
}

export function validateTenantLoginForm({ tenant, login, email, password }) {
  const identifier = String(login || email || '').trim()
  if (!tenant || !identifier || !password) {
    return { ok: false, error: 'Preencha organização, e-mail ou usuário, e senha.' }
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
