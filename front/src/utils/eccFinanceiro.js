/**
 * Totais do livro-caixa anual do ECC (espelha a planilha de fluxo).
 */

/**
 * @typedef {{ id: string, conta_id: string, tipo: 'entrada'|'saida', valor: number, transferencia_id?: string|null, abertura?: boolean }} Lancamento
 * @typedef {{ id: string, nome: string }} Conta
 */

/**
 * Saldo de um conjunto de lançamentos (entrada − saída).
 * Transferências se anulam no total geral porque entram nos dois lados.
 * @param {Lancamento[]} lancamentos
 * @returns {number}
 */
export function saldoLancamentos(lancamentos) {
  let total = 0
  for (const l of lancamentos || []) {
    const v = Number(l.valor) || 0
    total += l.tipo === 'saida' ? -v : v
  }
  return round2(total)
}

/**
 * Totais por conta a partir de lançamentos (ignora abertura se `skipAbertura`).
 * @param {Lancamento[]} lancamentos
 * @param {Conta[]} contas
 * @param {{ skipAbertura?: boolean }} [opts]
 * @returns {Record<string, { entradas: number, saidas: number, saldo: number }>}
 */
export function totaisPorConta(lancamentos, contas, opts = {}) {
  /** @type {Record<string, { entradas: number, saidas: number, saldo: number }>} */
  const map = {}
  for (const c of contas || []) {
    map[c.id] = { entradas: 0, saidas: 0, saldo: 0 }
  }
  for (const l of lancamentos || []) {
    if (opts.skipAbertura && l.abertura) continue
    if (!map[l.conta_id]) {
      map[l.conta_id] = { entradas: 0, saidas: 0, saldo: 0 }
    }
    const v = Number(l.valor) || 0
    if (l.tipo === 'saida') map[l.conta_id].saidas += v
    else map[l.conta_id].entradas += v
  }
  for (const id of Object.keys(map)) {
    map[id].entradas = round2(map[id].entradas)
    map[id].saidas = round2(map[id].saidas)
    map[id].saldo = round2(map[id].entradas - map[id].saidas)
  }
  return map
}

/**
 * Acumulado mês a mês a partir dos totais mensais e do saldo de abertura.
 * @param {number} saldoAbertura
 * @param {number[]} totaisMensais — 12 valores (jan…dez)
 * @returns {number[]}
 */
export function acumuladosMensais(saldoAbertura, totaisMensais) {
  let acc = Number(saldoAbertura) || 0
  return (totaisMensais || []).map((t) => {
    acc = round2(acc + (Number(t) || 0))
    return acc
  })
}

/**
 * Agrupa lançamentos por mês (1–12).
 * @param {Lancamento[]} lancamentos
 * @returns {Record<number, Lancamento[]>}
 */
export function groupByMes(lancamentos) {
  /** @type {Record<number, Lancamento[]>} */
  const out = {}
  for (let m = 1; m <= 12; m++) out[m] = []
  for (const l of lancamentos || []) {
    const mes = Number(String(l.data || '').slice(5, 7))
    if (mes >= 1 && mes <= 12) out[mes].push(l)
  }
  return out
}

/** @param {number} n */
export function formatMoney(n) {
  return Number(n || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const MESES = [
  'Janeiro',
  'Fevereiro',
  'Março',
  'Abril',
  'Maio',
  'Junho',
  'Julho',
  'Agosto',
  'Setembro',
  'Outubro',
  'Novembro',
  'Dezembro',
]

/** @param {number} mes 1–12 */
export function nomeMes(mes) {
  return MESES[mes - 1] || String(mes)
}

/** @param {number} n */
function round2(n) {
  return Math.round((Number(n) + Number.EPSILON) * 100) / 100
}
