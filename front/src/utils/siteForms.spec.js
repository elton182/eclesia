import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  FIELD_TYPES,
  createField,
  fieldTypeLabel,
  normalizeFields,
  slugifyFieldName,
  validateFields,
} from './siteForms.js'

describe('siteForms — tipos de campo', () => {
  it('FIELD_TYPES cobre os tipos aceitos pelo formulário público', () => {
    const values = FIELD_TYPES.map((t) => t.value)
    for (const tipo of ['text', 'email', 'tel', 'textarea', 'select', 'checkbox']) {
      assert.ok(values.includes(tipo), `tipo ausente: ${tipo}`)
    }
  })

  it('fieldTypeLabel traduz o tipo e faz fallback', () => {
    assert.equal(fieldTypeLabel('email'), 'E-mail')
    assert.equal(fieldTypeLabel('desconhecido'), 'desconhecido')
  })
})

describe('siteForms — slug do campo', () => {
  it('slugifyFieldName remove acentos e normaliza separadores', () => {
    assert.equal(slugifyFieldName('Nome Completo'), 'nome_completo')
    assert.equal(slugifyFieldName('E-mail de contato'), 'e_mail_de_contato')
    assert.equal(slugifyFieldName('  Endereço  '), 'endereco')
  })

  it('slugifyFieldName lida com entrada vazia ou inválida', () => {
    assert.equal(slugifyFieldName(''), '')
    assert.equal(slugifyFieldName(null), '')
    assert.equal(slugifyFieldName('!!!'), '')
  })
})

describe('siteForms — criação e normalização', () => {
  it('createField gera campo com defaults do tipo', () => {
    const field = createField('select', 2)
    assert.equal(field.tipo, 'select')
    assert.equal(field.ordem, 2)
    assert.equal(field.obrigatorio, false)
    assert.deepEqual(field.opcoes, [])
  })

  it('createField não compartilha opcoes entre instâncias', () => {
    const a = createField('select', 0)
    const b = createField('select', 1)
    a.opcoes.push('X')
    assert.deepEqual(b.opcoes, [])
  })

  it('normalizeFields deriva nome do label quando ausente', () => {
    const fields = normalizeFields([{ label: 'Telefone de contato', tipo: 'tel' }])
    assert.equal(fields[0].nome, 'telefone_de_contato')
  })

  it('normalizeFields resolve nomes duplicados', () => {
    const fields = normalizeFields([
      { label: 'Nome', tipo: 'text' },
      { label: 'Nome', tipo: 'text' },
    ])
    assert.equal(fields[0].nome, 'nome')
    assert.equal(fields[1].nome, 'nome_2')
  })

  it('normalizeFields reindexa ordem e limpa opcoes de tipos sem opções', () => {
    const fields = normalizeFields([
      { label: 'Assunto', nome: 'assunto', tipo: 'select', opcoes: ['A', '', 'B'], ordem: 7 },
      { label: 'Mensagem', nome: 'mensagem', tipo: 'textarea', opcoes: ['ignorado'], ordem: 1 },
    ])
    assert.deepEqual(
      fields.map((f) => f.ordem),
      [0, 1],
    )
    assert.deepEqual(fields[0].opcoes, ['A', 'B'])
    assert.deepEqual(fields[1].opcoes, [])
  })

  it('normalizeFields devolve lista vazia para valor inválido', () => {
    assert.deepEqual(normalizeFields(undefined), [])
  })
})

describe('siteForms — validação', () => {
  it('validateFields aceita definição completa', () => {
    assert.deepEqual(
      validateFields([{ label: 'Nome', nome: 'nome', tipo: 'text', obrigatorio: true }]),
      [],
    )
  })

  it('validateFields exige ao menos um campo', () => {
    const errors = validateFields([])
    assert.equal(errors.length, 1)
    assert.match(errors[0], /campo/i)
  })

  it('validateFields aponta label ausente e select sem opções', () => {
    const errors = validateFields([
      { label: '', nome: 'x', tipo: 'text' },
      { label: 'Assunto', nome: 'assunto', tipo: 'select', opcoes: [] },
    ])
    assert.equal(errors.length, 2)
    assert.match(errors[0], /1/)
    assert.match(errors[1], /opç/i)
  })

  it('validateFields aponta nomes duplicados', () => {
    const errors = validateFields([
      { label: 'Nome', nome: 'nome', tipo: 'text' },
      { label: 'Outro', nome: 'nome', tipo: 'text' },
    ])
    assert.equal(errors.length, 1)
    assert.match(errors[0], /nome/i)
  })
})
