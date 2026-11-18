/**
 * Exportação Excel do planejamento anual (uma aba por mês).
 */

import { MES_NOMES } from './calendario.js'
import { isoToBr } from './dateBr.js'
import {
  formatHoraCurta,
  labelStatusSolicitacao,
  sortEventosPorDataInicio,
} from './planejamento.js'

/** @type {readonly string[]} */
export const PLANEJAMENTO_EXCEL_HEADERS = Object.freeze([
  'Data',
  'Horário',
  'Pastoral',
  'Título',
  'Local',
  'Status',
  'Participantes',
  'Observações',
])

/**
 * @param {{ data_inicio?: string, data_fim?: string|null }|null|undefined} ev
 * @returns {string}
 */
export function formatDataEventoExcel(ev) {
  const ini = isoToBr(String(ev?.data_inicio || '').slice(0, 10))
  if (!ini) return ''
  const fimIso = ev?.data_fim ? String(ev.data_fim).slice(0, 10) : ''
  const fim = fimIso ? isoToBr(fimIso) : ''
  if (fim && fim !== ini) return `${ini} – ${fim}`
  return ini
}

/**
 * @param {{ hora_inicio?: string|null, hora_fim?: string|null }|null|undefined} ev
 * @returns {string}
 */
export function formatHorarioEventoExcel(ev) {
  const ini = formatHoraCurta(ev?.hora_inicio)
  const fim = formatHoraCurta(ev?.hora_fim)
  if (ini && fim) return `${ini} – ${fim}`
  return ini || fim || ''
}

/**
 * @param {{
 *   locais?: Array<{ nome?: string }>|null,
 *   local_texto?: string|null,
 * }|null|undefined} ev
 * @returns {string}
 */
export function formatLocalEventoExcel(ev) {
  const nomes = (ev?.locais || []).map((l) => (l?.nome || '').trim()).filter(Boolean)
  const texto = (ev?.local_texto || '').trim()
  if (nomes.length && texto) return `${nomes.join(', ')}; ${texto}`
  if (nomes.length) return nomes.join(', ')
  return texto
}

/**
 * @param {{
 *   pastoral_id?: string,
 *   pastoral?: { nome?: string }|null,
 *   pastoral_nome?: string|null,
 * }|null|undefined} ev
 * @param {Record<string, string>|null|undefined} pastoraisById
 * @returns {string}
 */
export function pastoralNomeEventoExcel(ev, pastoraisById) {
  const id = ev?.pastoral_id
  if (id && pastoraisById?.[id]) return pastoraisById[id]
  return (
    (ev?.pastoral?.nome && String(ev.pastoral.nome).trim()) ||
    (ev?.pastoral_nome && String(ev.pastoral_nome).trim()) ||
    ''
  )
}

/**
 * @param {object|null|undefined} ev
 * @param {{ pastoraisById?: Record<string, string> }} [opts]
 * @returns {any[]}
 */
export function eventoToExcelRow(ev, opts = {}) {
  const participantes =
    ev?.participantes_media != null && ev.participantes_media !== ''
      ? Number(ev.participantes_media)
      : ''
  return [
    formatDataEventoExcel(ev),
    formatHorarioEventoExcel(ev),
    pastoralNomeEventoExcel(ev, opts.pastoraisById),
    (ev?.titulo && String(ev.titulo).trim()) || '',
    formatLocalEventoExcel(ev),
    labelStatusSolicitacao(ev?.status_solicitacao),
    Number.isFinite(participantes) ? participantes : '',
    (ev?.observacoes && String(ev.observacoes).trim()) || '',
  ]
}

/**
 * Agrupa eventos por mês (1–12) a partir de data_inicio.
 * @param {Array<{ data_inicio?: string }>|null|undefined} eventos
 * @returns {Record<number, Array>}
 */
export function groupEventosPorMes(eventos) {
  /** @type {Record<number, Array>} */
  const out = {}
  for (let m = 1; m <= 12; m++) out[m] = []
  for (const ev of eventos || []) {
    const iso = String(ev?.data_inicio || '').slice(0, 10)
    const mes = Number(iso.slice(5, 7))
    if (mes >= 1 && mes <= 12) out[mes].push(ev)
  }
  for (let m = 1; m <= 12; m++) {
    out[m] = sortEventosPorDataInicio(out[m])
  }
  return out
}

/**
 * Linhas AOA de uma aba mensal.
 * @param {number} mes 1–12
 * @param {number} ano
 * @param {Array} eventos
 * @param {{ pastoraisById?: Record<string, string> }} [opts]
 * @returns {any[][]}
 */
export function buildMesSheetRows(mes, ano, eventos, opts = {}) {
  const nome = MES_NOMES[mes] || String(mes)
  /** @type {any[][]} */
  const rows = [[`Planejamento anual — ${nome} ${ano}`], [...PLANEJAMENTO_EXCEL_HEADERS]]
  const list = eventos || []
  if (!list.length) {
    rows.push(['Nenhum evento neste mês.'])
    return rows
  }
  for (const ev of list) {
    rows.push(eventoToExcelRow(ev, opts))
  }
  return rows
}

/**
 * Nome seguro de aba Excel (máx. 31 chars).
 * @param {number} mes
 * @returns {string}
 */
export function sheetNameMes(mes) {
  const nome = MES_NOMES[mes] || `Mes ${mes}`
  return String(nome).slice(0, 31)
}

/**
 * @param {Array<{ id?: string, nome?: string }>|null|undefined} pastorais
 * @returns {Record<string, string>}
 */
export function mapaPastoraisById(pastorais) {
  /** @type {Record<string, string>} */
  const out = {}
  for (const p of pastorais || []) {
    if (p?.id) out[p.id] = (p.nome && String(p.nome).trim()) || ''
  }
  return out
}

/**
 * Gera workbook com 12 abas (Janeiro–Dezembro).
 * @param {typeof import('xlsx')} XLSX
 * @param {{
 *   ano: number,
 *   eventos?: Array,
 *   pastorais?: Array<{ id?: string, nome?: string }>,
 * }} opts
 * @returns {ArrayBuffer}
 */
export function buildPlanejamentoAnualWorkbook(XLSX, opts) {
  const ano = Number(opts?.ano) || new Date().getFullYear()
  const pastoraisById = mapaPastoraisById(opts?.pastorais)
  const porMes = groupEventosPorMes(opts?.eventos)
  const wb = XLSX.utils.book_new()
  const cols = [
    { wch: 22 },
    { wch: 14 },
    { wch: 24 },
    { wch: 36 },
    { wch: 28 },
    { wch: 16 },
    { wch: 12 },
    { wch: 40 },
  ]

  for (let mes = 1; mes <= 12; mes++) {
    const rows = buildMesSheetRows(mes, ano, porMes[mes], { pastoraisById })
    const sheet = XLSX.utils.aoa_to_sheet(rows)
    sheet['!cols'] = cols
    XLSX.utils.book_append_sheet(wb, sheet, sheetNameMes(mes))
  }

  return XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
}
