/**
 * Utilitários do CMS de blocos do site público.
 * Catálogo alinhado ao one-pager 1g (PLAN-002).
 */

export const BLOCK_LIBRARY = [
  {
    tipo: 'hero',
    label: 'Hero — boas-vindas',
    descricao: 'Eyebrow, título, texto, foto e CTAs.',
    grupo: 'One page',
    icone: 'fa-star',
    payload: {
      eyebrow: 'Bem-vindo à nossa casa',
      headline: '',
      texto: '',
      banner_url: '',
      cta_label: 'Horários das missas',
      cta_href: '#missas',
      cta2_label: 'Conheça as pastorais',
      cta2_href: '#pastorais',
    },
  },
  {
    tipo: 'missas_horarios',
    label: 'Horários das missas',
    descricao: 'Grade de horários da semana.',
    grupo: 'One page',
    icone: 'fa-clock',
    payload: {
      eyebrow: 'Missas',
      titulo: 'Horários da semana',
      texto: 'Confissões meia hora antes de cada celebração.',
      items: [
        { dia: 'Sábado', hora: '19h00', local: 'Igreja Matriz' },
        { dia: 'Domingo', hora: '08h00', local: 'Igreja Matriz' },
        { dia: 'Domingo', hora: '10h30', local: 'Comunidade' },
      ],
    },
  },
  {
    tipo: 'sobre_paroquia',
    label: 'Sobre a paróquia',
    descricao: 'História, foto e números.',
    grupo: 'One page',
    icone: 'fa-book-open',
    payload: {
      eyebrow: 'Sobre nós',
      titulo: '',
      texto: '',
      image_url: '',
      stats: [
        { valor: '3', rotulo: 'comunidades' },
        { valor: '12', rotulo: 'pastorais' },
        { valor: '1968', rotulo: 'fundação' },
      ],
    },
  },
  {
    tipo: 'pastorais_list',
    label: 'Pastorais e movimentos',
    descricao: 'Lista as pastorais publicadas no site.',
    grupo: 'One page',
    icone: 'fa-hands-praying',
    payload: { eyebrow: 'Pastorais e movimentos', titulo: 'Onde servir' },
  },
  {
    tipo: 'comunicados_list',
    label: 'Comunicados',
    descricao: 'Avisos publicados da paróquia.',
    grupo: 'One page',
    icone: 'fa-bullhorn',
    payload: {
      eyebrow: 'Comunicados',
      titulo: 'Avisos da paróquia',
      mostrar_nas_pastorais: true,
    },
  },
  {
    tipo: 'agenda_eventos',
    label: 'Agenda de eventos',
    descricao: 'Próximos eventos da paróquia.',
    grupo: 'One page',
    icone: 'fa-calendar',
    payload: {
      eyebrow: 'Agenda',
      titulo: 'Próximos eventos',
      items: [
        { dia: '14', mes: 'set', titulo: 'Festa patronal', info: 'A partir das 11h' },
      ],
    },
  },
  {
    tipo: 'equipe_clero',
    label: 'Clero e equipe',
    descricao: 'Retratos da equipe pastoral.',
    grupo: 'One page',
    icone: 'fa-users',
    payload: {
      eyebrow: 'Clero e equipe',
      titulo: 'Quem caminha com você',
      items: [
        { nome: '', papel: '', foto_url: '' },
      ],
    },
  },
  {
    tipo: 'contato_local',
    label: 'Localização e contato',
    descricao: 'Endereço, mapa e formulário.',
    grupo: 'One page',
    icone: 'fa-location-dot',
    payload: {
      eyebrow: 'Onde estamos',
      titulo: '',
      endereco: '',
      horario_secretaria: '',
      telefone: '',
      email: '',
      form_titulo: 'Fale conosco',
      form_texto: 'Pedidos de missa, batizados, casamentos ou uma conversa.',
      form_slug: '',
    },
  },
  {
    tipo: 'banner',
    label: 'Banner',
    descricao: 'Imagem larga entre seções.',
    grupo: 'Extras',
    icone: 'fa-image',
    payload: { image_url: '', alt: '' },
  },
  {
    tipo: 'richtext',
    label: 'Texto',
    descricao: 'Parágrafos, títulos e links.',
    grupo: 'Extras',
    icone: 'fa-align-left',
    payload: { html: '' },
  },
  {
    tipo: 'igrejas_list',
    label: 'Igrejas',
    descricao: 'Lista as igrejas publicadas no site.',
    grupo: 'Extras',
    icone: 'fa-church',
    payload: { titulo: 'Nossas igrejas' },
  },
  {
    tipo: 'form',
    label: 'Formulário',
    descricao: 'Incorpora um formulário do CMS.',
    grupo: 'Extras',
    icone: 'fa-envelope-open-text',
    payload: { form_slug: '', titulo: 'Fale conosco' },
  },
  {
    tipo: 'html',
    label: 'HTML avançado',
    descricao: 'Trecho de HTML livre.',
    grupo: 'Extras',
    icone: 'fa-code',
    payload: { html: '' },
  },
]

export const BLOCK_TYPES = BLOCK_LIBRARY.map((b) => b.tipo)

/** Ordem padrão do one-pager 1g. */
export const HOME_ONE_PAGER_TYPES = [
  'hero',
  'missas_horarios',
  'sobre_paroquia',
  'pastorais_list',
  'comunicados_list',
  'agenda_eventos',
  'equipe_clero',
  'contato_local',
]

const BLOCK_SUMMARY_LIMIT = 60

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

export function createBlock(tipo, ordem = 0) {
  return {
    tipo,
    ordem: Number(ordem) || 0,
    visivel: true,
    payload: structuredClone
      ? structuredClone(blockMeta(tipo).payload)
      : JSON.parse(JSON.stringify(blockMeta(tipo).payload)),
  }
}

/** Blocos iniciais da home one-pager (1g). */
export function createHomeOnePagerBlocks() {
  return HOME_ONE_PAGER_TYPES.map((tipo, index) => createBlock(tipo, index))
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

export function blockSummary(block) {
  const payload = block?.payload || {}
  const candidates = {
    hero: [payload.headline, payload.texto, payload.eyebrow],
    banner: [payload.alt, payload.image_url],
    richtext: [payload.html, payload.texto],
    html: [payload.html],
    form: [payload.form_slug ? `/${payload.form_slug}` : '', payload.titulo],
    missas_horarios: [payload.titulo, `${(payload.items || []).length} horários`],
    sobre_paroquia: [payload.titulo, payload.texto],
    agenda_eventos: [payload.titulo, `${(payload.items || []).length} eventos`],
    equipe_clero: [payload.titulo, `${(payload.items || []).length} pessoas`],
    contato_local: [payload.titulo, payload.endereco],
    pastorais_list: [payload.titulo],
    comunicados_list: [payload.titulo],
    igrejas_list: [payload.titulo],
  }[block?.tipo] || [payload.titulo]

  for (const candidate of candidates) {
    const text = truncate(candidate)
    if (text) return text
  }
  return 'Sem conteúdo'
}

export function reindexBlocks(blocks) {
  if (!Array.isArray(blocks)) return []
  return blocks.map((block, index) => ({ ...block, ordem: index }))
}

export function normalizeBlocks(blocks) {
  if (!Array.isArray(blocks)) return []
  return blocks
    .filter((b) => b && typeof b === 'object' && b.visivel !== false)
    .slice()
    .sort((a, b) => (Number(a.ordem) || 0) - (Number(b.ordem) || 0))
}

export function createMenuItem() {
  return { label: '', tipo: 'pagina', slug: '', href: '' }
}

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

export function resolveMenuLinks(menu, tenantSlug) {
  const base = `/site/${tenantSlug}`
  if (!Array.isArray(menu) || menu.length === 0) {
    return [
      { label: 'Missas', href: '#missas' },
      { label: 'Sobre', href: '#sobre' },
      { label: 'Pastorais', href: '#pastorais' },
      { label: 'Comunicados', href: '#comunicados' },
      { label: 'Agenda', href: '#agenda' },
    ]
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

export function resolveSeo(seo, fallbackTitle) {
  const s = seo && typeof seo === 'object' ? seo : {}
  return {
    title: String(s.title || s.titulo || fallbackTitle || 'Site'),
    description: String(s.description || s.descricao || ''),
  }
}

/** Âncora de seção no one-pager. */
export function blockAnchor(tipo) {
  const map = {
    missas_horarios: 'missas',
    sobre_paroquia: 'sobre',
    pastorais_list: 'pastorais',
    comunicados_list: 'comunicados',
    agenda_eventos: 'agenda',
    equipe_clero: 'equipe',
    contato_local: 'contato',
  }
  return map[tipo] || null
}
