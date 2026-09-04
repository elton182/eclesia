/**
 * Datas no padrão brasileiro dd/mm/aaaa.
 */

const BR_RE = /^(\d{2})\/(\d{2})\/(\d{4})$/
const ISO_RE = /^(\d{4})-(\d{2})-(\d{2})$/

export function isoToBr(value) {
  if (!value) return ''
  const raw = String(value).slice(0, 10)
  const m = raw.match(ISO_RE)
  if (!m) {
    if (BR_RE.test(raw)) return raw
    return ''
  }
  return `${m[3]}/${m[2]}/${m[1]}`
}

export function brToIso(value) {
  if (!value) return null
  const raw = String(value).trim()
  const br = raw.match(BR_RE)
  if (br) {
    const [, dd, mm, yyyy] = br
    if (!isValidParts(+yyyy, +mm, +dd)) return null
    return `${yyyy}-${mm}-${dd}`
  }
  const iso = raw.match(ISO_RE)
  if (iso && isValidParts(+iso[1], +iso[2], +iso[3])) {
    return raw.slice(0, 10)
  }
  return null
}

export function isValidBrDate(value) {
  const m = String(value || '').trim().match(BR_RE)
  if (!m) return false
  return isValidParts(+m[3], +m[2], +m[1])
}

function isValidParts(year, month, day) {
  if (month < 1 || month > 12 || day < 1 || day > 31) return false
  const dt = new Date(year, month - 1, day)
  return dt.getFullYear() === year && dt.getMonth() === month - 1 && dt.getDate() === day
}

/** Máscara progressiva: 01022000 → 01/02/2000 */
export function maskBrDateInput(value) {
  const digits = String(value || '').replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 2) return digits
  if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`
  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}
