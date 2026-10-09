import api from './api'
import { unwrapItem, unwrapList } from '@/utils/planejamento'

/**
 * Client da API de pastorais de domínio (SPEC-018).
 * Distinto de `/site/pastorais` (CMS do site público).
 */

export async function listPastorais(params = {}) {
  const { data } = await api.get('/pastorais', { params })
  return unwrapList(data)
}

export async function getPastoral(id) {
  const { data } = await api.get(`/pastorais/${id}`)
  return unwrapItem(data)
}

export async function createPastoral(payload) {
  const { data } = await api.post('/pastorais', payload)
  return unwrapItem(data)
}

export async function updatePastoral(id, payload) {
  const { data } = await api.patch(`/pastorais/${id}`, payload)
  return unwrapItem(data)
}

export async function deletePastoral(id) {
  await api.delete(`/pastorais/${id}`)
}

export async function listMembros(pastoralId) {
  const { data } = await api.get(`/pastorais/${pastoralId}/membros`)
  return unwrapList(data)
}

/**
 * @param {string} pastoralId
 * @param {{ user_id: string, papel: 'coordenador'|'membro' }} payload
 */
export async function addMembro(pastoralId, payload) {
  const { data } = await api.post(`/pastorais/${pastoralId}/membros`, payload)
  return unwrapItem(data) || data
}

export async function removeMembro(pastoralId, userId) {
  await api.delete(`/pastorais/${pastoralId}/membros/${userId}`)
}
