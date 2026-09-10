import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  BLOCK_LIBRARY,
  BLOCK_TYPES,
  blockMeta,
  blockSummary,
  createBlock,
  createMenuItem,
  menuItemsToPayload,
  normalizeBlocks,
  normalizeMenuItems,
  reindexBlocks,
  resolveMenuLinks,
  resolveSeo,
} from './siteBlocks.js'

describe('siteBlocks', () => {
  it('normalizeBlocks filtra invisíveis e ordena', () => {
    const result = normalizeBlocks([
      { tipo: 'richtext', ordem: 2, visivel: true, payload: {} },
      { tipo: 'hero', ordem: 0, visivel: false, payload: {} },
      { tipo: 'banner', ordem: 1, visivel: true, payload: {} },
    ])
    assert.equal(result.length, 2)
    assert.equal(result[0].tipo, 'banner')
    assert.equal(result[1].tipo, 'richtext')
  })

  it('resolveMenuLinks usa home e slug', () => {
    const links = resolveMenuLinks(
      [
        { label: 'Início', slug: 'home' },
        { label: 'Sobre', slug: 'sobre' },
      ],
      'demo',
    )
    assert.deepEqual(links, [
      { label: 'Início', href: '/site/demo' },
      { label: 'Sobre', href: '/site/demo/sobre' },
    ])
  })

  it('resolveMenuLinks sem menu devolve âncoras do one-pager', () => {
    const links = resolveMenuLinks([], 'demo')
    assert.ok(links.some((l) => l.href === '#missas'))
    assert.ok(links.some((l) => l.label === 'Pastorais'))
  })

  it('createHomeOnePagerBlocks monta seções na ordem 1g', async () => {
    const { createHomeOnePagerBlocks, HOME_ONE_PAGER_TYPES } = await import('./siteBlocks.js')
    const blocks = createHomeOnePagerBlocks()
    assert.equal(blocks.length, HOME_ONE_PAGER_TYPES.length)
    assert.equal(blocks[0].tipo, 'hero')
    assert.equal(blocks[blocks.length - 1].tipo, 'contato_local')
  })

  it('resolveSeo usa fallback', () => {
    assert.deepEqual(resolveSeo(null, 'Paróquia'), {
      title: 'Paróquia',
      description: '',
    })
  })
})

describe('siteBlocks — biblioteca de blocos', () => {
  it('BLOCK_TYPES deriva da biblioteca e cobre todos os tipos da spec', () => {
    assert.deepEqual(
      BLOCK_TYPES,
      BLOCK_LIBRARY.map((b) => b.tipo),
    )
    for (const tipo of [
      'hero',
      'banner',
      'richtext',
      'igrejas_list',
      'comunicados_list',
      'pastorais_list',
      'form',
      'html',
    ]) {
      assert.ok(BLOCK_TYPES.includes(tipo), `tipo ausente: ${tipo}`)
    }
  })

  it('blockMeta devolve rótulo e descrição do tipo conhecido', () => {
    const meta = blockMeta('hero')
    assert.equal(meta.tipo, 'hero')
    assert.ok(meta.label.length > 0)
    assert.ok(meta.descricao.length > 0)
    assert.ok(meta.grupo.length > 0)
  })

  it('blockMeta tem fallback para tipo desconhecido', () => {
    const meta = blockMeta('inexistente')
    assert.equal(meta.tipo, 'inexistente')
    assert.equal(meta.label, 'inexistente')
  })

  it('createBlock gera payload padrão por tipo', () => {
    const hero = createBlock('hero', 0)
    assert.equal(hero.tipo, 'hero')
    assert.equal(hero.ordem, 0)
    assert.equal(hero.visivel, true)
    assert.ok('headline' in hero.payload)

    const texto = createBlock('richtext', 3)
    assert.equal(texto.ordem, 3)
    assert.ok('html' in texto.payload)
  })

  it('createBlock não compartilha o payload entre instâncias', () => {
    const a = createBlock('hero', 0)
    const b = createBlock('hero', 1)
    a.payload.headline = 'A'
    assert.notEqual(b.payload.headline, 'A')
  })

  it('blockSummary resume o conteúdo do bloco', () => {
    assert.equal(
      blockSummary({ tipo: 'hero', payload: { headline: 'Bem-vindos' } }),
      'Bem-vindos',
    )
    assert.equal(
      blockSummary({ tipo: 'form', payload: { form_slug: 'contato' } }),
      '/contato',
    )
    assert.equal(
      blockSummary({ tipo: 'richtext', payload: { html: '<p>Olá <b>mundo</b></p>' } }),
      'Olá mundo',
    )
    assert.equal(blockSummary({ tipo: 'banner', payload: {} }), 'Sem conteúdo')
  })

  it('blockSummary trunca textos longos', () => {
    const summary = blockSummary({ tipo: 'richtext', payload: { html: 'a'.repeat(120) } })
    assert.ok(summary.length <= 61)
    assert.ok(summary.endsWith('…'))
  })

  it('reindexBlocks aplica ordem sequencial pela posição', () => {
    const blocks = reindexBlocks([
      { tipo: 'hero', ordem: 9, payload: {} },
      { tipo: 'richtext', ordem: 4, payload: {} },
    ])
    assert.deepEqual(
      blocks.map((b) => b.ordem),
      [0, 1],
    )
    assert.equal(blocks[0].tipo, 'hero')
  })
})

describe('siteBlocks — editor de menu', () => {
  it('normalizeMenuItems converte itens de página e link externo', () => {
    const items = normalizeMenuItems([
      { label: 'Início', slug: 'home' },
      { label: 'Doar', href: 'https://exemplo.org/doar' },
    ])
    assert.equal(items.length, 2)
    assert.deepEqual(items[0], { label: 'Início', tipo: 'pagina', slug: 'home', href: '' })
    assert.deepEqual(items[1], {
      label: 'Doar',
      tipo: 'link',
      slug: '',
      href: 'https://exemplo.org/doar',
    })
  })

  it('normalizeMenuItems devolve lista vazia para valor inválido', () => {
    assert.deepEqual(normalizeMenuItems(null), [])
    assert.deepEqual(normalizeMenuItems('x'), [])
  })

  it('createMenuItem cria item de página vazio', () => {
    assert.deepEqual(createMenuItem(), { label: '', tipo: 'pagina', slug: '', href: '' })
  })

  it('menuItemsToPayload descarta itens incompletos e limpa campos do outro tipo', () => {
    const payload = menuItemsToPayload([
      { label: 'Início', tipo: 'pagina', slug: 'home', href: 'https://ignorado' },
      { label: 'Doar', tipo: 'link', href: 'https://exemplo.org', slug: 'ignorado' },
      { label: '', tipo: 'pagina', slug: 'sem-label', href: '' },
      { label: 'Sem destino', tipo: 'link', slug: '', href: '' },
    ])
    assert.deepEqual(payload, [
      { label: 'Início', slug: 'home' },
      { label: 'Doar', href: 'https://exemplo.org' },
    ])
  })

  it('menuItemsToPayload preserva ida e volta com resolveMenuLinks', () => {
    const payload = menuItemsToPayload(
      normalizeMenuItems([{ label: 'Sobre', slug: 'sobre' }]),
    )
    assert.deepEqual(resolveMenuLinks(payload, 'demo'), [
      { label: 'Sobre', href: '/site/demo/sobre' },
    ])
  })
})
