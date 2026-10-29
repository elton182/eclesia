/**
 * Buffer de diagnóstico da sessão (SPEC-031) — envio explícito pelo menu do perfil.
 */
import { APP_VERSION } from './appVersion.js'
import { isRunningStandalone } from './pwa.js'
import { queryCameraPermission } from './mediaPermissions.js'

export const CLIENT_DIAGNOSTIC_MAX_EVENTS = 100

/** @type {Array<{ t: number, type: string, data?: Record<string, unknown> }>} */
const events = []

/**
 * @param {string} type
 * @param {Record<string, unknown>} [data]
 */
export function trackDiagnostic(type, data = {}) {
  const entry = {
    t: Date.now(),
    type: String(type || 'unknown').slice(0, 80),
    data: data && typeof data === 'object' ? sanitizeData(data) : {},
  }
  events.push(entry)
  if (events.length > CLIENT_DIAGNOSTIC_MAX_EVENTS) {
    events.splice(0, events.length - CLIENT_DIAGNOSTIC_MAX_EVENTS)
  }
}

/**
 * @param {Record<string, unknown>} data
 * @returns {Record<string, unknown>}
 */
function sanitizeData(data) {
  /** @type {Record<string, unknown>} */
  const out = {}
  for (const [key, value] of Object.entries(data)) {
    if (value === undefined) continue
    // Evita PII acidental
    if (['email', 'name', 'nome', 'telefone', 'phone', 'password'].includes(key)) continue
    if (typeof value === 'string') {
      out[key] = value.slice(0, 200)
    } else if (typeof value === 'number' || typeof value === 'boolean' || value === null) {
      out[key] = value
    } else if (Array.isArray(value)) {
      out[key] = value.slice(0, 20)
    }
  }
  return out
}

export function getDiagnosticEvents() {
  return events.map((e) => ({ ...e, data: { ...(e.data || {}) } }))
}

export function clearDiagnosticEvents() {
  events.length = 0
}

/**
 * @returns {Promise<Record<string, unknown>>}
 */
export async function collectDiagnosticMeta() {
  const cameraPermission = await queryCameraPermission()
  const nav = typeof navigator !== 'undefined' ? navigator : null
  const win = typeof window !== 'undefined' ? window : null

  return {
    app_version: APP_VERSION,
    standalone: isRunningStandalone(),
    camera_permission: cameraPermission,
    href: win?.location?.pathname || '',
    online: typeof nav?.onLine === 'boolean' ? nav.onLine : true,
    platform: String(nav?.userAgent || '').slice(0, 120),
    viewport: win ? `${win.innerWidth}x${win.innerHeight}` : '',
    language: nav?.language || '',
  }
}

/**
 * @param {{ motivo?: string, nota?: string|null }} [opts]
 */
export async function buildDiagnosticPayload(opts = {}) {
  return {
    motivo: opts.motivo || 'geral',
    nota: opts.nota || null,
    meta: await collectDiagnosticMeta(),
    events: getDiagnosticEvents(),
  }
}

export function diagnosticEventCount() {
  return events.length
}
