/**
 * Utilitários do CMS de blocos do site público.
 */

export const BLOCK_TYPES = [
  'hero',
  'banner',
  'richtext',
  'igrejas_list',
  'comunicados_list',
  'pastorais_list',
  'form',
  'html',
]

/**
 * @param {unknown} blocks
 * @returns {Array<{ tipo: string, ordem: number, visivel: boolean, payload: Record<string, unknown> }>}
 */
export function normalizeBlocks(blocks) {
  if (!Array.isArray(blocks)) return []
  return blocks
    .filter((b) => b && typeof b === 'object' && b.visivel !== false)
    .slice()
    .sort((a, b) => (Number(a.ordem) || 0) - (Number(b.ordem) || 0))
}

/**
 * Monta menu a partir de settings.menu ou páginas com mostrar_no_menu.
 * @param {Array<{ label?: string, slug?: string, href?: string }>|null|undefined} menu
 * @param {string} tenantSlug
 */
export function resolveMenuLinks(menu, tenantSlug) {
  const base = `/site/${tenantSlug}`
  if (!Array.isArray(menu) || menu.length === 0) {
    return [{ label: 'Início', href: base }]
  }
  return menu.map((item) => {
    if (item.href) return { label: item.label || item.href, href: item.href }
    const slug = item.slug || 'home'
    return {
      label: item.label || slug,
      href: slug === 'home' ? base : `${base}/${slug}`,
    }
  })
}

/**
 * @param {Record<string, unknown>|null|undefined} seo
 * @param {string} fallbackTitle
 */
export function resolveSeo(seo, fallbackTitle) {
  const s = seo && typeof seo === 'object' ? seo : {}
  return {
    title: String(s.title || s.titulo || fallbackTitle || 'Site'),
    description: String(s.description || s.descricao || ''),
  }
}
