import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { applyDocumentSeo, resolveSeo } from './siteBlocks.js'
import { extractIndexAssets, injectSpaAssets } from '../../vite.site-html-plugin.js'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('site SEO document (SPEC-005)', () => {
  it('applyDocumentSeo define title e meta description', () => {
    const doc = {
      title: '',
      head: {
        appendChild(el) {
          this._kids = this._kids || []
          this._kids.push(el)
        },
      },
      querySelector() {
        return null
      },
      createElement(tag) {
        const attrs = {}
        return {
          tag,
          setAttribute(k, v) {
            attrs[k] = v
          },
          getAttribute(k) {
            return attrs[k]
          },
        }
      },
    }
    applyDocumentSeo({ title: 'Paróquia X', description: 'Comunidade' }, doc)
    assert.equal(doc.title, 'Paróquia X')
    assert.equal(doc.head._kids.length, 1)
    assert.equal(doc.head._kids[0].getAttribute('name'), 'description')
    assert.equal(doc.head._kids[0].getAttribute('content'), 'Comunidade')
  })

  it('resolveSeo e PublicSiteView usam applyDocumentSeo', () => {
    assert.deepEqual(resolveSeo({ title: 'A', description: 'B' }, 'Z'), {
      title: 'A',
      description: 'B',
    })
    const src = readFileSync(join(root, 'views/site/PublicSiteView.vue'), 'utf8')
    assert.match(src, /applyDocumentSeo/)
    assert.doesNotMatch(src, /document\.title\s*=\s*seo\.title/)
  })

  it('vite plugin injeta assets SPA no HTML SEO', () => {
    const base =
      '<!DOCTYPE html><html><head><title>T</title></head><body><div id="app"><main>Oi</main></div></body></html>'
    const { links, scripts } = extractIndexAssets(
      '<html><head><link rel="stylesheet" href="/assets/x.css"></head><body><script type="module" src="/assets/y.js"></script></body></html>',
    )
    assert.equal(links.length, 1)
    assert.equal(scripts.length, 1)
    const out = injectSpaAssets(base, { links, scripts })
    assert.match(out, /rel="stylesheet"/)
    assert.match(out, /type="module"/)
    assert.match(out, /<main>Oi<\/main>/)
  })

  it('vite.config registra plugin e denylist /site/', () => {
    const cfg = readFileSync(join(root, '..', 'vite.config.js'), 'utf8')
    assert.match(cfg, /siteHtmlPrerenderPlugin/)
    assert.match(cfg, /\/\^\\\/site\\\//)
  })
})
