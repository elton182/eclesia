/**
 * Helpers do acesso compartilhado da equipe (SPEC-021 / SPEC-024 / SPEC-025).
 */

/**
 * Normaliza texto digitado para username (slug), espelhando a API.
 * @param {string} value
 * @returns {string}
 */
export function normalizeEquipeUsername(value) {
  return String(value || '')
    .trim()
    .toLocaleLowerCase('pt-BR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

/**
 * Valida username antes do PUT /ecc/equipes/{id}/acesso.
 * @param {string} value
 * @returns {{ ok: true, username: string } | { ok: false, message: string }}
 */
export function validateEquipeUsername(value) {
  const username = normalizeEquipeUsername(value)
  if (!username) {
    return { ok: false, message: 'Informe um login válido (letras, números e hífens).' }
  }
  if (username.length > 120) {
    return { ok: false, message: 'Login deve ter no máximo 120 caracteres.' }
  }
  return { ok: true, username }
}

/**
 * Nome sugerido para o PDF de equipes (SPEC-025).
 * @param {string} [igrejaSlug]
 * @returns {string}
 */
export function equipesPdfFilename(igrejaSlug = 'igreja') {
  const slug = normalizeEquipeUsername(igrejaSlug) || 'igreja'
  return `equipes-${slug}.pdf`
}

/**
 * Dispara download de um blob no browser.
 * @param {Blob} blob
 * @param {string} filename
 */
export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
