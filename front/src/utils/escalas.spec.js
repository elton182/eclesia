import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  toDayKey,
  groupOcorrenciasByDay,
  buildMonthGrid,
  equipesJaEscalado,
  toDatetimeLocal,
} from './escalas.js'

describe('escalas utils', () => {
  it('toDayKey formata data local', () => {
    assert.equal(toDayKey('2026-09-20T10:00:00-03:00'), '2026-09-20')
    assert.equal(toDayKey(null), '')
  })

  it('groupOcorrenciasByDay agrupa por dia', () => {
    const map = groupOcorrenciasByDay([
      { id: '1', inicia_em: '2026-09-20T10:00:00-03:00' },
      { id: '2', inicia_em: '2026-09-20T18:00:00-03:00' },
      { id: '3', inicia_em: '2026-09-21T10:00:00-03:00' },
    ])
    assert.equal(map.get('2026-09-20').length, 2)
    assert.equal(map.get('2026-09-21').length, 1)
  })

  it('buildMonthGrid tem semanas de 7 dias', () => {
    const weeks = buildMonthGrid(2026, 8) // setembro 2026
    assert.ok(weeks.length >= 4)
    assert.equal(weeks[0].length, 7)
    const flat = weeks.flat().filter(Boolean)
    assert.equal(flat.length, 30)
  })

  it('equipesJaEscalado lista ministérios informativos', () => {
    const atr = [
      { escala_equipe_id: 'e1', equipe_nome: 'Leitura', casal_id: 'c1' },
      { escala_equipe_id: 'e2', equipe_nome: 'Acolhida', casal_id: 'c1' },
      { escala_equipe_id: 'e1', equipe_nome: 'Leitura', pessoa_id: 'p1' },
    ]
    assert.deepEqual(equipesJaEscalado(atr, { casalId: 'c1' }), ['Leitura', 'Acolhida'])
    assert.deepEqual(equipesJaEscalado(atr, { pessoaId: 'p1' }), ['Leitura'])
  })

  it('toDatetimeLocal monta valor para input', () => {
    assert.equal(toDatetimeLocal('2026-09-20'), '2026-09-20T10:00')
    assert.equal(toDatetimeLocal('2026-09-20', '18:30'), '2026-09-20T18:30')
    assert.equal(toDatetimeLocal(''), '')
  })
})
