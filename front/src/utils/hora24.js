/**
 * Horário sempre em 24h (HH:MM). O input nativo type="time" segue o locale do
 * sistema e pode exibir AM/PM mesmo com a página em pt-BR.
 */

/** @param {number} n */
function pad2(n) {
  return String(n).padStart(2, '0')
}

/** @returns {string[]} */
export function horas24() {
  return Array.from({ length: 24 }, (_, i) => pad2(i))
}

/**
 * Minutos no passo pedido. Se o valor atual não cai no passo, entra na lista.
 * @param {number} [step]
 * @param {string} [atual]
 * @returns {string[]}
 */
export function minutos24(step = 5, atual = '') {
  const raw = Math.floor(Number(step))
  const passo = Number.isFinite(raw) && raw >= 1 ? Math.min(60, raw) : 5
  const lista = []
  for (let m = 0; m < 60; m += passo) lista.push(pad2(m))
  if (/^\d{2}$/.test(atual) && !lista.includes(atual)) {
    lista.push(atual)
    lista.sort()
  }
  return lista
}

/**
 * @param {unknown} valor
 * @returns {{ hora: string, minuto: string }}
 */
export function splitHora24(valor) {
  const m = String(valor ?? '').match(/^(\d{2}):(\d{2})/)
  if (!m) return { hora: '', minuto: '' }
  const hora = Number(m[1])
  const minuto = Number(m[2])
  if (hora > 23 || minuto > 59) return { hora: '', minuto: '' }
  return { hora: m[1], minuto: m[2] }
}

/**
 * @param {string} hora
 * @param {string} minuto
 * @returns {string}
 */
export function joinHora24(hora, minuto) {
  const h = splitHora24(`${hora}:${minuto || '00'}`)
  if (!h.hora) return ''
  return `${h.hora}:${h.minuto || '00'}`
}
