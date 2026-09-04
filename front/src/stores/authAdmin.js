import { defineStore } from 'pinia'
import { ref } from 'vue'
import api, { setAuthToken } from '../services/api'

export const useAuthAdminStore = defineStore('authAdmin', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token') || null)
  const isAuthenticated = ref(false)

  const setUser = (userData) => {
    if (userData.access_token) {
      setAuthToken(userData.access_token)
      token.value = userData.access_token
      localStorage.setItem('auth_token', userData.access_token)
    }
    user.value = userData
    isAuthenticated.value = true
  }

  const clearAuth = () => {
    user.value = null
    token.value = null
    isAuthenticated.value = false
    localStorage.removeItem('auth_token')
    setAuthToken(null)
  }

  const logout = async () => {
    try {
      await api.post('/admin/logout')
    } catch {
      // sessão já expirada
    } finally {
      clearAuth()
    }
  }

  const checkAuth = async () => {
    if (!localStorage.getItem('auth_token')) {
      clearAuth()
      return false
    }

    try {
      const response = await api.post('/admin/me')
      if (response.data) {
        setUser(response.data)
        return true
      }
      clearAuth()
      return false
    } catch {
      clearAuth()
      return false
    }
  }

  const login = async (email, password) => {
    try {
      await api.get('/sanctum/csrf-cookie')
      const response = await api.post('/admin/login', { email, password })

      if (!response.data?.access_token) {
        throw new Error(response.data?.message || 'Erro ao fazer login')
      }

      setUser(response.data)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'Erro ao fazer login',
      }
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    logout,
    checkAuth,
    clearAuth,
  }
})
