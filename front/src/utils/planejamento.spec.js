import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelAnoStatus,
  labelEventoStatus,
  styleEventoStatus,
  groupEventosByDay,
  moveEventoToDay,
  nextAnoStatuses,
  toDayKeyLocal,
} from './planejamento.js'

describe('planejamento', () => {
  it('labels e estilos de status', () => {
    assert.equal(labelAnoStatus('coleta'), 'Coleta')
    assert.equal(labelEventoStatus('em_revisao'), 'Em revisão')
    assert.equal(styleEventoStatus('aprovado').fg, '#1f6b3a')
    assert.equal(styleEventoStatus('desconhecido').fg, styleEventoStatus('proposta').fg)
  })

  it('groupEventosByDay agrupa por data local', () => {
    const map = groupEventosByDay([
      { id: '1', data_inicio: '2026-03-10T10:00:00' },
      { id: '2', data_inicio: '2026-03-10T15:00:00' },
      { id: '3', data_inicio: '2026-03-11T09:00:00' },
    ])
    assert.equal(map.get(toDayKeyLocal('2026-03-10T10:00:00'))?.length, 2)
    assert.equal(map.get(toDayKeyLocal('2026-03-11T09:00:00'))?.length, 1)
  })

  it('moveEventoToDay preserva duração', () => {
    const moved = moveEventoToDay('2026-05-20', {
      data_inicio: '2026-05-10T14:30:00',
      data_fim: '2026-05-10T16:30:00',
      dia_inteiro: false,
    })
    assert.ok(moved.data_inicio.includes('2026-05-20') || toDayKeyLocal(moved.data_inicio) === '2026-05-20')
    assert.ok(moved.data_fim)
    const delta =
      new Date(moved.data_fim).getTime() - new Date(moved.data_inicio).getTime()
    assert.equal(delta, 2 * 60 * 60 * 1000)
  })

  it('nextAnoStatuses cobre reabertura', () => {
    assert.deepEqual(nextAnoStatuses('aberto'), ['coleta'])
    assert.ok(nextAnoStatuses('fechado').includes('coleta'))
  })
})
