import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  DEFAULT_MANIFEST_HREF,
  SITE_INSTALL_SEGMENT,
  tenantAccessManifestPath,
  tenantAccessInstallPath,
  tenantAccessStartUrl,
  normalizeManifestPath,
  applyManifestHref,
  ensureTenantAccessManifest,
  applyTenantAccessManifest,
  restoreDefaultManifest,
  detectInstallPlatform,
} from './tenantAccessManifest.js'

describe('tenantAccessManifestPath / startUrl / installPath', () => {
  it('monta paths com slug', () => {
    assert.equal(tenantAccessManifestPath('  sao-jose  '), '/site/sao-jose/manifest.webmanifest')
    assert.equal(tenantAccessStartUrl('sao-jose'), '/entrar?tenant=sao-jose')
    assert.equal(tenantAccessInstallPath('sao-jose'), `/site/sao-jose/${SITE_INSTALL_SEGMENT}`)
  })

  it('codifica slug na URL', () => {
    assert.equal(
      tenantAccessManifestPath('paroquia x'),
      '/site/paroquia%20x/manifest.webmanifest',
    )
    assert.equal(tenantAccessStartUrl('a/b'), '/entrar?tenant=a%2Fb')
    assert.equal(tenantAccessInstallPath('a/b'), '/site/a%2Fb/instalar')
  })

  it('slug vazio', () => {
    assert.equal(tenantAccessManifestPath(''), '')
    assert.equal(tenantAccessStartUrl(''), '/entrar')
    assert.equal(tenantAccessInstallPath(''), '/')
  })
})

describe('normalizeManifestPath', () => {
  it('extrai pathname de URL absoluta ou relativa', () => {
    assert.equal(
      normalizeManifestPath('https://x.test/site/demo/manifest.webmanifest'),
      '/site/demo/manifest.webmanifest',
    )
    assert.equal(normalizeManifestPath('/manifest.webmanifest'), '/manifest.webmanifest')
  })
})

describe('ensureTenantAccessManifest', () => {
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

  it('cria link e reporta changed', () => {
    const first = ensureTenantAccessManifest('demo', doc)
    assert.equal(first.changed, true)
    assert.equal(first.href, '/site/demo/manifest.webmanifest')
    const second = ensureTenantAccessManifest('demo', doc)
    assert.equal(second.changed, false)
  })

  it('atualiza href existente e restaura o default', () => {
    applyTenantAccessManifest('demo', doc)
    assert.equal(doc._link.getAttribute('href'), '/site/demo/manifest.webmanifest')
    restoreDefaultManifest(doc)
    assert.equal(doc._link.getAttribute('href'), DEFAULT_MANIFEST_HREF)
  })

  it('applyManifestHref cria link quando ausente', () => {
    applyManifestHref('/site/demo/manifest.webmanifest', doc)
    assert.ok(doc._link)
    assert.equal(doc._link.getAttribute('rel'), 'manifest')
  })
})

describe('detectInstallPlatform', () => {
  it('detecta iOS, Android e desktop', () => {
    assert.equal(
      detectInstallPlatform({ userAgent: 'iPhone', platform: 'iPhone', maxTouchPoints: 5 }),
      'ios',
    )
    assert.equal(
      detectInstallPlatform({ userAgent: 'Android 14', platform: 'Linux', maxTouchPoints: 5 }),
      'android',
    )
    assert.equal(
      detectInstallPlatform({ userAgent: 'Mozilla/5.0 Chrome', platform: 'Win32', maxTouchPoints: 0 }),
      'desktop',
    )
  })
})
