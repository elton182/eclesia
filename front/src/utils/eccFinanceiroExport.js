/**
 * Gera planilha Excel no modelo "FLUXO CAIXA ECC" (uma aba por ano).
 */

import { nomeMes } from './eccFinanceiro.js'

/**
 * @param {typeof import('xlsx')} XLSX
 * @param {Array<{ ano: number, livro: object }>} livrosPorAno
 * @returns {ArrayBuffer}
 */
export function buildFluxoCaixaWorkbook(XLSX, livrosPorAno) {
  const wb = XLSX.utils.book_new()

  for (const { ano, livro } of livrosPorAno) {
    const rows = buildAnoRows(ano, livro)
    const sheet = XLSX.utils.aoa_to_sheet(rows)
    sheet['!cols'] = [
      { wch: 12 },
      { wch: 48 },
      { wch: 12 },
      { wch: 12 },
      { wch: 12 },
      { wch: 12 },
      { wch: 12 },
      { wch: 12 },
      { wch: 14 },
      { wch: 14 },
    ]
    XLSX.utils.book_append_sheet(wb, sheet, String(ano))
  }

  return XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
}

/**
 * @param {number} ano
 * @param {object} livro — payload de GET /ecc/financeiro?ano=
 * @returns {any[][]}
 */
export function buildAnoRows(ano, livro) {
  const contas = [...(livro?.contas || [])].sort((a, b) => (a.ordem || 0) - (b.ordem || 0))
  const c1 = contas[0] || { id: '_1', nome: 'Conta ECC (paróquia)' }
  const c2 = contas[1] || { id: '_2', nome: 'Espécie / conta particular' }

  /** @type {any[][]} */
  const rows = []
  rows.push([`MOVIMENTO DO CAIXA ECC - ${ano}`])
  rows.push([null, null, c1.nome, null, null, c2.nome])
  rows.push([
    'DATA',
    'HISTÓRICO',
    'Entrada',
    'Saída',
    'Saldo',
    'Entrada',
    'Saída',
    'Saldo',
    'Total Mensal',
    'Total Acumulado',
  ])

  for (const bloco of livro?.meses || []) {
    const linhas = compactLancamentosRows(bloco.lancamentos || [], c1.id, c2.id)
    for (const row of linhas) {
      rows.push(row)
    }
    const t1 = bloco.totais_por_conta?.[c1.id] || { entradas: 0, saidas: 0, saldo: 0 }
    const t2 = bloco.totais_por_conta?.[c2.id] || { entradas: 0, saidas: 0, saldo: 0 }
    rows.push([
      nomeMes(bloco.mes).toUpperCase(),
      'TOTAL',
      t1.entradas,
      t1.saidas,
      t1.saldo,
      t2.entradas,
      t2.saidas,
      t2.saldo,
      bloco.total_mensal,
      bloco.acumulado,
    ])
  }

  return rows
}

/**
 * Agrupa pares de transferência na mesma linha quando possível.
 * @param {object} l
 * @param {string} id1
 * @param {string} id2
 */
function lancamentoRow(l, id1, id2) {
  const row = [l.data, l.historico, null, null, null, null, null, null]
  if (l.conta_id === id1) {
    if (l.tipo === 'entrada') row[2] = l.valor
    else row[3] = l.valor
  } else if (l.conta_id === id2) {
    if (l.tipo === 'entrada') row[5] = l.valor
    else row[6] = l.valor
  } else if (l.tipo === 'entrada') {
    row[2] = l.valor
  } else {
    row[3] = l.valor
  }
  return row
}

/**
 * Compacta lançamentos de transferência (mesmo transferencia_id) numa linha.
 * @param {object[]} lancamentos
 * @param {string} id1
 * @param {string} id2
 * @returns {any[][]}
 */
export function compactLancamentosRows(lancamentos, id1, id2) {
  const used = new Set()
  /** @type {any[][]} */
  const out = []
  for (const l of lancamentos || []) {
    if (used.has(l.id)) continue
    if (l.transferencia_id) {
      const pair = (lancamentos || []).find(
        (x) => x.transferencia_id === l.transferencia_id && x.id !== l.id,
      )
      if (pair) {
        used.add(l.id)
        used.add(pair.id)
        const row = [l.data, l.historico, null, null, null, null, null, null]
        for (const item of [l, pair]) {
          if (item.conta_id === id1) {
            if (item.tipo === 'entrada') row[2] = item.valor
            else row[3] = item.valor
          } else if (item.conta_id === id2) {
            if (item.tipo === 'entrada') row[5] = item.valor
            else row[6] = item.valor
          }
        }
        out.push(row)
        continue
      }
    }
    used.add(l.id)
    out.push(lancamentoRow(l, id1, id2))
  }
  return out
}
