import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import * as XLSX from 'xlsx'
import {
  PLANEJAMENTO_EXCEL_HEADERS,
  buildMesSheetRows,
  buildPlanejamentoAnualWorkbook,
  eventoToExcelRow,
  formatDataEventoExcel,
  formatHorarioEventoExcel,
  formatLocalEventoExcel,
  groupEventosPorMes,
  mapaPastoraisById,
  pastoralNomeEventoExcel,
  sheetNameMes,
} from './planejamentoExcelExport.js'

describe('planejamentoExcelExport', () => {
  it('formata data, horário, local e pastoral', () => {
    assert.equal(
      formatDataEventoExcel({ data_inicio: '2026-05-01', data_fim: '2026-05-02' }),
      '01/05/2026 – 02/05/2026',
    )
    assert.equal(formatDataEventoExcel({ data_inicio: '2026-05-01', data_fim: '2026-05-01' }), '01/05/2026')
    assert.equal(
      formatHorarioEventoExcel({ hora_inicio: '19:00:00', hora_fim: '21:30' }),
      '19:00 – 21:30',
    )
    assert.equal(
      formatLocalEventoExcel({
        locais: [{ nome: 'Salão' }, { nome: 'Capela' }],
        local_texto: 'Anexo',
      }),
      'Salão, Capela; Anexo',
    )
    assert.equal(
      pastoralNomeEventoExcel({ pastoral_id: 'p1' }, { p1: 'Catequese' }),
      'Catequese',
    )
    assert.equal(pastoralNomeEventoExcel({ pastoral_nome: 'Liturgia' }, {}), 'Liturgia')
    assert.equal(sheetNameMes(3), 'Março')
    assert.deepEqual(mapaPastoraisById([{ id: 'a', nome: ' A ' }]), { a: 'A' })
  })

  it('eventoToExcelRow monta colunas alinhadas ao PDF', () => {
    const row = eventoToExcelRow(
      {
        data_inicio: '2026-03-10',
        hora_inicio: '20:00',
        hora_fim: '22:00',
        pastoral_id: 'p1',
        titulo: 'Reunião',
        locais: [{ nome: 'Salão' }],
        status_solicitacao: 'confirmado',
        participantes_media: 40,
        observacoes: 'Trazer material',
      },
      { pastoraisById: { p1: 'Jovens' } },
    )
    assert.deepEqual(row, [
      '10/03/2026',
      '20:00 – 22:00',
      'Jovens',
      'Reunião',
      'Salão',
      'Confirmado',
      40,
      'Trazer material',
    ])
    assert.equal(row.length, PLANEJAMENTO_EXCEL_HEADERS.length)
  })

  it('groupEventosPorMes e buildMesSheetRows cobrem 12 meses', () => {
    const porMes = groupEventosPorMes([
      { id: 'b', data_inicio: '2026-01-15', hora_inicio: '10:00', titulo: 'B' },
      { id: 'a', data_inicio: '2026-01-15', hora_inicio: '08:00', titulo: 'A' },
      { id: 'c', data_inicio: '2026-05-01', titulo: 'C' },
      { id: 'x', data_inicio: 'invalid' },
    ])
    assert.equal(Object.keys(porMes).length, 12)
    assert.deepEqual(
      porMes[1].map((e) => e.id),
      ['a', 'b'],
    )
    assert.equal(porMes[5].length, 1)
    assert.equal(porMes[2].length, 0)

    const vazio = buildMesSheetRows(2, 2026, [])
    assert.equal(vazio[0][0], 'Planejamento anual — Fevereiro 2026')
    assert.deepEqual(vazio[1], [...PLANEJAMENTO_EXCEL_HEADERS])
    assert.equal(vazio[2][0], 'Nenhum evento neste mês.')

    const cheio = buildMesSheetRows(1, 2026, porMes[1], {
      pastoraisById: {},
    })
    assert.equal(cheio.length, 4)
    assert.equal(cheio[2][3], 'A')
    assert.equal(cheio[3][3], 'B')
  })

  it('buildPlanejamentoAnualWorkbook cria 12 abas', () => {
    const buffer = buildPlanejamentoAnualWorkbook(XLSX, {
      ano: 2026,
      pastorais: [{ id: 'p1', nome: 'Família' }],
      eventos: [
        {
          id: '1',
          data_inicio: '2026-07-04',
          hora_inicio: '19:00',
          pastoral_id: 'p1',
          titulo: 'Encontro',
          status_solicitacao: 'proposta',
        },
      ],
    })
    assert.ok(buffer.byteLength > 500)
    const wb = XLSX.read(buffer, { type: 'array' })
    assert.equal(wb.SheetNames.length, 12)
    assert.deepEqual(wb.SheetNames[0], 'Janeiro')
    assert.deepEqual(wb.SheetNames[6], 'Julho')
    const jul = XLSX.utils.sheet_to_json(wb.Sheets.Julho, { header: 1 })
    assert.equal(jul[0][0], 'Planejamento anual — Julho 2026')
    assert.equal(jul[2][2], 'Família')
    assert.equal(jul[2][3], 'Encontro')
    const jan = XLSX.utils.sheet_to_json(wb.Sheets.Janeiro, { header: 1 })
    assert.equal(jan[2][0], 'Nenhum evento neste mês.')
  })
})
