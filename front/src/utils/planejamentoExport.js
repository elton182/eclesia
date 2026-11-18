/**
 * Exportação Excel do planejamento anual (SPEC-036).
 * Uma planilha por mês; ano = workbook com 12 abas.
 */

import { MES_NOMES } from './calendario.js'
import {
  filterEventosPorMes,
  formatHoraCurta,
  labelStatusSolicitacao,
} from './planejamento.js'

/** Cabeçalho padrão da agenda (alinhado ao PDF + colunas extras úteis em planilha). */
export const PLANEJAMENTO_EXCEL_HEADERS = Object.freeze([
  'Data',
  'Horário',
  'Pastoral',
  'Título',
  'Local',
  'Recursos',
  'Participantes',
  'Status',
  'Observações',
])

/**
 * @param {string|null|undefined} iso YYYY-MM-DD
 * @returns {string} dd/mm/yyyy
 */
export function formatDataBr(iso) {
  const d = (iso || '').slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return ''
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

/**
 * @param {{ data_inicio?: string, data_fim?: string|null }} evento
 * @returns {string}
 */
export function formatDataEventoExcel(evento) {
  const ini = formatDataBr(evento?.data_inicio)
  const fim = formatDataBr(evento?.data_fim)
  if (!ini) return ''
  if (fim && fim !== ini) return `${ini} – ${fim}`
  return ini
}

/**
 * @param {{ hora_inicio?: string|null, hora_fim?: string|null }} evento
 * @returns {string}
 */
export function formatHorarioEventoExcel(evento) {
  const ini = formatHoraCurta(evento?.hora_inicio)
  const fim = formatHoraCurta(evento?.hora_fim)
  if (!ini && !fim) return ''
  if (ini && fim) return `${ini} – ${fim}`
  return ini || fim
}

/**
 * @param {object} evento
 * @returns {string}
 */
export function formatPastoralEventoExcel(evento) {
  return (
    (evento?.pastoral?.nome && String(evento.pastoral.nome).trim()) ||
    (evento?.pastoral_nome && String(evento.pastoral_nome).trim()) ||
    ''
  )
}

/**
 * @param {object} evento
 * @returns {string}
 */
export function formatLocalEventoExcel(evento) {
  const nomes = (evento?.locais || [])
    .map((l) => (l?.nome && String(l.nome).trim()) || '')
    .filter(Boolean)
  const texto = (evento?.local_texto && String(evento.local_texto).trim()) || ''
  if (nomes.length && texto) return `${nomes.join(', ')}; ${texto}`
  if (nomes.length) return nomes.join(', ')
  return texto
}

/**
 * @param {object} evento
 * @returns {string}
 */
export function formatRecursosEventoExcel(evento) {
  return (evento?.recursos || [])
    .map((r) => {
      const nome = (r?.nome && String(r.nome).trim()) || ''
      if (!nome) return ''
      const q = Number(r.quantidade)
      return q > 1 ? `${nome} (×${q})` : nome
    })
    .filter(Boolean)
    .join(', ')
}

/**
 * Linha de dados (sem cabeçalho) para um evento.
 * @param {object} evento
 * @returns {any[]}
 */
export function eventoToExcelRow(evento) {
  const obs = []
  if (evento?.observacoes) obs.push(String(evento.observacoes).trim())
  if (evento?.motivo_ajuste) obs.push(`Ajuste: ${String(evento.motivo_ajuste).trim()}`)

  return [
    formatDataEventoExcel(evento),
    formatHorarioEventoExcel(evento),
    formatPastoralEventoExcel(evento),
    (evento?.titulo && String(evento.titulo).trim()) || '',
    formatLocalEventoExcel(evento),
    formatRecursosEventoExcel(evento),
    evento?.participantes_media != null && evento.participantes_media !== ''
      ? Number(evento.participantes_media)
      : '',
    labelStatusSolicitacao(evento?.status_solicitacao) || evento?.status_solicitacao || '',
    obs.filter(Boolean).join(' · '),
  ]
}

/**
 * Matriz AOA do mês (cabeçalho + eventos ordenados).
 * @param {object[]} eventos
 * @param {number} mes 1–12
 * @returns {any[][]}
 */
export function buildPlanejamentoMesRows(eventos, mes) {
  const lista = filterEventosPorMes(eventos, mes)
    .slice()
    .sort((a, b) => {
      const da = (a.data_inicio || '').slice(0, 10)
      const db = (b.data_inicio || '').slice(0, 10)
      if (da !== db) return da < db ? -1 : 1
      const ha = formatHoraCurta(a.hora_inicio) || '99:99'
      const hb = formatHoraCurta(b.hora_inicio) || '99:99'
      return ha < hb ? -1 : ha > hb ? 1 : 0
    })

  return [PLANEJAMENTO_EXCEL_HEADERS.slice(), ...lista.map(eventoToExcelRow)]
}

/**
 * Nome seguro para aba do Excel (máx. 31 chars).
 * @param {number} mes 1–12
 * @returns {string}
 */
export function nomeAbaMes(mes) {
  const nome = MES_NOMES[Number(mes)] || `Mês ${mes}`
  return String(nome).slice(0, 31)
}

const COL_WIDTHS = [
  { wch: 22 },
  { wch: 14 },
  { wch: 22 },
  { wch: 36 },
  { wch: 28 },
  { wch: 24 },
  { wch: 12 },
  { wch: 16 },
  { wch: 40 },
]

/**
 * @param {typeof import('xlsx')} XLSX
 * @param {any[][]} rows
 * @param {string} sheetName
 */
function appendSheet(XLSX, wb, rows, sheetName) {
  const sheet = XLSX.utils.aoa_to_sheet(rows)
  sheet['!cols'] = COL_WIDTHS
  XLSX.utils.book_append_sheet(wb, sheet, sheetName)
}

/**
 * Workbook com uma única aba do mês.
 * @param {typeof import('xlsx')} XLSX
 * @param {object[]} eventos
 * @param {number} mes 1–12
 * @returns {ArrayBuffer}
 */
export function buildPlanejamentoMesWorkbook(XLSX, eventos, mes) {
  const wb = XLSX.utils.book_new()
  appendSheet(XLSX, wb, buildPlanejamentoMesRows(eventos, mes), nomeAbaMes(mes))
  return XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
}

/**
 * Workbook com 12 abas (Janeiro–Dezembro).
 * @param {typeof import('xlsx')} XLSX
 * @param {object[]} eventos
 * @returns {ArrayBuffer}
 */
export function buildPlanejamentoAnoWorkbook(XLSX, eventos) {
  const wb = XLSX.utils.book_new()
  for (let mes = 1; mes <= 12; mes += 1) {
    appendSheet(XLSX, wb, buildPlanejamentoMesRows(eventos, mes), nomeAbaMes(mes))
  }
  return XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
}

/**
 * Dispara download de um ArrayBuffer xlsx no browser.
 * @param {ArrayBuffer} buffer
 * @param {string} filename
 */
export function downloadXlsxBuffer(buffer, filename) {
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
