/**
 * Upload multipart da foto da pessoa.
 * @param {import('axios').AxiosInstance} api
 * @param {string} pessoaId
 * @param {File|Blob} file
 */
export async function uploadPessoaFoto(api, pessoaId, file) {
  const body = new FormData()
  body.append('file', file)
  const { data } = await api.post(`/pessoas/${pessoaId}/foto`, body)
  return data.data || data
}

/**
 * Remove foto da pessoa.
 * @param {import('axios').AxiosInstance} api
 * @param {string} pessoaId
 */
export async function deletePessoaFoto(api, pessoaId) {
  await api.delete(`/pessoas/${pessoaId}/foto`)
}

/**
 * Aceita imagem do input (galeria ou câmera).
 * @param {Event} event
 * @returns {File|null}
 */
export function fileFromInputEvent(event) {
  const input = event?.target
  const file = input?.files?.[0] ?? null
  if (input) input.value = ''
  return file
}

/**
 * Casal tem ficha com foto se ele ou ela possui foto_url.
 * @param {{ ele?: { foto_url?: string|null }, ela?: { foto_url?: string|null }, ficha_com_foto?: boolean }} casal
 */
export function casalTemFichaComFoto(casal) {
  if (!casal) return false
  if (casal.ele?.foto_url || casal.ela?.foto_url) return true
  return Boolean(casal.ficha_com_foto)
}
