import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('public site branding (SPEC-011)', () => {
  it('PublicSiteView aplica CSS vars e restaura branding do app ao sair', () => {
    const src = readFileSync(join(root, 'views/site/PublicSiteView.vue'), 'utf8')
    assert.match(src, /applyBrandCores/)
    assert.match(src, /brandingStore\.reapply/)
    assert.match(src, /var\(--color-primary/)
    assert.match(src, /var\(--color-ink\)/)
    assert.doesNotMatch(src, /#4E1220|#6B1C2B|#2A1418/)
    assert.doesNotMatch(src, /brandSub|subtitulo/)
  })

  it('SiteBlockRenderer hero usa var(--color-primary)', () => {
    const src = readFileSync(join(root, 'components/site/SiteBlockRenderer.vue'), 'utf8')
    assert.match(src, /background: var\(--color-primary\)/)
    assert.doesNotMatch(src, /background: #4E1220/)
  })
})
