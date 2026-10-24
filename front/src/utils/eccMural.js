import { formatRecadosBadge } from './liderRecados.js'

/**
 * @param {{ recados?: number, reflexoes?: number }|null|undefined} data
 * @returns {{ recados: string|null, reflexoes: string|null }}
 */
export function formatMuralBadges(data) {
  return {
    recados: formatRecadosBadge(data?.recados ?? 0),
    reflexoes: formatRecadosBadge(data?.reflexoes ?? 0),
  }
}

/**
 * @param {number|null|undefined} bytes
 * @returns {string}
 */
export function formatAnexoTamanho(bytes) {
  const n = Number(bytes) || 0
  if (n <= 0) return ''
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}
