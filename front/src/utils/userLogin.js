/**
 * Helpers de login de usuário (SPEC-026) — e-mail e/ou username.
 */

import { normalizeEquipeUsername } from './eccEquipeAcesso.js'

/**
 * @param {string} value
 * @returns {string}
 */
export function normalizeUsername(value) {
  return normalizeEquipeUsername(value)
}

/**
 * @param {string|null|undefined} email
 * @returns {boolean}
 */
export function isSyntheticEmail(email) {
  if (!email) return false
  const lower = String(email).toLowerCase()
  return lower.endsWith('@login.local') || lower.endsWith('@equipe.local')
}

/**
 * Identificador visível na lista/ficha.
 * @param {{ username?: string|null, email?: string|null }} user
 * @returns {string}
 */
export function displayUserLogin(user) {
  const username = user?.username ? String(user.username).trim() : ''
  if (username) return username
  const email = user?.email && !isSyntheticEmail(user.email) ? String(user.email).trim() : ''
  return email || '—'
}

/**
 * Filtra usuários por nome, e-mail ou username (tela Usuários).
 * @param {Array<{ name?: string, email?: string|null, username?: string|null }>|null|undefined} users
 * @param {string} query
 * @returns {Array}
 */
export function filterUsersByLoginSearch(users, query) {
  const q = String(query || '').trim().toLocaleLowerCase('pt-BR')
  const list = users || []
  if (!q) return [...list]
  return list.filter((u) => {
    const name = String(u.name || '').toLocaleLowerCase('pt-BR')
    const email = u.email && !isSyntheticEmail(u.email)
      ? String(u.email).toLocaleLowerCase('pt-BR')
      : ''
    const username = String(u.username || '').toLocaleLowerCase('pt-BR')
    return name.includes(q) || email.includes(q) || username.includes(q)
  })
}

/**
 * Valida create/update: precisa de e-mail real ou username.
 * @param {{ email?: string, username?: string }} input
 * @returns {{ ok: true, email: string|null, username: string|null } | { ok: false, message: string }}
 */
export function validateUserLoginFields(input) {
  const rawEmail = String(input?.email ?? '').trim()
  const email = rawEmail && !isSyntheticEmail(rawEmail) ? rawEmail : null
  const rawUsername = String(input?.username ?? '').trim()
  const username = normalizeUsername(rawUsername) || null

  if (rawUsername && !username) {
    return { ok: false, message: 'Informe um login válido (letras, números e hífens).' }
  }

  if (!email && !username) {
    return { ok: false, message: 'Informe e-mail ou login (username).' }
  }

  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return { ok: false, message: 'E-mail inválido.' }
  }

  return { ok: true, email, username }
}
