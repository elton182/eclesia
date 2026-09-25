/**
 * Parser da planilha "FLUXO CAIXA ECC" (aba por ano).
 *
 * Layout (modelo 2025):
 *   A DATA | B HISTÓRICO | C–E conta 1 (Entrada/Saída/Saldo) | F–H conta 2 | I–K totais
 * Linhas TOTAL e cabeçalhos são ignoradas.
 */

/**
 * @param {import('xlsx').WorkBook} workbook
 * @param {typeof import('xlsx')} XLSX
 * @returns {{
 *   anos: Array<{ ano: number, contas: Array<{ nome: string, tipo: string }>, lancamentos: Array<object> }>,
 *   errors: Array<{ sheet: string, message: string }>
 * }}
 */
export function parseFluxoCaixaWorkbook(workbook, XLSX) {
  const anos = []
  const errors = []

  for (const sheetName of workbook.SheetNames || []) {
    const ano = Number(String(sheetName).trim())
    if (!Number.isInteger(ano) || ano < 2000 || ano > 2100) {
      errors.push({ sheet: sheetName, message: 'Aba ignorada (nome não é ano).' })
      continue
    }

    try {
      const sheet = workbook.Sheets[sheetName]
      const rows = sheetToMatrix(XLSX, sheet)
      anos.push(parseAnoMatrix(rows, ano))
    } catch (e) {
      errors.push({ sheet: sheetName, message: e?.message || String(e) })
    }
  }

  return { anos, errors }
}

/**
 * @param {typeof import('xlsx')} XLSX
 * @param {import('xlsx').WorkSheet} sheet
 * @returns {any[][]}
 */
export function sheetToMatrix(XLSX, sheet) {
  return XLSX.utils.sheet_to_json(sheet, { header: 1, defval: null, raw: true })
}

/**
 * @param {any[][]} rows
 * @param {number} ano
 */
export function parseAnoMatrix(rows, ano) {
  const { conta1, conta2, headerRow } = detectHeader(rows)

  const contas = [
    { nome: conta1.nome, tipo: inferTipoConta(conta1.nome, 'banco') },
    { nome: conta2.nome, tipo: inferTipoConta(conta2.nome, 'especie') },
  ]

  /** @type {Array<object>} */
  const lancamentos = []
  let transferSeq = 0

  for (let i = headerRow + 1; i < rows.length; i++) {
    const row = rows[i] || []
    const historico = String(cell(row, 1) ?? '').trim()
    const dataRaw = cell(row, 0)

    if (!historico) continue
    if (/^total$/i.test(historico)) continue
    if (/^hist[oó]rico$/i.test(historico)) continue
    if (isMonthLabel(dataRaw) && /^total$/i.test(historico)) continue

    const data = parseExcelDate(dataRaw, ano)
    if (!data) continue

    const e1 = toNumber(cell(row, conta1.entradaCol))
    const s1 = toNumber(cell(row, conta1.saidaCol))
    const e2 = toNumber(cell(row, conta2.entradaCol))
    const s2 = toNumber(cell(row, conta2.saidaCol))

    if (e1 === 0 && s1 === 0 && e2 === 0 && s2 === 0) continue

    const abertura = /saldo\s+transportado/i.test(historico)

    const isTransfer =
      !abertura &&
      ((s1 > 0 && e2 > 0 && almostEqual(s1, e2) && e1 === 0 && s2 === 0) ||
        (s2 > 0 && e1 > 0 && almostEqual(s2, e1) && e2 === 0 && s1 === 0))

    if (isTransfer) {
      transferSeq += 1
      const key = `t-${ano}-${transferSeq}`
      if (s1 > 0) {
        lancamentos.push(item(data, historico, contas[0].tipo, 'saida', s1, abertura, key))
        lancamentos.push(item(data, historico, contas[1].tipo, 'entrada', e2, abertura, key))
      } else {
        lancamentos.push(item(data, historico, contas[1].tipo, 'saida', s2, abertura, key))
        lancamentos.push(item(data, historico, contas[0].tipo, 'entrada', e1, abertura, key))
      }
      continue
    }

    if (e1 > 0) lancamentos.push(item(data, historico, contas[0].tipo, 'entrada', e1, abertura))
    if (s1 > 0) lancamentos.push(item(data, historico, contas[0].tipo, 'saida', s1, abertura))
    if (e2 > 0) lancamentos.push(item(data, historico, contas[1].tipo, 'entrada', e2, abertura))
    if (s2 > 0) lancamentos.push(item(data, historico, contas[1].tipo, 'saida', s2, abertura))
  }

  return { ano, contas, lancamentos }
}

/**
 * @param {any[][]} rows
 */
export function detectHeader(rows) {
  let headerRow = 2
  for (let i = 0; i < Math.min(rows.length, 8); i++) {
    const b = String(cell(rows[i], 1) ?? '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{M}/gu, '')
    if (b.includes('historico')) {
      headerRow = i
      break
    }
  }

  const nameRow = Math.max(0, headerRow - 1)
  const nome1 = String(cell(rows[nameRow], 2) ?? '').trim() || 'Conta ECC (paróquia)'
  const nome2 = String(cell(rows[nameRow], 5) ?? '').trim() || 'Espécie / conta particular'

  return {
    headerRow,
    conta1: { nome: nome1, entradaCol: 2, saidaCol: 3 },
    conta2: { nome: nome2, entradaCol: 5, saidaCol: 6 },
  }
}

/**
 * @param {unknown} value
 * @param {number} _anoFallback
 * @returns {string|null} Y-m-d
 */
export function parseExcelDate(value, _anoFallback) {
  if (value == null || value === '') return null
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return formatYmd(value)
  }
  if (typeof value === 'number' && value > 20000 && value < 80000) {
    const epoch = Date.UTC(1899, 11, 30)
    const d = new Date(epoch + Math.round(value) * 86400000)
    return formatYmd(d)
  }
  const s = String(value).trim()
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10)
  const br = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/)
  if (br) {
    const y = br[3].length === 2 ? `20${br[3]}` : br[3]
    return `${y}-${br[2].padStart(2, '0')}-${br[1].padStart(2, '0')}`
  }
  if (isMonthLabel(s)) return null
  return null
}

function isMonthLabel(v) {
  const s = String(v ?? '')
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .trim()
  return /^(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/.test(
    s,
  )
}

function inferTipoConta(nome, fallback) {
  const n = nome
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
  if (n.includes('esp') || n.includes('particular') || n.includes('dinheiro') || n.includes('caix')) {
    return 'especie'
  }
  if (n.includes('banco') || n.includes('paro') || n.includes('ecc') || n.includes('financ')) {
    return 'banco'
  }
  return fallback
}

function item(data, historico, contaTipo, tipo, valor, abertura, transferenciaKey = null) {
  return {
    data,
    historico,
    conta_tipo: contaTipo,
    tipo,
    valor: round2(valor),
    abertura: Boolean(abertura),
    transferencia_key: transferenciaKey,
  }
}

function cell(row, idx) {
  return Array.isArray(row) ? row[idx] : undefined
}

function toNumber(v) {
  if (v == null || v === '') return 0
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0
  const s = String(v).trim()
  if (s.includes(',') && s.includes('.')) {
    return Number(s.replace(/\./g, '').replace(',', '.')) || 0
  }
  if (s.includes(',')) return Number(s.replace(',', '.')) || 0
  const n = Number(s)
  return Number.isFinite(n) ? n : 0
}

function almostEqual(a, b) {
  return Math.abs(a - b) < 0.02
}

function round2(n) {
  return Math.round((Number(n) + Number.EPSILON) * 100) / 100
}

function formatYmd(d) {
  const y = d.getUTCFullYear()
  const m = String(d.getUTCMonth() + 1).padStart(2, '0')
  const day = String(d.getUTCDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
