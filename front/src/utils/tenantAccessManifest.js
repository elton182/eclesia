/**
 * Manifest / atalho PWA de acesso por tenant (SPEC-023 / ADR-0009).
 */

export const DEFAULT_MANIFEST_HREF = '/manifest.webmanifest'

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
export function tenantAccessStartUrl(tenantSlug) {
  const slug = String(tenantSlug || '').trim()
  if (!slug) return '/entrar'
  return `/entrar?tenant=${encodeURIComponent(slug)}`
}

/**
 * @param {string} href
 * @param {Document} [doc]
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
 * @param {Document} [doc]
 */
export function applyTenantAccessManifest(tenantSlug, doc = typeof document !== 'undefined' ? document : null) {
  const href = tenantAccessManifestPath(tenantSlug)
  if (!href) return
  applyManifestHref(href, doc)
}

/**
 * @param {Document} [doc]
 */
export function restoreDefaultManifest(doc = typeof document !== 'undefined' ? document : null) {
  applyManifestHref(DEFAULT_MANIFEST_HREF, doc)
}
