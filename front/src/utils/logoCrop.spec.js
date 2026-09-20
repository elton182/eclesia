import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  LOGO_OUTPUT_SIZE,
  baseScaleToFit,
  computeCropRect,
  clampLogoOffsets,
} from './logoCrop.js'

describe('logoCrop', () => {
  it('baseScaleToFit usa o lado menor (contain)', () => {
    assert.equal(baseScaleToFit(1000, 500, 250), 0.25)
    assert.equal(baseScaleToFit(400, 800, 200), 0.25)
  })

  it('computeCropRect em zoom 1 cobre a imagem centralizada', () => {
    const r = computeCropRect({
      imgW: 800,
      imgH: 400,
      viewport: 200,
      zoom: 1,
      offsetX: 0,
      offsetY: 0,
    })
    // scale = min(200/800, 200/400) = 0.25 → drawn 200x100, letterbox vertical
    assert.ok(r.sw > 0 && r.sh > 0)
    assert.ok(r.sw <= 800)
    assert.ok(r.sh <= 400)
  })

  it('clampLogoOffsets zera pan quando imagem cabe no viewport', () => {
    const c = clampLogoOffsets({
      imgW: 100,
      imgH: 100,
      viewport: 200,
      zoom: 1,
      offsetX: 40,
      offsetY: -20,
    })
    assert.equal(c.offsetX, 0)
    assert.equal(c.offsetY, 0)
  })

  it('clampLogoOffsets limita pan no zoom alto', () => {
    const c = clampLogoOffsets({
      imgW: 400,
      imgH: 400,
      viewport: 200,
      zoom: 2,
      offsetX: 999,
      offsetY: -999,
    })
    // base=0.5, scale=1 → drawn 400, max offset (400-200)/2 = 100
    assert.equal(c.offsetX, 100)
    assert.equal(c.offsetY, -100)
  })

  it('output size padrão é 512', () => {
    assert.equal(LOGO_OUTPUT_SIZE, 512)
  })
})
