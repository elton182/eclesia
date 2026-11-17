import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  SAFE_AREA_CLASSES,
  moduleAsideSafePadClass,
  moduleHeaderSafeClass,
} from './safeArea.js'

const mainCss = readFileSync(
  join(dirname(fileURLToPath(import.meta.url)), '../assets/main.css'),
  'utf8',
)

describe('SAFE_AREA_CLASSES', () => {
  it('expõe tokens estáveis usados pelos layouts', () => {
    assert.equal(SAFE_AREA_CLASSES.header14, 'safe-header-14')
    assert.equal(SAFE_AREA_CLASSES.header16, 'safe-header-16')
    assert.equal(SAFE_AREA_CLASSES.padTop, 'safe-pt')
    assert.equal(SAFE_AREA_CLASSES.minHeightApp, 'min-h-app')
    assert.equal(SAFE_AREA_CLASSES.liderMainPadBottom, 'safe-lider-main-pb')
    assert.equal(SAFE_AREA_CLASSES.offsetTop16, 'safe-offset-top-16')
  })

  it('está definido em main.css com env(safe-area-inset-*)', () => {
    for (const cls of Object.values(SAFE_AREA_CLASSES)) {
      assert.match(mainCss, new RegExp(`\\.${cls}\\b`))
    }
    assert.match(mainCss, /env\(safe-area-inset-top/)
    assert.match(mainCss, /env\(safe-area-inset-bottom/)
    assert.match(mainCss, /100dvh/)
  })
})

describe('moduleHeaderSafeClass', () => {
  it('aplica safe-header-14 quando não há banner no topo', () => {
    assert.equal(moduleHeaderSafeClass(false), 'safe-header-14')
  })

  it('não duplica inset quando o banner já ocupa a bandeja', () => {
    assert.equal(moduleHeaderSafeClass(true), '')
  })
})

describe('moduleAsideSafePadClass', () => {
  it('usa aside safe-pt sem banner', () => {
    const cls = moduleAsideSafePadClass(false)
    assert.match(cls, /safe-aside-pt/)
    assert.match(cls, /px-3\.5/)
    assert.match(cls, /pb-5/)
    assert.ok(!cls.split(/\s+/).includes('py-5'))
  })

  it('usa py-5 uniforme quando o banner já deu o inset', () => {
    assert.equal(moduleAsideSafePadClass(true), 'px-3.5 py-5')
  })
})
