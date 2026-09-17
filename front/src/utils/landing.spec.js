import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  MODULOS,
  PILARES,
  PASSOS,
  NAV_PUBLICA,
  PORTAL_PREVIEW,
  modulosDisponiveis,
  modulosRoadmap,
  rotuloSituacaoModulo,
  classeSituacaoModulo,
} from './landing.js'

describe('MODULOS', () => {
  it('cobre os sete módulos previstos no brief', () => {
    assert.equal(MODULOS.length, 7)
    assert.deepEqual(
      MODULOS.map((m) => m.chave),
      ['ecc', 'catequese', 'sacramentos', 'dizimo', 'financeiro', 'pastorais', 'gestao'],
    )
  })

  it('só marca como disponível o que já existe (ECC)', () => {
    const disponiveis = modulosDisponiveis()
    assert.equal(disponiveis.length, 1)
    assert.equal(disponiveis[0].chave, 'ecc')
  })

  it('classifica os demais módulos como roadmap', () => {
    assert.equal(modulosRoadmap().length, 6)
    assert.ok(modulosRoadmap().every((m) => m.situacao === 'roadmap'))
  })

  it('descreve todo módulo com nome, descrição e ícone', () => {
    for (const modulo of MODULOS) {
      assert.ok(modulo.nome, `módulo ${modulo.chave} sem nome`)
      assert.ok(modulo.descricao, `módulo ${modulo.chave} sem descrição`)
      assert.ok(modulo.icone, `módulo ${modulo.chave} sem ícone`)
    }
  })
})

describe('rotuloSituacaoModulo', () => {
  it('diferencia disponível de em breve', () => {
    assert.equal(rotuloSituacaoModulo('disponivel'), 'Disponível')
    assert.equal(rotuloSituacaoModulo('roadmap'), 'Em breve')
  })
})

describe('classeSituacaoModulo', () => {
  it('usa badge de sucesso no disponível e de atenção no roadmap', () => {
    assert.equal(classeSituacaoModulo('disponivel'), 'badge badge-success')
    assert.equal(classeSituacaoModulo('roadmap'), 'badge badge-warning')
  })
})

describe('conteúdo da landing', () => {
  it('tem quatro pilares e três passos', () => {
    assert.equal(PILARES.length, 4)
    assert.equal(PASSOS.length, 3)
  })

  it('numera os passos em sequência', () => {
    assert.deepEqual(
      PASSOS.map((p) => p.numero),
      ['01', '02', '03'],
    )
  })

  it('aponta a navegação pública apenas para âncoras da própria página', () => {
    assert.ok(NAV_PUBLICA.length > 0)
    assert.ok(NAV_PUBLICA.every((item) => item.href.startsWith('#') && item.label))
  })
})

describe('PORTAL_PREVIEW', () => {
  it('descreve o launcher com org, usuário e módulos desktop/mobile', () => {
    assert.ok(PORTAL_PREVIEW.orgNome)
    assert.ok(PORTAL_PREVIEW.usuario)
    assert.ok(PORTAL_PREVIEW.urlBar)
    assert.ok(PORTAL_PREVIEW.modulos.length >= 3)
    for (const modulo of PORTAL_PREVIEW.modulos) {
      assert.ok(modulo.chave)
      assert.ok(modulo.nome)
      assert.ok(modulo.meta)
      assert.ok(modulo.cor)
    }
  })
})
