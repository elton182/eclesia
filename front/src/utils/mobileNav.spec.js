import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { adminAsideClasses, asideNavClasses } from './mobileNav.js'

describe('asideNavClasses', () => {
  it('esconde o drawer no mobile quando fechado e mantém no fluxo no md+', () => {
    const cls = asideNavClasses({ open: false })
    assert.match(cls, /-translate-x-full/)
    assert.match(cls, /md:translate-x-0/)
    assert.match(cls, /md:relative/)
    assert.match(cls, /fixed/)
  })

  it('mostra o drawer quando aberto', () => {
    const cls = asideNavClasses({ open: true })
    assert.ok(cls.split(/\s+/).includes('translate-x-0'))
    assert.ok(!cls.split(/\s+/).includes('-translate-x-full'))
  })
})

describe('adminAsideClasses', () => {
  it('no mobile fechado fica fora da tela; no desktop vira rail estreito', () => {
    const cls = adminAsideClasses({ open: false })
    assert.match(cls, /-translate-x-full/)
    assert.match(cls, /md:w-20/)
    assert.match(cls, /md:translate-x-0/)
  })

  it('aberto ocupa largura completa do menu', () => {
    const cls = adminAsideClasses({ open: true })
    assert.match(cls, /w-60/)
    assert.match(cls, /translate-x-0/)
  })
})
