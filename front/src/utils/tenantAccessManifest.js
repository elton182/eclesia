/**
 * Manifest / atalho PWA de acesso por tenant (SPEC-023 / ADR-0009).
 */

export const DEFAULT_MANIFEST_HREF = '/manifest.webmanifest'

/** Segmento reservado da URL pública (não é pageSlug do CMS). */
export const SITE_INSTALL_SEGMENT = 'instalar'

/**
 * @param {string} tenantSlug
 * @returns {string}
 */
export function tenantAccessManifestPath(tenantSlug) {
  const slug = String(tenantSlug || '').trim()
  if (!slug) return ''
  return `/site/${encodeURIComponent(slug)}/manifest.webmanifest`
}

/**
 * @param {string} tenantSlug
 * @returns {string}
 */
export function tenantAccessInstallPath(tenantSlug) {
  const slug = String(tenantSlug || '').trim()
  if (!slug) return '/'
  return `/site/${encodeURIComponent(slug)}/${SITE_INSTALL_SEGMENT}`
}

/**
 * @param {string} tenantSlug
 * @returns {string}
 */
export function tenantAccessStartUrl(tenantSlug) {
  const slug = String(tenantSlug || '').trim()
  if (!slug) return '/entrar'
  return `/entrar?tenant=${encodeURIComponent(slug)}`
}

/**
 * @param {string} href
 * @param {string} [base]
 * @returns {string}
 */
export function normalizeManifestPath(href, base = 'http://local.invalid') {
  const raw = String(href || '').trim()
  if (!raw) return ''
  try {
    return new URL(raw, base).pathname
  } catch {
    return raw.split('?')[0] || ''
  }
}

/**
 * @param {Document|null|undefined} doc
 * @returns {string}
 */
export function currentManifestHref(doc = typeof document !== 'undefined' ? document : null) {
  if (!doc) return ''
  const link = doc.querySelector('link[rel="manifest"]')
  return link?.getAttribute('href') || ''
}

/**
 * @param {string} href
 * @param {Document|null|undefined} [doc]
 */
export function applyManifestHref(href, doc = typeof document !== 'undefined' ? document : null) {
  if (!doc || !href) return
  let link = doc.querySelector('link[rel="manifest"]')
  if (!link) {
    link = doc.createElement('link')
    link.setAttribute('rel', 'manifest')
    doc.head.appendChild(link)
  }
  link.setAttribute('href', href)
}

/**
 * @param {string} tenantSlug
 * @param {Document|null|undefined} [doc]
 * @returns {{ changed: boolean, href: string }}
 */
export function ensureTenantAccessManifest(
  tenantSlug,
  doc = typeof document !== 'undefined' ? document : null,
) {
  const href = tenantAccessManifestPath(tenantSlug)
  if (!href || !doc) {
    return { changed: false, href }
  }
  const before = normalizeManifestPath(currentManifestHref(doc))
  applyManifestHref(href, doc)
  const after = normalizeManifestPath(currentManifestHref(doc))
  return { changed: before !== after, href: after }
}

/**
 * @param {string} tenantSlug
 * @param {Document|null|undefined} [doc]
 */
export function applyTenantAccessManifest(
  tenantSlug,
  doc = typeof document !== 'undefined' ? document : null,
) {
  ensureTenantAccessManifest(tenantSlug, doc)
}

/**
 * @param {Document|null|undefined} [doc]
 */
export function restoreDefaultManifest(doc = typeof document !== 'undefined' ? document : null) {
  applyManifestHref(DEFAULT_MANIFEST_HREF, doc)
}

/**
 * Orientação quando o prompt nativo não está disponível (iOS etc.).
 * @param {{ userAgent?: string, maxTouchPoints?: number, platform?: string }|null|undefined} [nav]
 * @returns {'ios' | 'android' | 'desktop'}
 */
export function detectInstallPlatform(nav = typeof navigator !== 'undefined' ? navigator : null) {
  if (!nav) return 'desktop'
  const ua = String(nav.userAgent || '')
  const platform = String(nav.platform || '')
  const touchPoints = Number(nav.maxTouchPoints || 0)
  const isIOS =
    /iPad|iPhone|iPod/.test(ua) ||
    (platform === 'MacIntel' && touchPoints > 1)
  if (isIOS) return 'ios'
  if (/Android/i.test(ua)) return 'android'
  return 'desktop'
}
