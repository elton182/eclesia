import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { casalEle, casalEla, formFieldsFromEleEla } from './casalDisplay.js'

describe('casalDisplay', () => {
  it('usa ele/ela quando presentes', () => {
    const c = {
      nome: 'A',
      nome_conjuge: 'B',
      ele: { nome: 'João' },
      ela: { nome: 'Maria' },
    }
    assert.equal(casalEle(c), 'João')
    assert.equal(casalEla(c), 'Maria')
  })

  it('faz fallback para nome/nome_conjuge', () => {
    const c = { nome: 'Pedro', nome_conjuge: 'Ana' }
    assert.equal(casalEle(c), 'Pedro')
    assert.equal(casalEla(c), 'Ana')
  })

  it('monta formulário a partir de ele/ela', () => {
    const fields = formFieldsFromEleEla({
      nome: 'A',
      nome_conjuge: 'B',
      email: 'a@x.com',
      ele: { nome: 'João', email: 'j@x.com', telefone: '1', data_nascimento: '2000-01-01' },
      ela: { nome: 'Maria', email: 'm@x.com', telefone: '2', data_nascimento: '2001-02-02' },
    })
    assert.equal(fields.nome, 'João')
    assert.equal(fields.nome_conjuge, 'Maria')
    assert.equal(fields.email, 'j@x.com')
    assert.equal(fields.email_conjuge, 'm@x.com')
  })
})
