import axios from 'axios'
import { useTenantStore } from '../stores/tenant'

const api = axios.create({
  baseURL: (import.meta.env.VITE_API_URL || 'http://localhost:8000/') + 'api/v1/',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.defaults.withCredentials = true
api.defaults.withXSRFToken = true

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    try {
      const tenantStore = useTenantStore()
      if (tenantStore.slug) {
        config.headers['X-Tenant'] = tenantStore.slug
      }
    } catch {
      const slug = localStorage.getItem('eclesia_tenant_slug')
      if (slug) {
        config.headers['X-Tenant'] = slug
      }
    }

    return config
  },
  (error) => Promise.reject(error),
)

export const setAuthToken = (token) => {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
    localStorage.setItem('auth_token', token)
  } else {
    delete api.defaults.headers.common.Authorization
    localStorage.removeItem('auth_token')
  }
}

const savedToken = localStorage.getItem('auth_token')
if (savedToken) {
  setAuthToken(savedToken)
}

export default api
