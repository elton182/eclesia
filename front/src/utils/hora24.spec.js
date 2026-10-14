import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { maskHora24, normalizarHora24 } from './hora24.js'

describe('hora24', () => {
  it('máscara junta hora e minuto enquanto digita', () => {
    assert.equal(maskHora24(''), '')
    assert.equal(maskHora24('1'), '1')
    assert.equal(maskHora24('19'), '19')
    assert.equal(maskHora24('193'), '19:3')
    assert.equal(maskHora24('1930'), '19:30')
    assert.equal(maskHora24('19:30'), '19:30')
    assert.equal(maskHora24('7:5'), '7:5')
    assert.equal(maskHora24('19h30'), '19:30')
    assert.equal(maskHora24('193045'), '19:30')
  })

  it('normaliza para HH:MM em 24h', () => {
    assert.equal(normalizarHora24('19:30'), '19:30')
    assert.equal(normalizarHora24('19:30:00'), '19:30')
    assert.equal(normalizarHora24('9'), '09:00')
    assert.equal(normalizarHora24('19'), '19:00')
    assert.equal(normalizarHora24('193'), '19:03')
    assert.equal(normalizarHora24('7:5'), '07:05')
    assert.equal(normalizarHora24(''), '')
    assert.equal(normalizarHora24('25'), '')
    assert.equal(normalizarHora24('19:60'), '')
    assert.equal(normalizarHora24('24:00'), '')
  })

  it('passo de hora cheia zera os minutos', () => {
    assert.equal(normalizarHora24('19:30', 60), '19:00')
    assert.equal(normalizarHora24('8', 60), '08:00')
  })
})
