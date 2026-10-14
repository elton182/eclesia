/**
 * Horário sempre em 24h, num único campo HH:MM.
 * O input nativo type="time" segue o locale do sistema e pode exibir AM/PM.
 */

/**
 * Mantém só dígitos e insere ":" depois da hora. Aceita digitação parcial.
 * @param {unknown} valor
 * @returns {string}
 */
export function maskHora24(valor) {
  const raw = String(valor ?? '')
  if (raw.includes(':')) {
    const [horaRaw, minutoRaw = ''] = raw.split(':')
    const hora = horaRaw.replace(/\D/g, '').slice(0, 2)
    const minuto = minutoRaw.replace(/\D/g, '').slice(0, 2)
    return `${hora}:${minuto}`
  }
  const digits = raw.replace(/\D/g, '').slice(0, 4)
  if (digits.length <= 2) return digits
  return `${digits.slice(0, 2)}:${digits.slice(2)}`
}

/**
 * Completa para HH:MM válido, ou devolve vazio. Com passo >= 60, os minutos vão para 00.
 * @param {unknown} valor
 * @param {number} [stepMinutos]
 * @returns {string}
 */
export function normalizarHora24(valor, stepMinutos = 1) {
  const masked = maskHora24(valor)
  if (!masked) return ''

  const [horaRaw, minutoRaw = ''] = masked.split(':')
  if (!horaRaw) return ''
  const hora = Number(horaRaw)
  const minuto = Number((minutoRaw || '0').padStart(2, '0'))
  if (!Number.isInteger(hora) || hora > 23 || !Number.isInteger(minuto) || minuto > 59) {
    return ''
  }

  const passo = Math.floor(Number(stepMinutos))
  const minutoFinal = Number.isFinite(passo) && passo >= 60 ? 0 : minuto

  return `${String(hora).padStart(2, '0')}:${String(minutoFinal).padStart(2, '0')}`
}
