import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { normalizeBlocks, resolveMenuLinks, resolveSeo } from './siteBlocks.js'

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

  it('resolveSeo usa fallback', () => {
    assert.deepEqual(resolveSeo(null, 'Paróquia'), {
      title: 'Paróquia',
      description: '',
    })
  })
})
