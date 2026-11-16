import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import * as XLSX from 'xlsx'
import {
  PLANEJAMENTO_EXCEL_HEADERS,
  buildPlanejamentoAnoWorkbook,
  buildPlanejamentoMesRows,
  buildPlanejamentoMesWorkbook,
  eventoToExcelRow,
  formatDataEventoExcel,
  formatHorarioEventoExcel,
  formatLocalEventoExcel,
  formatPastoralEventoExcel,
  formatRecursosEventoExcel,
  nomeAbaMes,
} from './planejamentoExport.js'

const amostra = [
  {
    id: '1',
    data_inicio: '2026-03-10',
    data_fim: '2026-03-10',
    hora_inicio: '19:00:00',
    hora_fim: '21:30',
    pastoral_nome: 'ECC',
    titulo: 'Reunião de casais',
    locais: [{ nome: 'Salão paroquial' }],
    local_texto: 'Entrada lateral',
    recursos: [{ nome: 'Projetor', quantidade: 1 }, { nome: 'Cadeira', quantidade: 20 }],
    participantes_media: 40,
    status_solicitacao: 'confirmado',
    observacoes: 'Levar lanche',
    motivo_ajuste: null,
  },
  {
    id: '2',
    data_inicio: '2026-03-05',
    data_fim: null,
    hora_inicio: '08:00',
    hora_fim: null,
    pastoral: { nome: 'Liturgia' },
    titulo: 'Ensaio',
    locais: [],
    local_texto: 'Igreja',
    recursos: [],
    participantes_media: null,
    status_solicitacao: 'proposta',
    observacoes: null,
    motivo_ajuste: 'Confirmar horário',
  },
  {
    id: '3',
    data_inicio: '2026-04-01',
    titulo: 'Fora do mês',
    status_solicitacao: 'proposta',
  },
]

describe('planejamentoExport', () => {
  it('formata data, horário, pastoral, local e recursos', () => {
    assert.equal(formatDataEventoExcel(amostra[0]), '10/03/2026')
    assert.equal(
      formatDataEventoExcel({ data_inicio: '2026-03-10', data_fim: '2026-03-12' }),
      '10/03/2026 – 12/03/2026',
    )
    assert.equal(formatHorarioEventoExcel(amostra[0]), '19:00 – 21:30')
    assert.equal(formatHorarioEventoExcel(amostra[1]), '08:00')
    assert.equal(formatPastoralEventoExcel(amostra[0]), 'ECC')
    assert.equal(formatPastoralEventoExcel(amostra[1]), 'Liturgia')
    assert.equal(formatLocalEventoExcel(amostra[0]), 'Salão paroquial; Entrada lateral')
    assert.equal(formatLocalEventoExcel(amostra[1]), 'Igreja')
    assert.equal(formatRecursosEventoExcel(amostra[0]), 'Projetor, Cadeira (×20)')
  })

  it('eventoToExcelRow e buildPlanejamentoMesRows ordenam e filtram o mês', () => {
    const row = eventoToExcelRow(amostra[1])
    assert.equal(row[0], '05/03/2026')
    assert.equal(row[7], 'Proposta')
    assert.match(String(row[8]), /Ajuste: Confirmar horário/)

    const rows = buildPlanejamentoMesRows(amostra, 3)
    assert.deepEqual(rows[0], [...PLANEJAMENTO_EXCEL_HEADERS])
    assert.equal(rows.length, 3) // header + 2 de março
    assert.equal(rows[1][3], 'Ensaio') // 05/03 antes de 10/03
    assert.equal(rows[2][3], 'Reunião de casais')
    assert.equal(buildPlanejamentoMesRows(amostra, 4).length, 2)
    assert.equal(buildPlanejamentoMesRows([], 1).length, 1)
  })

  it('nomeAbaMes e workbooks mês/ano', () => {
    assert.equal(nomeAbaMes(3), 'Março')
    assert.equal(nomeAbaMes(12), 'Dezembro')

    const bufMes = buildPlanejamentoMesWorkbook(XLSX, amostra, 3)
    assert.ok(bufMes instanceof ArrayBuffer || ArrayBuffer.isView(bufMes))
    const wbMes = XLSX.read(bufMes, { type: 'array' })
    assert.deepEqual(wbMes.SheetNames, ['Março'])
    const aoa = XLSX.utils.sheet_to_json(wbMes.Sheets.Março, { header: 1 })
    assert.equal(aoa.length, 3)

    const bufAno = buildPlanejamentoAnoWorkbook(XLSX, amostra)
    const wbAno = XLSX.read(bufAno, { type: 'array' })
    assert.equal(wbAno.SheetNames.length, 12)
    assert.equal(wbAno.SheetNames[0], 'Janeiro')
    assert.equal(wbAno.SheetNames[2], 'Março')
    assert.equal(wbAno.SheetNames[11], 'Dezembro')
    const mar = XLSX.utils.sheet_to_json(wbAno.Sheets.Março, { header: 1 })
    assert.equal(mar.length, 3)
    const jan = XLSX.utils.sheet_to_json(wbAno.Sheets.Janeiro, { header: 1 })
    assert.equal(jan.length, 1)
  })
})
