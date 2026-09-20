import { defineStore } from 'pinia'
import { ref } from 'vue'
import api, { setTenantToken, clearTenantToken, setAuthToken } from '../services/api'
import { useTenantStore } from './tenant'
import { useIgrejaStore } from './igreja'
import { useBrandingStore } from './branding'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isAuthenticated = ref(false)

  const setUser = (userData) => {
    user.value = userData
    isAuthenticated.value = true
  }

  const clearAuth = () => {
    user.value = null
    isAuthenticated.value = false
    clearTenantToken()
  }

  const logout = async () => {
    try {
      await api.post('/web/logout')
    } catch {
      // sessão já expirada
    } finally {
      clearAuth()
      useTenantStore().clear()
      useIgrejaStore().reset()
      useBrandingStore().reset()
    }
  }

  const checkAuth = async () => {
    const tenantStore = useTenantStore()
    if (!tenantStore.slug || !localStorage.getItem('tenant_token')) {
      clearAuth()
      return false
    }

    try {
      const response = await api.post('/web/me')
      if (response.data?.id || response.data?.email) {
        setUser(response.data)
        useBrandingStore().applyFromPayload(response.data.branding)
        return true
      }
      clearAuth()
      return false
    } catch (e) {
      // api.js já tenta /web/refresh no 401; se ainda falhar, encerra
      if (e?.response?.status === 401) {
        clearAuth()
      }
      return false
    }
  }

  /**
   * @param {string} tenant Identificador (slug, apelido ou nome)
   * @param {string} email
   * @param {string} password
   */
  const login = async (tenant, email, password) => {
    try {
      // Não misturar Bearer de super-admin com auth do tenant
      setAuthToken(null)
      clearTenantToken()
      useBrandingStore().reset()

      await api.get('/sanctum/csrf-cookie')
      const response = await api.post('/web/login', {
        tenant,
        email,
        password,
      })

      if (!response.data?.success || !response.data?.access_token) {
        throw new Error(response.data?.message || 'Erro ao fazer login')
      }

      const tenantStore = useTenantStore()
      tenantStore.select(response.data.tenant)
      setTenantToken(response.data.access_token)
      setUser(response.data.user)

      // Aplica cores/logo imediatamente (SPEC-014) — antes do /web/me e do router.push
      if (response.data.branding != null) {
        useBrandingStore().applyFromPayload(response.data.branding)
      }

      // Carrega permissions (telas.*) + reafirma branding via /web/me
      const meOk = await checkAuth()
      if (!meOk) {
        // Token/sessão inválidos logo após login
        clearAuth()
        useBrandingStore().reset()
        return {
          success: false,
          error: 'Não foi possível validar a sessão. Tente novamente.',
        }
      }

      // Rede de segurança: se /web/me veio sem branding, busca GET /app/branding
      await useBrandingStore().ensureLoaded()

      return { success: true }
    } catch (error) {
      clearTenantToken()
      useBrandingStore().reset()
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'Erro ao fazer login',
      }
    }
  }

  return {
    user,
    isAuthenticated,
    login,
    logout,
    checkAuth,
    clearAuth,
    setUser,
  }
})
