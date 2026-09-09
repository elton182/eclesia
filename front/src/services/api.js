import axios from 'axios'
import { useTenantStore } from '../stores/tenant'
import { loadIgrejaId } from '../utils/igrejaContext'

const TENANT_TOKEN_KEY = 'tenant_token'
const ADMIN_TOKEN_KEY = 'auth_token'

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
    let tenantSlug = ''
    try {
      const tenantStore = useTenantStore()
      tenantSlug = tenantStore.slug || ''
      if (tenantSlug) {
        config.headers['X-Tenant'] = tenantSlug
      }
    } catch {
      tenantSlug = localStorage.getItem('eclesia_tenant_slug') || ''
      if (tenantSlug) {
        config.headers['X-Tenant'] = tenantSlug
      }
    }

    const igrejaId = loadIgrejaId()
    if (igrejaId) {
      config.headers['X-Igreja'] = igrejaId
    } else {
      delete config.headers['X-Igreja']
    }

    // Token do tenant tem prioridade quando há X-Tenant (evita Bearer de super-admin)
    const tenantToken = localStorage.getItem(TENANT_TOKEN_KEY)
    const adminToken = localStorage.getItem(ADMIN_TOKEN_KEY)

    if (tenantSlug && tenantToken) {
      config.headers.Authorization = `Bearer ${tenantToken}`
    } else if (adminToken && !tenantSlug) {
      config.headers.Authorization = `Bearer ${adminToken}`
    } else if (adminToken && !tenantToken) {
      // ECC ainda usa super-admin + X-Tenant
      config.headers.Authorization = `Bearer ${adminToken}`
    } else {
      delete config.headers.Authorization
    }

    return config
  },
  (error) => Promise.reject(error),
)

export const setAuthToken = (token) => {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
    localStorage.setItem(ADMIN_TOKEN_KEY, token)
  } else {
    delete api.defaults.headers.common.Authorization
    localStorage.removeItem(ADMIN_TOKEN_KEY)
  }
}

export const setTenantToken = (token) => {
  if (token) {
    localStorage.setItem(TENANT_TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TENANT_TOKEN_KEY)
  }
}

export const clearTenantToken = () => {
  localStorage.removeItem(TENANT_TOKEN_KEY)
}

const savedToken = localStorage.getItem(ADMIN_TOKEN_KEY)
if (savedToken) {
  setAuthToken(savedToken)
}

export default api
