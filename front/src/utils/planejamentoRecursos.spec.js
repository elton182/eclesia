import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelTipoRecurso,
  splitPorTipo,
  emptySelecaoRecursos,
  selecaoFromEvento,
  buildRecursosPayload,
  selecaoTemEspaco,
  toggleEspaco,
  setQuantidadeRecurso,
  disponibilidadeParams,
  formatSaldo,
  itensExcedidos,
  isErroCapacidade,
  formatConflitosRecursos,
  moverEventoParaDia,
  labelRecursosEvento,
} from './planejamentoRecursos.js'

describe('planejamentoRecursos utils', () => {
  it('labels e split por tipo', () => {
    assert.equal(labelTipoRecurso('espaco'), 'Espaço')
    assert.equal(labelTipoRecurso('recurso'), 'Recurso')
    assert.equal(labelTipoRecurso('outro'), 'outro')
    const { espacos, recursos } = splitPorTipo([
      { id: 'a', tipo: 'espaco' },
      { id: 'b', tipo: 'recurso' },
      null,
    ])
    assert.deepEqual(espacos.map((i) => i.id), ['a'])
    assert.deepEqual(recursos.map((i) => i.id), ['b'])
    assert.deepEqual(splitPorTipo(undefined), { espacos: [], recursos: [] })
  })

  it('seleção ↔ payload', () => {
    const sel = selecaoFromEvento({
      recursos: [
        { recurso_id: 's1', tipo: 'espaco', quantidade: 1 },
        { recurso_id: 'p1', tipo: 'recurso', quantidade: 2 },
        { tipo: 'recurso' },
      ],
    })
    assert.deepEqual(sel, { espacoIds: ['s1'], quantidades: { p1: 2 } })
    assert.deepEqual(buildRecursosPayload(sel), [
      { recurso_id: 's1', quantidade: 1 },
      { recurso_id: 'p1', quantidade: 2 },
    ])
    assert.deepEqual(buildRecursosPayload({ espacoIds: [], quantidades: { x: 0 } }), [])
    assert.deepEqual(buildRecursosPayload(null), [])
    assert.deepEqual(selecaoFromEvento(null), emptySelecaoRecursos())
    assert.equal(selecaoTemEspaco(sel), true)
    assert.equal(selecaoTemEspaco(emptySelecaoRecursos()), false)
  })

  it('toggle e quantidade', () => {
    let sel = emptySelecaoRecursos()
    sel = toggleEspaco(sel, 's1')
    assert.deepEqual(sel.espacoIds, ['s1'])
    sel = toggleEspaco(sel, 's1')
    assert.deepEqual(sel.espacoIds, [])
    sel = setQuantidadeRecurso(sel, 'p1', 5, 3)
    assert.equal(sel.quantidades.p1, 3)
    sel = setQuantidadeRecurso(sel, 'p1', 0)
    assert.equal('p1' in sel.quantidades, false)
    sel = setQuantidadeRecurso(sel, 'p1', -2)
    assert.deepEqual(sel.quantidades, {})
  })

  it('parâmetros de disponibilidade', () => {
    assert.equal(disponibilidadeParams({ data_inicio: '' }), null)
    assert.deepEqual(
      disponibilidadeParams(
        { data_inicio: '2026-03-10', data_fim: '2026-03-12', hora_inicio: '19:00:00', hora_fim: '21:00' },
        'ev1',
      ),
      {
        data_inicio: '2026-03-10',
        data_fim: '2026-03-12',
        hora_inicio: '19:00',
        hora_fim: '21:00',
        ignorar_evento_id: 'ev1',
      },
    )
    assert.deepEqual(disponibilidadeParams({ data_inicio: '2026-03-10', data_fim: '2026-03-01' }), {
      data_inicio: '2026-03-10',
    })
  })

  it('saldo e itens excedidos', () => {
    assert.equal(formatSaldo({ tipo: 'espaco', disponivel: 1, quantidade: 1 }), 'Livre')
    assert.equal(formatSaldo({ tipo: 'espaco', disponivel: 0, quantidade: 1 }), 'Ocupado')
    assert.equal(formatSaldo({ tipo: 'recurso', disponivel: 1, quantidade: 3 }), '1 de 3 livre')
    assert.equal(formatSaldo({ tipo: 'recurso', disponivel: 2, quantidade: 3 }), '2 de 3 livres')
    assert.equal(formatSaldo(null), '')
    const disp = [
      { recurso_id: 's1', disponivel: 0 },
      { recurso_id: 'p1', disponivel: 1 },
    ]
    assert.deepEqual(
      itensExcedidos({ espacoIds: ['s1'], quantidades: { p1: 2 } }, disp),
      ['s1', 'p1'],
    )
    assert.deepEqual(itensExcedidos({ espacoIds: [], quantidades: { p1: 1 } }, disp), [])
  })

  it('erro de capacidade', () => {
    const err = {
      response: {
        status: 422,
        data: {
          conflitos_recursos: [
            {
              nome: 'Sala 1',
              tipo: 'espaco',
              disponivel: 0,
              pedido: 1,
              eventos: [{ titulo: 'Catequese', hora_inicio: '19:00:00' }],
            },
            { nome: 'Projetor', tipo: 'recurso', disponivel: 0, quantidade: 1, pedido: 1 },
          ],
        },
      },
    }
    assert.equal(isErroCapacidade(err), true)
    assert.equal(isErroCapacidade({ response: { status: 422, data: {} } }), false)
    assert.equal(isErroCapacidade(new Error('x')), false)
    const msg = formatConflitosRecursos(err.response.data)
    assert.match(msg, /Sala 1 já está ocupado neste horário — em uso por Catequese \(19:00\)/)
    assert.match(msg, /Projetor: 0 de 1 livre\(s\), pedido 1/)
    assert.equal(formatConflitosRecursos({ message: 'Falhou' }), 'Falhou')
    assert.equal(formatConflitosRecursos(null), '')
  })

  it('mover evento para outro dia preserva duração', () => {
    assert.deepEqual(moverEventoParaDia({ data_inicio: '2026-03-10' }, '2026-03-15'), {
      data_inicio: '2026-03-15',
      data_fim: null,
    })
    assert.deepEqual(
      moverEventoParaDia({ data_inicio: '2026-03-30', data_fim: '2026-04-01' }, '2026-04-29'),
      { data_inicio: '2026-04-29', data_fim: '2026-05-01' },
    )
  })

  it('label dos recursos do evento', () => {
    assert.equal(
      labelRecursosEvento({
        recursos: [
          { nome: 'Sala 1', tipo: 'espaco', quantidade: 1 },
          { nome: 'Projetor', tipo: 'recurso', quantidade: 2 },
          { nome: 'Som', tipo: 'recurso', quantidade: 1 },
        ],
      }),
      'Sala 1, Projetor ×2, Som',
    )
    assert.equal(labelRecursosEvento({}), '')
  })
})
