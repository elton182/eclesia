import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { parseAnoMatrix, parseExcelDate, detectHeader } from './eccFinanceiroImport.js'
import { buildAnoRows, compactLancamentosRows } from './eccFinanceiroExport.js'

describe('eccFinanceiroImport', () => {
  it('parseExcelDate aceita serial Excel e ISO', () => {
    // 2025-01-02 ≈ serial 45659 (planilha 2025)
    assert.equal(parseExcelDate(45659, 2025), '2025-01-02')
    assert.equal(parseExcelDate('2025-04-15', 2025), '2025-04-15')
    assert.equal(parseExcelDate('15/04/2025', 2025), '2025-04-15')
    assert.equal(parseExcelDate('JANEIRO', 2025), null)
  })

  it('detectHeader e parseAnoMatrix lê dual conta + transferência', () => {
    const rows = [
      ['MOVIMENTO DO CAIXA ECC - 2025'],
      [null, null, 'Casal Finanças/Conta ECC Paróquia', null, null, 'Conta particular/espécie'],
      ['DATA', 'HISTÓRICO', 'Entrada', 'Saída', 'Saldo', 'Entrada', 'Saída', 'Saldo'],
      [45659, 'Saldo Transportado de 2024', 0, 0, 0, 5734.69, 0, 5734.69],
      [45763, 'Transferência bancária', 5956.36, 0, null, 0, 5956.36, null],
      [45766, 'Arrecadação Abril PIX', 2930, 0],
      ['Abril', 'TOTAL', 8886.36, 0],
    ]

    const header = detectHeader(rows)
    assert.equal(header.headerRow, 2)
    assert.match(header.conta1.nome, /Paróquia|Finanças/)

    const parsed = parseAnoMatrix(rows, 2025)
    assert.equal(parsed.ano, 2025)
    assert.equal(parsed.contas.length, 2)

    const aberturas = parsed.lancamentos.filter((l) => l.abertura)
    assert.equal(aberturas.length, 1)
    assert.equal(aberturas[0].conta_tipo, 'especie')
    assert.equal(aberturas[0].valor, 5734.69)

    const transf = parsed.lancamentos.filter((l) => l.transferencia_key)
    assert.equal(transf.length, 2)
    assert.equal(transf[0].transferencia_key, transf[1].transferencia_key)

    const pix = parsed.lancamentos.find((l) => l.historico.includes('PIX'))
    assert.equal(pix.tipo, 'entrada')
    assert.equal(pix.valor, 2930)
    assert.equal(pix.conta_tipo, 'banco')
  })
})

describe('eccFinanceiroExport', () => {
  it('compactLancamentosRows une transferência numa linha', () => {
    const rows = compactLancamentosRows(
      [
        {
          id: '1',
          data: '2025-04-05',
          historico: 'Transferência',
          conta_id: 'a',
          tipo: 'saida',
          valor: 100,
          transferencia_id: 't1',
        },
        {
          id: '2',
          data: '2025-04-05',
          historico: 'Transferência',
          conta_id: 'b',
          tipo: 'entrada',
          valor: 100,
          transferencia_id: 't1',
        },
      ],
      'a',
      'b',
    )
    assert.equal(rows.length, 1)
    assert.equal(rows[0][3], 100) // saída conta 1
    assert.equal(rows[0][5], 100) // entrada conta 2
  })

  it('buildAnoRows gera cabeçalho e TOTAL mensal', () => {
    const rows = buildAnoRows(2025, {
      contas: [
        { id: 'a', nome: 'Paróquia', ordem: 1 },
        { id: 'b', nome: 'Espécie', ordem: 2 },
      ],
      meses: [
        {
          mes: 1,
          lancamentos: [
            {
              id: '1',
              data: '2025-01-10',
              historico: 'Arrecadação',
              conta_id: 'a',
              tipo: 'entrada',
              valor: 50,
            },
          ],
          totais_por_conta: {
            a: { entradas: 50, saidas: 0, saldo: 50 },
            b: { entradas: 0, saidas: 0, saldo: 0 },
          },
          total_mensal: 50,
          acumulado: 50,
        },
      ],
    })
    assert.match(String(rows[0][0]), /2025/)
    assert.equal(rows[2][1], 'HISTÓRICO')
    const total = rows.find((r) => r[1] === 'TOTAL')
    assert.ok(total)
    assert.equal(total[2], 50)
  })
})
