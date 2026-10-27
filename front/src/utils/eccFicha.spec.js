import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  atividadeStatusLabel,
  etapaLabel,
  normalizeEtapasForm,
  etapasPayload,
  preferenciasPayload,
  equipesDisponiveisParaPreferencia,
  ATIVIDADE_STATUS_CODES,
} from './eccFicha.js'

describe('eccFicha', () => {
  it('traduz status de atividade', () => {
    assert.equal(atividadeStatusLabel('A'), 'Aceitou')
    assert.equal(atividadeStatusLabel('IC'), 'Indicado para coordenação')
    assert.equal(atividadeStatusLabel('NN'), 'Não aceita equipe de trabalho')
    assert.equal(atividadeStatusLabel(null), '—')
  })

  it('lista códigos de status', () => {
    assert.ok(ATIVIDADE_STATUS_CODES.includes('C'))
    assert.ok(ATIVIDADE_STATUS_CODES.includes('NA'))
  })

  it('rótulos de etapa', () => {
    assert.equal(etapaLabel(1), '1ª etapa')
    assert.equal(etapaLabel(2), '2ª etapa')
    assert.equal(etapaLabel(3), '3ª etapa')
  })

  it('normaliza formulário de etapas com 3 slots', () => {
    const form = normalizeEtapasForm([
      { etapa: 2, ecc_numero: '20', data: '2020-01-01', local: 'Salão' },
    ])
    assert.equal(form.length, 3)
    assert.equal(form[0].etapa, 1)
    assert.equal(form[0].ecc_numero, '')
    assert.equal(form[1].ecc_numero, '20')
    assert.equal(form[1].local, 'Salão')
  })

  it('payload de etapas omite vazias', () => {
    const payload = etapasPayload([
      { etapa: 1, ecc_numero: '', data: '', local: '' },
      { etapa: 2, ecc_numero: '11º', data: '', local: '' },
    ])
    assert.equal(payload.length, 1)
    assert.equal(payload[0].etapa, 2)
    assert.equal(payload[0].ecc_numero, '11º')
  })

  it('payload de preferências omite vazias e deduplica equipe', () => {
    const payload = preferenciasPayload([
      { equipe_servico_id: 'eq-a', ordem: 1 },
      { equipe_servico_id: '', ordem: 2 },
      { equipe_servico_id: 'eq-a', ordem: 6 },
      { equipe_servico_id: 'eq-b', ordem: 3 },
    ])
    assert.deepEqual(payload, [
      { equipe_servico_id: 'eq-a', ordem: 1 },
      { equipe_servico_id: 'eq-b', ordem: 3 },
    ])
  })

  it('lista equipes disponíveis sem repetir seleção de outras linhas', () => {
    const equipes = [
      { id: 'eq-a', nome: 'A' },
      { id: 'eq-b', nome: 'B' },
      { id: 'eq-c', nome: 'C' },
    ]
    const prefs = [
      { equipe_servico_id: 'eq-a' },
      { equipe_servico_id: '' },
    ]
    const forRow1 = equipesDisponiveisParaPreferencia(equipes, prefs, 1)
    assert.deepEqual(
      forRow1.map((e) => e.id),
      ['eq-b', 'eq-c'],
    )
    const forRow0 = equipesDisponiveisParaPreferencia(equipes, prefs, 0)
    assert.deepEqual(
      forRow0.map((e) => e.id),
      ['eq-a', 'eq-b', 'eq-c'],
    )
  })
})
