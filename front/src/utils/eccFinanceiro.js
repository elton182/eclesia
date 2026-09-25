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

/** Abreviação de 3 letras (Jan, Fev…). */
export function nomeMesCurto(mes) {
  return (MESES[mes - 1] || String(mes)).slice(0, 3)
}

/**
 * Série mensal para o gráfico do livro.
 * Entradas/saídas = soma de todas as contas no mês (transferências aparecem nos dois lados).
 *
 * @param {{ meses?: Array<{ mes: number, total_mensal?: number, acumulado?: number, totais_por_conta?: Record<string, { entradas?: number, saidas?: number }> }> }|null|undefined} livro
 * @returns {Array<{ mes: number, label: string, labelCurto: string, entradas: number, saidas: number, total_mensal: number, acumulado: number }>}
 */
export function chartSeriesFromLivro(livro) {
  const meses = livro?.meses || []
  return Array.from({ length: 12 }, (_, i) => {
    const mes = i + 1
    const bloco = meses.find((m) => Number(m.mes) === mes) || {}
    let entradas = 0
    let saidas = 0
    for (const t of Object.values(bloco.totais_por_conta || {})) {
      entradas += Number(t?.entradas) || 0
      saidas += Number(t?.saidas) || 0
    }
    return {
      mes,
      label: nomeMes(mes),
      labelCurto: nomeMesCurto(mes),
      entradas: round2(entradas),
      saidas: round2(saidas),
      total_mensal: round2(Number(bloco.total_mensal) || 0),
      acumulado: round2(Number(bloco.acumulado) || 0),
    }
  })
}

/**
 * Escala linear para barras (sempre ≥ 0 no domínio de valores absolutos).
 * @param {number[]} values
 * @param {number} [pad=1.1]
 */
export function chartMaxAbs(values, pad = 1.1) {
  const max = Math.max(0, ...values.map((v) => Math.abs(Number(v) || 0)))
  if (max === 0) return 1
  return round2(max * pad)
}

/**
 * Config Chart.js (barras entradas/saídas + linha de acumulado).
 * @param {ReturnType<typeof chartSeriesFromLivro>} series
 * @param {{ formatMoney?: (n: number) => string }} [opts]
 */
export function buildFinanceiroChartConfig(series, opts = {}) {
  const money = opts.formatMoney || formatMoney
  const labels = series.map((s) => s.labelCurto)
  const entradas = series.map((s) => s.entradas)
  const saidas = series.map((s) => s.saidas)
  const acumulado = series.map((s) => s.acumulado)

  return {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          type: 'bar',
          label: 'Entradas',
          data: entradas,
          backgroundColor: 'rgba(200, 138, 94, 0.85)',
          borderColor: 'rgba(180, 112, 63, 1)',
          borderWidth: 1,
          borderRadius: 4,
          order: 2,
        },
        {
          type: 'bar',
          label: 'Saídas',
          data: saidas,
          backgroundColor: 'rgba(107, 28, 43, 0.8)',
          borderColor: 'rgba(78, 18, 32, 1)',
          borderWidth: 1,
          borderRadius: 4,
          order: 3,
        },
        {
          type: 'line',
          label: 'Acumulado',
          data: acumulado,
          borderColor: 'rgba(42, 20, 24, 0.9)',
          backgroundColor: 'rgba(42, 20, 24, 0.08)',
          borderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
          tension: 0.25,
          yAxisID: 'y1',
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: {
            boxWidth: 12,
            usePointStyle: true,
            color: 'rgba(42, 20, 24, 0.8)',
          },
        },
        tooltip: {
          callbacks: {
            title(items) {
              const i = items?.[0]?.dataIndex ?? 0
              return series[i]?.label || ''
            },
            label(ctx) {
              const v = Number(ctx.parsed.y) || 0
              return `${ctx.dataset.label}: ${money(v)}`
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: 'rgba(42, 20, 24, 0.62)' },
        },
        y: {
          position: 'left',
          beginAtZero: true,
          title: { display: true, text: 'Entradas / saídas', color: 'rgba(42, 20, 24, 0.62)' },
          ticks: {
            color: 'rgba(42, 20, 24, 0.62)',
            callback(value) {
              return money(Number(value))
            },
          },
          grid: { color: 'rgba(42, 20, 24, 0.08)' },
        },
        y1: {
          position: 'right',
          beginAtZero: false,
          title: { display: true, text: 'Acumulado', color: 'rgba(42, 20, 24, 0.62)' },
          ticks: {
            color: 'rgba(42, 20, 24, 0.62)',
            callback(value) {
              return money(Number(value))
            },
          },
          grid: { drawOnChartArea: false },
        },
      },
    },
  }
}

/** @param {number} n */
function round2(n) {
  return Math.round((Number(n) + Number.EPSILON) * 100) / 100
}
