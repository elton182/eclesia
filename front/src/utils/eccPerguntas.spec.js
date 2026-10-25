import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelResumoPerguntas,
  temAtividadePerguntas,
  labelStatusPergunta,
  labelTipoCampo,
  campoVazio,
  rotuloRespostaEfetivo,
  labelEquipeComPiloto,
  ROTULO_RESPOSTA_PADRAO,
} from './eccPerguntas.js'

describe('eccPerguntas', () => {
  it('labelResumo prioriza pendentes', () => {
    assert.equal(labelResumoPerguntas({ pendentes: 2, com_resposta_nova: 1 }), '2 para responder')
    assert.equal(labelResumoPerguntas({ pendentes: 1 }), '1 para responder')
    assert.equal(labelResumoPerguntas({ pendentes: 0, com_resposta_nova: 1 }), '1 resposta disponível')
    assert.equal(labelResumoPerguntas({ pendentes: 0, com_resposta_nova: 0 }), null)
  })

  it('temAtividadePerguntas', () => {
    assert.equal(temAtividadePerguntas({ pendentes: 1 }), true)
    assert.equal(temAtividadePerguntas({ com_resposta_nova: 2 }), true)
    assert.equal(temAtividadePerguntas({}), false)
  })

  it('rótulos de status e tipo', () => {
    assert.equal(labelStatusPergunta('disponivel'), 'Disponível')
    assert.equal(labelTipoCampo('texto_longo'), 'Texto longo')
  })

  it('campoVazio lista inicia com opção em branco', () => {
    assert.deepEqual(campoVazio('lista').opcoes, [''])
    assert.deepEqual(campoVazio('texto_curto').opcoes, [])
  })

  it('rotuloRespostaEfetivo usa padrão quando vazio', () => {
    assert.equal(rotuloRespostaEfetivo(''), ROTULO_RESPOSTA_PADRAO)
    assert.equal(rotuloRespostaEfetivo('Retorno da secretaria'), 'Retorno da secretaria')
  })

  it('labelEquipeComPiloto junta equipe e casal piloto', () => {
    assert.equal(labelEquipeComPiloto({ equipe_nome: 'Alpha', casal_piloto: 'Ana & João' }), 'Alpha · Ana & João')
    assert.equal(labelEquipeComPiloto({ equipe_nome: 'Alpha' }), 'Alpha')
  })
})
