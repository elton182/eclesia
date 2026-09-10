/**
 * Utilitários do CMS de blocos do site público.
 */

/**
 * Catálogo de blocos disponíveis no construtor.
 * `payload` descreve os campos padrão de cada tipo.
 */
export const BLOCK_LIBRARY = [
  {
    tipo: 'hero',
    label: 'Destaque',
    descricao: 'Imagem de fundo com título, texto e botão de ação.',
    grupo: 'Estrutura',
    icone: 'fa-star',
    payload: { headline: '', texto: '', banner_url: '', cta_label: '', cta_href: '' },
  },
  {
    tipo: 'banner',
    label: 'Banner',
    descricao: 'Imagem larga entre seções.',
    grupo: 'Estrutura',
    icone: 'fa-image',
    payload: { image_url: '', alt: '' },
  },
  {
    tipo: 'richtext',
    label: 'Texto',
    descricao: 'Parágrafos, títulos e links da página.',
    grupo: 'Conteúdo',
    icone: 'fa-align-left',
    payload: { html: '' },
  },
  {
    tipo: 'igrejas_list',
    label: 'Igrejas',
    descricao: 'Lista as igrejas publicadas no site.',
    grupo: 'Listagens',
    icone: 'fa-church',
    payload: { titulo: 'Nossas igrejas' },
  },
  {
    tipo: 'comunicados_list',
    label: 'Comunicados',
    descricao: 'Lista os comunicados publicados.',
    grupo: 'Listagens',
    icone: 'fa-bullhorn',
    payload: { titulo: 'Comunicados' },
  },
  {
    tipo: 'pastorais_list',
    label: 'Pastorais',
    descricao: 'Lista as pastorais publicadas no site.',
    grupo: 'Listagens',
    icone: 'fa-hands-praying',
    payload: { titulo: 'Pastorais' },
  },
  {
    tipo: 'form',
    label: 'Formulário',
    descricao: 'Incorpora um formulário criado na aba Formulários.',
    grupo: 'Interação',
    icone: 'fa-envelope-open-text',
    payload: { form_slug: '', titulo: 'Fale conosco' },
  },
  {
    tipo: 'html',
    label: 'HTML avançado',
    descricao: 'Trecho de HTML para casos não cobertos pelos demais blocos.',
    grupo: 'Avançado',
    icone: 'fa-code',
    payload: { html: '' },
  },
]

export const BLOCK_TYPES = BLOCK_LIBRARY.map((b) => b.tipo)

const BLOCK_SUMMARY_LIMIT = 60

/**
 * @param {string} tipo
 * @returns {{ tipo: string, label: string, descricao: string, grupo: string, icone: string, payload: Record<string, unknown> }}
 */
export function blockMeta(tipo) {
  const found = BLOCK_LIBRARY.find((b) => b.tipo === tipo)
  if (found) return found
  return {
    tipo: String(tipo || ''),
    label: String(tipo || 'Bloco'),
    descricao: '',
    grupo: 'Outros',
    icone: 'fa-cube',
    payload: {},
  }
}

/**
 * @param {string} tipo
 * @param {number} ordem
 */
export function createBlock(tipo, ordem = 0) {
  return {
    tipo,
    ordem: Number(ordem) || 0,
    visivel: true,
    payload: { ...blockMeta(tipo).payload },
  }
}

function stripHtml(value) {
  return String(value ?? '')
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

function truncate(value) {
  const text = stripHtml(value)
  if (!text) return ''
  return text.length > BLOCK_SUMMARY_LIMIT
    ? `${text.slice(0, BLOCK_SUMMARY_LIMIT)}…`
    : text
}

/**
 * Texto curto exibido na lista de blocos do construtor.
 * @param {{ tipo?: string, payload?: Record<string, unknown> }} block
 */
export function blockSummary(block) {
  const payload = block?.payload || {}
  const candidates = {
    hero: [payload.headline, payload.texto],
    banner: [payload.alt, payload.image_url],
    richtext: [payload.html, payload.texto],
    html: [payload.html],
    form: [payload.form_slug ? `/${payload.form_slug}` : '', payload.titulo],
  }[block?.tipo] || [payload.titulo]

  for (const candidate of candidates) {
    const text = truncate(candidate)
    if (text) return text
  }
  return 'Sem conteúdo'
}

/**
 * Reaplica `ordem` conforme a posição na lista.
 * @param {Array<Record<string, unknown>>} blocks
 */
export function reindexBlocks(blocks) {
  if (!Array.isArray(blocks)) return []
  return blocks.map((block, index) => ({ ...block, ordem: index }))
}

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
 * Item vazio do editor visual de menu.
 */
export function createMenuItem() {
  return { label: '', tipo: 'pagina', slug: '', href: '' }
}

/**
 * Converte o menu salvo (`slug` ou `href`) para o formato do editor visual.
 * @param {unknown} menu
 */
export function normalizeMenuItems(menu) {
  if (!Array.isArray(menu)) return []
  return menu
    .filter((item) => item && typeof item === 'object')
    .map((item) => {
      const isLink = !!item.href && !item.slug
      return {
        label: String(item.label || ''),
        tipo: isLink ? 'link' : 'pagina',
        slug: isLink ? '' : String(item.slug || ''),
        href: isLink ? String(item.href || '') : '',
      }
    })
}

/**
 * Converte itens do editor visual para o formato salvo em `site_settings.menu`.
 * @param {Array<{ label?: string, tipo?: string, slug?: string, href?: string }>} items
 */
export function menuItemsToPayload(items) {
  if (!Array.isArray(items)) return []
  return items
    .map((item) => {
      const label = String(item?.label || '').trim()
      if (!label) return null
      if (item?.tipo === 'link') {
        const href = String(item.href || '').trim()
        return href ? { label, href } : null
      }
      const slug = String(item?.slug || '').trim()
      return slug ? { label, slug } : null
    })
    .filter((item) => item !== null)
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
