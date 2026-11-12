import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  statusAcessoLabel,
  statusAcessoTone,
  formatDateTimeBr,
  acaoCadastroLabel,
  normalizeJanelaDias,
  buildResumoCards,
} from './eccCadastrosAcessos.js'

describe('eccCadastrosAcessos', () => {
  it('statusAcessoLabel e tone cobrem os quatro status', () => {
    assert.equal(statusAcessoLabel('ativo'), 'Ativo')
    assert.equal(statusAcessoTone('ativo'), 'ok')
    assert.equal(statusAcessoLabel('inativo'), 'Inativo')
    assert.equal(statusAcessoTone('inativo'), 'warn')
    assert.equal(statusAcessoLabel('nunca_acessou'), 'Nunca acessou')
    assert.equal(statusAcessoTone('nunca_acessou'), 'danger')
    assert.equal(statusAcessoLabel('sem_acesso'), 'Sem acesso')
    assert.equal(statusAcessoTone('sem_acesso'), 'muted')
    assert.equal(statusAcessoLabel(null), '—')
  })

  it('formatDateTimeBr formata ISO ou devolve traço', () => {
    assert.equal(formatDateTimeBr(null), '—')
    assert.equal(formatDateTimeBr('nao-data'), '—')
    const out = formatDateTimeBr('2026-11-12T15:30:00.000Z')
    assert.match(out, /\d{2}\/\d{2}\/2026/)
  })

  it('acaoCadastroLabel traduz created/updated', () => {
    assert.equal(acaoCadastroLabel('created'), 'Criou')
    assert.equal(acaoCadastroLabel('updated'), 'Atualizou')
    assert.equal(acaoCadastroLabel('x'), 'x')
  })

  it('normalizeJanelaDias respeita limites 7–365', () => {
    assert.equal(normalizeJanelaDias(undefined), 30)
    assert.equal(normalizeJanelaDias(2), 7)
    assert.equal(normalizeJanelaDias(400), 365)
    assert.equal(normalizeJanelaDias(45), 45)
  })

  it('buildResumoCards monta métricas da janela', () => {
    const cards = buildResumoCards({
      janela_dias: 30,
      casais_total: 10,
      casais_atualizados_janela: 4,
      casais_estagnados: 2,
      equipes_total: 5,
      equipes_com_acesso: 3,
      equipes_nunca_acessaram: 1,
      equipes_inativas_janela: 2,
      equipes_sem_acesso: 2,
    })
    assert.equal(cards.length, 8)
    assert.equal(cards[0].value, 10)
    assert.equal(cards.find((c) => c.key === 'nunca')?.value, 1)
    assert.match(cards.find((c) => c.key === 'atualizados')?.hint || '', /30/)
  })
})
