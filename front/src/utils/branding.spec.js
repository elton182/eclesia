import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  DEFAULT_BRAND_COLORS,
  isHexColor,
  normalizeBrandCores,
  applyBrandCores,
  resetBrandCores,
} from './branding.js'

describe('branding', () => {
  it('valida hex', () => {
    assert.equal(isHexColor('#4E1220'), true)
    assert.equal(isHexColor('#fff'), false)
    assert.equal(isHexColor('vermelho'), false)
  })

  it('normaliza com fallback vinho (SPEC-014)', () => {
    assert.deepEqual(normalizeBrandCores(null), {
      primary: DEFAULT_BRAND_COLORS.primary,
      secondary: DEFAULT_BRAND_COLORS.secondary,
      text: DEFAULT_BRAND_COLORS.text,
      text_muted: DEFAULT_BRAND_COLORS.text_muted,
      on_primary: DEFAULT_BRAND_COLORS.on_primary,
    })
    assert.deepEqual(normalizeBrandCores({ primary: '#112233' }), {
      primary: '#112233',
      secondary: DEFAULT_BRAND_COLORS.secondary,
      text: DEFAULT_BRAND_COLORS.text,
      text_muted: DEFAULT_BRAND_COLORS.text_muted,
      on_primary: DEFAULT_BRAND_COLORS.on_primary,
    })
  })

  it('aceita accent do site como on_primary', () => {
    const n = normalizeBrandCores({
      primary: '#111111',
      secondary: '#222222',
      accent: '#FEDCBA',
    })
    assert.equal(n.on_primary, '#FEDCBA')
  })

  it('aplica CSS vars e derivados color-mix', () => {
    const props = new Map()
    const doc = {
      documentElement: {
        style: {
          setProperty: (k, v) => props.set(k, v),
          removeProperty: (k) => props.delete(k),
        },
      },
    }
    applyBrandCores(
      {
        primary: '#ABCDEF',
        secondary: '#123456',
        text: '#010101',
        text_muted: '#888888',
        on_primary: '#FFFFFF',
      },
      doc,
    )
    assert.equal(props.get('--color-primary'), '#ABCDEF')
    assert.equal(props.get('--color-accent'), '#123456')
    assert.equal(props.get('--color-ink'), '#010101')
    assert.equal(props.get('--color-muted'), '#888888')
    assert.equal(props.get('--color-on-primary'), '#FFFFFF')
    assert.match(props.get('--color-primary-soft'), /color-mix/)
    assert.match(props.get('--color-primary-hover'), /color-mix/)
    assert.match(props.get('--color-primary-dark'), /color-mix/)

    resetBrandCores(doc)
    assert.equal(props.size, 0)
  })
})
