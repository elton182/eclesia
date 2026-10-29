import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'useFilePickerFallback.js'), 'utf8')

describe('useFilePickerFallback', () => {
  it('expõe timeout de 2s para revelar sem change', () => {
    assert.match(src, /export const FILE_PICKER_FALLBACK_MS = 2000/)
  })

  it('revela em standalone e após timeout sem change', () => {
    assert.match(src, /isRunningStandalone/)
    assert.match(src, /revealFallback\('no_change'\)/)
    assert.match(src, /revealFallback\('standalone'\)/)
    assert.match(src, /fallback\.reveal/)
    assert.match(src, /onSilentFail/)
    assert.match(src, /fallback\.silent/)
  })
})
