import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  filterCasais,
  filterEquipesBySearch,
  casaisRouteForEquipe,
  casaisListQuery,
  casaisListQueryFromRoute,
  casaisListRoute,
} from './eccFilters.js'

describe('filterEquipesBySearch', () => {
  const equipes = [
    { id: '1', nome: 'NOSSA SENHORA DA GLÓRIA' },
    { id: '2', nome: 'SANTO ANTÔNIO DE PÁDUA' },
  ]

  it('retorna tudo sem termo', () => {
    assert.equal(filterEquipesBySearch(equipes, '').length, 2)
  })

  it('filtra por nome parcial', () => {
    const r = filterEquipesBySearch(equipes, 'antônio')
    assert.equal(r.length, 1)
    assert.equal(r[0].id, '2')
  })
})

describe('filterCasais', () => {
  const casais = [
    {
      id: 'a',
      equipe_id: '1',
      nome: 'Eliseu',
      nome_conjuge: 'Érika',
      equipe_nome: 'SANTO ANTÔNIO',
      cidade: 'Campinas',
    },
    {
      id: 'b',
      equipe_id: '2',
      nome: 'Luiz',
      nome_conjuge: 'Vera',
      equipe_nome: 'GLÓRIA',
      cidade: 'São Paulo',
    },
  ]

  it('filtra por equipe e pesquisa', () => {
    assert.equal(filterCasais({ casais, search: '', equipeId: '1' }).length, 1)
    assert.equal(filterCasais({ casais, search: 'eliseu', equipeId: '' }).length, 1)
    assert.equal(filterCasais({ casais, search: 'campinas', equipeId: '' })[0].id, 'a')
  })
})

describe('casaisListQuery', () => {
  it('monta equipe e pesquisa', () => {
    assert.deepEqual(casaisListQuery({ equipeId: 'abc-1', search: 'eliseu' }), {
      equipe: 'abc-1',
      q: 'eliseu',
    })
  })

  it('omite vazios e faz trim', () => {
    assert.deepEqual(casaisListQuery({ equipeId: '  ', search: '  ' }), {})
    assert.deepEqual(casaisListQuery({ search: '  vera  ' }), { q: 'vera' })
  })
})

describe('casaisListQueryFromRoute', () => {
  it('preserva filtros e ignora edit', () => {
    assert.deepEqual(
      casaisListQueryFromRoute({ equipe: '1', q: 'vera', edit: 'xyz' }),
      { equipe: '1', q: 'vera' },
    )
  })
})

describe('casaisListRoute / casaisRouteForEquipe', () => {
  it('monta rota com filtros', () => {
    assert.deepEqual(casaisListRoute({ equipeId: 'abc-1', search: 'x' }), {
      name: 'ecc-casais',
      query: { equipe: 'abc-1', q: 'x' },
    })
  })

  it('casaisRouteForEquipe mantém compatibilidade', () => {
    assert.deepEqual(casaisRouteForEquipe('abc-1'), {
      name: 'ecc-casais',
      query: { equipe: 'abc-1' },
    })
    assert.deepEqual(casaisRouteForEquipe(''), {
      name: 'ecc-casais',
      query: {},
    })
  })
})
