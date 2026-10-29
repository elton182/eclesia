import api from './api'
import { unwrapItem, unwrapList } from '@/utils/planejamento'

/**
 * Client da API de planejamento anual (SPEC-018).
 */

export async function listAnuais(params = {}) {
  const { data } = await api.get('/planejamento/anuais', { params })
  return unwrapList(data)
}

export async function getAnual(id) {
  const { data } = await api.get(`/planejamento/anuais/${id}`)
  return unwrapItem(data)
}

export async function createAnual(payload) {
  const { data } = await api.post('/planejamento/anuais', payload)
  return unwrapItem(data)
}

export async function updateAnual(id, payload) {
  const { data } = await api.patch(`/planejamento/anuais/${id}`, payload)
  return unwrapItem(data)
}

export async function deleteAnual(id) {
  await api.delete(`/planejamento/anuais/${id}`)
}

/**
 * @param {string} id
 * @param {{ status: string }} payload
 */
export async function changeAnualStatus(id, payload) {
  const { data } = await api.post(`/planejamento/anuais/${id}/status`, payload)
  return unwrapItem(data)
}

/**
 * @param {string} anualId
 * @param {Record<string, unknown>} [params]
 */
export async function listEventos(anualId, params = {}) {
  const { data } = await api.get(`/planejamento/anuais/${anualId}/eventos`, { params })
  return unwrapList(data)
}

/**
 * Resposta pode incluir `conflitos[]` no root ou em data.
 * @returns {{ evento: Record<string, unknown>|null, conflitos: Array }}
 */
function withConflitos(body) {
  const item = unwrapItem(body)
  const conflitos =
    (item && Array.isArray(item.conflitos) && item.conflitos) ||
    (Array.isArray(body?.conflitos) && body.conflitos) ||
    []
  if (item && 'conflitos' in item) {
    const { conflitos: _c, ...rest } = item
    return { evento: rest, conflitos }
  }
  return { evento: item, conflitos }
}

export async function createEvento(anualId, payload) {
  const { data } = await api.post(`/planejamento/anuais/${anualId}/eventos`, payload)
  return withConflitos(data)
}

export async function updateEvento(eventoId, payload) {
  const { data } = await api.patch(`/planejamento/eventos/${eventoId}`, payload)
  return withConflitos(data)
}

export async function deleteEvento(eventoId) {
  await api.delete(`/planejamento/eventos/${eventoId}`)
}

/**
 * @param {string} anualId
 * @param {{ tipo?: 'agenda'|'links', mes?: number }} [params]
 * @returns {Promise<Blob>}
 */
export async function downloadAnualPdf(anualId, params = {}) {
  const res = await api.get(`/planejamento/anuais/${anualId}/pdf`, {
    params,
    responseType: 'blob',
  })
  return res.data
}

/**
 * @returns {Promise<{ token: string, data: Record<string, unknown>|null }>}
 */
export async function createPastoralToken(anualId, pastoralId) {
  const { data } = await api.post(
    `/planejamento/anuais/${anualId}/pastorais/${pastoralId}/token`,
  )
  return {
    token: data.token || data.data?.token || '',
    data: unwrapItem(data),
  }
}

export async function listPastoralTokens(anualId) {
  const { data } = await api.get(`/planejamento/anuais/${anualId}/pastorais/tokens`)
  return unwrapList(data)
}

export async function revokePastoralToken(anualId, pastoralId) {
  await api.delete(`/planejamento/anuais/${anualId}/pastorais/${pastoralId}/token`)
}

/**
 * Link público (sem login) — SPEC-018.
 * @param {Record<string, string>} headers
 */
export async function getPublicoMeta(token, headers) {
  const { data } = await api.get(`/public/planejamento/${token}`, { headers })
  return unwrapItem(data)
}

/** @param {Record<string, string>} headers */
export async function listEventosPublicos(token, headers) {
  const { data } = await api.get(`/public/planejamento/${token}/eventos`, { headers })
  return unwrapList(data)
}

/** @param {Record<string, string>} headers */
export async function createEventoPublico(token, payload, headers) {
  const { data } = await api.post(`/public/planejamento/${token}/eventos`, payload, { headers })
  return withConflitos(data)
}

/** @param {Record<string, string>} headers */
export async function updateEventoPublico(token, eventoId, payload, headers) {
  const { data } = await api.patch(
    `/public/planejamento/${token}/eventos/${eventoId}`,
    payload,
    { headers },
  )
  return withConflitos(data)
}

/** @param {Record<string, string>} headers */
export async function deleteEventoPublico(token, eventoId, headers) {
  await api.delete(`/public/planejamento/${token}/eventos/${eventoId}`, { headers })
}
