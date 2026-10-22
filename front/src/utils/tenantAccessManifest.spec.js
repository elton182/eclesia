import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  DEFAULT_MANIFEST_HREF,
  tenantAccessManifestPath,
  tenantAccessStartUrl,
  applyManifestHref,
  applyTenantAccessManifest,
  restoreDefaultManifest,
} from './tenantAccessManifest.js'

describe('tenantAccessManifestPath / startUrl', () => {
  it('monta path do manifest e start_url com slug', () => {
    assert.equal(tenantAccessManifestPath('  sao-jose  '), '/site/sao-jose/manifest.webmanifest')
    assert.equal(tenantAccessStartUrl('sao-jose'), '/entrar?tenant=sao-jose')
  })

  it('codifica slug na URL', () => {
    assert.equal(
      tenantAccessManifestPath('paroquia x'),
      '/site/paroquia%20x/manifest.webmanifest',
    )
    assert.equal(tenantAccessStartUrl('a/b'), '/entrar?tenant=a%2Fb')
  })

  it('slug vazio', () => {
    assert.equal(tenantAccessManifestPath(''), '')
    assert.equal(tenantAccessStartUrl(''), '/entrar')
  })
})

describe('applyManifestHref', () => {
  /** @type {{ head: { appendChild: Function }, querySelector: Function, createElement: Function, _link: any }} */
  let doc

  beforeEach(() => {
    const link = {
      attrs: /** @type {Record<string, string>} */ ({}),
      setAttribute(k, v) {
        this.attrs[k] = String(v)
      },
      getAttribute(k) {
        return this.attrs[k] || null
      },
    }
    doc = {
      _link: null,
      head: {
        appendChild(el) {
          doc._link = el
        },
      },
      querySelector(sel) {
        if (sel === 'link[rel="manifest"]') return doc._link
        return null
      },
      createElement(tag) {
        assert.equal(tag, 'link')
        return link
      },
    }
  })

  it('cria link rel=manifest quando ausente', () => {
    applyManifestHref('/site/demo/manifest.webmanifest', doc)
    assert.ok(doc._link)
    assert.equal(doc._link.getAttribute('rel'), 'manifest')
    assert.equal(doc._link.getAttribute('href'), '/site/demo/manifest.webmanifest')
  })

  it('atualiza href existente e restaura o default', () => {
    applyTenantAccessManifest('demo', doc)
    assert.equal(doc._link.getAttribute('href'), '/site/demo/manifest.webmanifest')
    restoreDefaultManifest(doc)
    assert.equal(doc._link.getAttribute('href'), DEFAULT_MANIFEST_HREF)
  })
})
