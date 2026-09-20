import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { casalEle, casalEla, formFieldsFromEleEla, swapEleElaFormFields } from './casalDisplay.js'

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
      ele: {
        id: 'ele-1',
        nome: 'João',
        email: 'j@x.com',
        telefone: '1',
        data_nascimento: '2000-01-01',
        foto_url: 'http://x/e.jpg',
        nome_usual: 'Jão',
        profissao: 'Engenheiro',
      },
      ela: {
        id: 'ela-1',
        nome: 'Maria',
        email: 'm@x.com',
        telefone: '2',
        data_nascimento: '2001-02-02',
        foto_url: null,
        nome_usual: 'Mari',
      },
    })
    assert.equal(fields.nome, 'João')
    assert.equal(fields.nome_conjuge, 'Maria')
    assert.equal(fields.email, 'j@x.com')
    assert.equal(fields.email_conjuge, 'm@x.com')
    assert.equal(fields.pessoa_a_id, 'ele-1')
    assert.equal(fields.pessoa_b_id, 'ela-1')
    assert.equal(fields.foto_url_ele, 'http://x/e.jpg')
    assert.equal(fields.foto_url_ela, null)
    assert.equal(fields.nome_usual_ele, 'Jão')
    assert.equal(fields.profissao_ele, 'Engenheiro')
    assert.equal(fields.nome_usual_ela, 'Mari')
  })

  it('inverte campos ele/ela do formulário', () => {
    const swapped = swapEleElaFormFields({
      nome: 'João',
      nome_conjuge: 'Maria',
      email: 'j@x.com',
      email_conjuge: 'm@x.com',
      telefone: '1',
      telefone_conjuge: '2',
      data_nascimento: '2000-01-01',
      data_nascimento_conjuge: '2001-02-02',
      pessoa_a_id: 'a',
      pessoa_b_id: 'b',
      foto_url_ele: 'ele.jpg',
      foto_url_ela: 'ela.jpg',
      pending_foto_ele: null,
      pending_foto_ela: 'file',
      nome_usual_ele: 'Jão',
      nome_usual_ela: 'Mari',
      profissao_ele: 'Eng',
      profissao_ela: 'Prof',
      religiao_ele: 'Cat',
      religiao_ela: 'Cat2',
      endereco_profissional_ele: 'Av A',
      endereco_profissional_ela: 'Av B',
      telefone_profissional_ele: '11',
      telefone_profissional_ela: '22',
      equipe_id: 'eq-1',
    })
    assert.equal(swapped.nome, 'Maria')
    assert.equal(swapped.nome_conjuge, 'João')
    assert.equal(swapped.email, 'm@x.com')
    assert.equal(swapped.pessoa_a_id, 'b')
    assert.equal(swapped.pessoa_b_id, 'a')
    assert.equal(swapped.foto_url_ele, 'ela.jpg')
    assert.equal(swapped.pending_foto_ela, null)
    assert.equal(swapped.pending_foto_ele, 'file')
    assert.equal(swapped.nome_usual_ele, 'Mari')
    assert.equal(swapped.profissao_ela, 'Eng')
    assert.equal(swapped.equipe_id, 'eq-1')
  })
})
