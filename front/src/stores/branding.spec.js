import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('branding pós-login (SPEC-014)', () => {
  it('login aplica branding da resposta e chama ensureLoaded', () => {
    const src = readFileSync(join(root, 'stores/auth.js'), 'utf8')
    assert.match(src, /applyFromPayload\(response\.data\.branding\)/)
    assert.match(src, /ensureLoaded\(\)/)
    assert.match(src, /useBrandingStore\(\)\.reset\(\)/)
  })

  it('store não marca loaded quando payload é null', () => {
    const src = readFileSync(join(root, 'stores/branding.js'), 'utf8')
    assert.match(src, /if \(payload == null\)/)
    assert.match(src, /async function ensureLoaded/)
    assert.match(src, /function reapply/)
  })

  it('router garante branding mesmo com sessão já autenticada', () => {
    const src = readFileSync(join(root, 'router/index.js'), 'utf8')
    assert.match(src, /branding\.ensureLoaded/)
    assert.match(src, /useBrandingStore/)
  })
})
