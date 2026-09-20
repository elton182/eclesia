/**
 * Branding do app por tenant (SPEC-014). Fallback = paleta vinho ADR-0004.
 * Site público espelha as mesmas cores (SPEC-011); accent legado mapeia para on_primary.
 */

export const DEFAULT_BRAND_COLORS = Object.freeze({
  primary: '#4E1220',
  secondary: '#C88A5E',
  text: '#2A1418',
  text_muted: '#6B4A50',
  on_primary: '#F7EDE0',
  /** Compat SPEC-011 / site: mapeia para secondary no normalize legado */
  accent: '#F7EDE0',
})

/**
 * @param {unknown} value
 * @returns {value is string}
 */
export function isHexColor(value) {
  return typeof value === 'string' && /^#[0-9A-Fa-f]{6}$/.test(value)
}

/**
 * @param {Record<string, unknown>|null|undefined} cores
 * @returns {{
 *   primary: string,
 *   secondary: string,
 *   text: string,
 *   text_muted: string,
 *   on_primary: string,
 * }}
 */
export function normalizeBrandCores(cores) {
  const primary = isHexColor(cores?.primary) ? cores.primary : DEFAULT_BRAND_COLORS.primary
  const secondary = isHexColor(cores?.secondary)
    ? cores.secondary
    : DEFAULT_BRAND_COLORS.secondary
  // Site (SPEC-011) envia accent; app (SPEC-014) usa on_primary / text
  const onPrimary = isHexColor(cores?.on_primary)
    ? cores.on_primary
    : isHexColor(cores?.accent)
      ? cores.accent
      : DEFAULT_BRAND_COLORS.on_primary
  const text = isHexColor(cores?.text) ? cores.text : DEFAULT_BRAND_COLORS.text
  const textMuted = isHexColor(cores?.text_muted)
    ? cores.text_muted
    : DEFAULT_BRAND_COLORS.text_muted

  return {
    primary,
    secondary,
    text,
    text_muted: textMuted,
    on_primary: onPrimary,
  }
}

/**
 * Aplica CSS variables no :root (documentElement).
 * Derivados soft/hover/dark via color-mix a partir de primary.
 * @param {Record<string, unknown>|null|undefined} cores
 * @param {Document} [doc]
 */
export function applyBrandCores(cores, doc = typeof document !== 'undefined' ? document : null) {
  const brand = normalizeBrandCores(cores)

  if (!doc?.documentElement) {
    return brand
  }

  const root = doc.documentElement
  root.style.setProperty('--color-primary', brand.primary)
  root.style.setProperty(
    '--color-primary-soft',
    `color-mix(in srgb, ${brand.primary} 78%, white)`,
  )
  root.style.setProperty(
    '--color-primary-hover',
    `color-mix(in srgb, ${brand.primary} 72%, white)`,
  )
  root.style.setProperty(
    '--color-primary-dark',
    `color-mix(in srgb, ${brand.primary} 82%, black)`,
  )
  root.style.setProperty('--color-accent', brand.secondary)
  root.style.setProperty('--color-secondary', brand.secondary)
  root.style.setProperty('--color-brand-accent', brand.on_primary)
  root.style.setProperty('--color-ink', brand.text)
  root.style.setProperty('--color-muted', brand.text_muted)
  root.style.setProperty('--color-on-primary', brand.on_primary)

  return brand
}

/**
 * Remove overrides inline (volta ao CSS de :root).
 * @param {Document} [doc]
 */
export function resetBrandCores(doc = typeof document !== 'undefined' ? document : null) {
  if (!doc?.documentElement) return
  const root = doc.documentElement
  ;[
    '--color-primary',
    '--color-primary-soft',
    '--color-primary-hover',
    '--color-primary-dark',
    '--color-accent',
    '--color-secondary',
    '--color-brand-accent',
    '--color-ink',
    '--color-muted',
    '--color-on-primary',
  ].forEach((prop) => root.style.removeProperty(prop))
}
