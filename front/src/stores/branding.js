import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../services/api'
import { applyBrandCores, resetBrandCores } from '../utils/branding'

export const useBrandingStore = defineStore('branding', () => {
  const cores = ref(null)
  const logoUrl = ref(null)
  const logoPath = ref(null)
  const logoDioceseUrl = ref(null)
  const logoDiocesePath = ref(null)
  const loaded = ref(false)

  /**
   * @param {{
   *   cores?: Record<string, string>|null,
   *   logo_url?: string|null,
   *   logo_path?: string|null,
   *   logo_diocese_url?: string|null,
   *   logo_diocese_path?: string|null,
   * }|null|undefined} payload
   */
  function applyFromPayload(payload) {
    // Sem payload: aplica fallback visual mas NÃO marca loaded —
    // permite fetch/ensure recuperar branding real (ex.: pós-login).
    if (payload == null) {
      applyBrandCores(null)
      return
    }

    cores.value = payload.cores ?? null
    logoUrl.value = payload.logo_url ?? null
    logoPath.value = payload.logo_path ?? null
    logoDioceseUrl.value = payload.logo_diocese_url ?? null
    logoDiocesePath.value = payload.logo_diocese_path ?? null
    applyBrandCores(cores.value)
    loaded.value = true
  }

  function reset() {
    cores.value = null
    logoUrl.value = null
    logoPath.value = null
    logoDioceseUrl.value = null
    logoDiocesePath.value = null
    loaded.value = false
    resetBrandCores()
  }

  /** Reaplica CSS vars a partir do estado atual (ex.: ao sair do site público). */
  function reapply() {
    if (loaded.value) {
      applyBrandCores(cores.value)
    } else {
      resetBrandCores()
    }
  }

  async function fetch() {
    const { data } = await api.get('/app/branding')
    const payload = data?.data ?? data
    applyFromPayload(payload)
    return payload
  }

  /**
   * Garante branding aplicado. Idempotente se já carregou.
   * @returns {Promise<boolean>}
   */
  async function ensureLoaded() {
    if (loaded.value) {
      applyBrandCores(cores.value)
      return true
    }
    try {
      await fetch()
      return loaded.value
    } catch {
      return false
    }
  }

  /**
   * @param {Record<string, string>|null} nextCores
   */
  async function updateCores(nextCores) {
    const { data } = await api.patch('/app/branding', { cores: nextCores })
    const payload = data?.data ?? data
    applyFromPayload(payload)
    return payload
  }

  /**
   * @param {File} file
   */
  async function uploadLogo(file) {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post('/app/branding/logo', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const payload = data?.data ?? data
    applyFromPayload(payload)
    return payload
  }

  async function removeLogo() {
    await api.delete('/app/branding/logo')
    logoUrl.value = null
    logoPath.value = null
  }

  /**
   * @param {File} file
   */
  async function uploadLogoDiocese(file) {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post('/app/branding/logo-diocese', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const payload = data?.data ?? data
    applyFromPayload(payload)
    return payload
  }

  async function removeLogoDiocese() {
    await api.delete('/app/branding/logo-diocese')
    logoDioceseUrl.value = null
    logoDiocesePath.value = null
  }

  return {
    cores,
    logoUrl,
    logoPath,
    logoDioceseUrl,
    logoDiocesePath,
    loaded,
    applyFromPayload,
    reset,
    reapply,
    fetch,
    ensureLoaded,
    updateCores,
    uploadLogo,
    removeLogo,
    uploadLogoDiocese,
    removeLogoDiocese,
  }
})
