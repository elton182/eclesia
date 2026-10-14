import api from './api'
import { unwrapItem, unwrapList } from '@/utils/planejamento'

/**
 * Espaços e recursos do planejamento (SPEC-019).
 */

/** @param {{ tipo?: 'espaco'|'recurso' }} [params] */
export async function listRecursos(params = {}) {
  const { data } = await api.get('/planejamento/recursos', { params })
  return unwrapList(data)
}

export async function createRecurso(payload) {
  const { data } = await api.post('/planejamento/recursos', payload)
  return unwrapItem(data)
}

export async function updateRecurso(id, payload) {
  const { data } = await api.patch(`/planejamento/recursos/${id}`, payload)
  return unwrapItem(data)
}

/**
 * @returns {Promise<Record<string, unknown>|null>} item inativado, ou null se excluído
 */
export async function deleteRecurso(id) {
  const res = await api.delete(`/planejamento/recursos/${id}`)
  return res.status === 204 ? null : unwrapItem(res.data)
}

/**
 * @param {string} anualId
 * @param {Record<string, string>} params
 */
export async function getDisponibilidade(anualId, params) {
  const { data } = await api.get(`/planejamento/anuais/${anualId}/disponibilidade`, { params })
  return unwrapList(data)
}

/**
 * @param {string} token
 * @param {Record<string, string>} params
 * @param {Record<string, string>} headers
 */
export async function getDisponibilidadePublica(token, params, headers) {
  const { data } = await api.get(`/public/planejamento/${token}/disponibilidade`, { params, headers })
  return unwrapList(data)
}
