import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { horas24, minutos24, splitHora24, joinHora24 } from './hora24.js'

describe('hora24', () => {
  it('horas de 00 a 23', () => {
    const horas = horas24()
    assert.equal(horas.length, 24)
    assert.equal(horas[0], '00')
    assert.equal(horas[23], '23')
    assert.equal(horas.includes('24'), false)
  })

  it('minutos no passo, incluindo o atual fora da grade', () => {
    assert.deepEqual(minutos24(15), ['00', '15', '30', '45'])
    assert.deepEqual(minutos24(60), ['00'])
    assert.deepEqual(minutos24(5, '07'), ['00', '05', '07', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'])
    assert.deepEqual(minutos24(5, '00')[0], '00')
    assert.equal(minutos24(1).length, 60)
    assert.equal(minutos24(0).length, 12)
  })

  it('separa e junta HH:MM', () => {
    assert.deepEqual(splitHora24('19:30'), { hora: '19', minuto: '30' })
    assert.deepEqual(splitHora24('19:30:00'), { hora: '19', minuto: '30' })
    assert.deepEqual(splitHora24(''), { hora: '', minuto: '' })
    assert.deepEqual(splitHora24('7:30'), { hora: '', minuto: '' })
    assert.deepEqual(splitHora24('25:00'), { hora: '', minuto: '' })
    assert.equal(joinHora24('19', '30'), '19:30')
    assert.equal(joinHora24('19', ''), '19:00')
    assert.equal(joinHora24('', '30'), '')
    assert.equal(joinHora24('25', '00'), '')
  })
})
