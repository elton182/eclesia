import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../services/api'

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
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch (error) {
      // Ignora erro se a sessão já expirou
    } finally {
      clearAuth()
    }
  }

  const checkAuth = async () => {
    try {
      const response = await api.get('/user')
      if (response.data?.user) {
        setUser(response.data.user)
        return true
      }
      clearAuth()
      return false
    } catch (error) {
      clearAuth()
      return false
    }
  }

  const login = async (email, password, remember = false) => {
    try {
      await api.get('/sanctum/csrf-cookie')
      const response = await api.post('/login', {
        email,
        password,
        remember,
      })

      if (!response.data.success) {
        throw new Error(response.data.message || 'Erro ao fazer login')
      }

      setUser(response.data.user)
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
    isAuthenticated,
    login,
    logout,
    checkAuth,
  }
})
