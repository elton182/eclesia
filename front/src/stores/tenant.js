import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const STORAGE_KEY = 'eclesia_tenant_slug'

export const useTenantStore = defineStore('tenant', () => {
  const slug = ref(localStorage.getItem(STORAGE_KEY) || '')
  const name = ref(localStorage.getItem('eclesia_tenant_name') || '')

  watch(slug, (value) => {
    if (value) {
      localStorage.setItem(STORAGE_KEY, value)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  })

  watch(name, (value) => {
    if (value) {
      localStorage.setItem('eclesia_tenant_name', value)
    } else {
      localStorage.removeItem('eclesia_tenant_name')
    }
  })

  const select = (tenant) => {
    slug.value = tenant?.slug || ''
    name.value = tenant?.name || ''
  }

  const clear = () => {
    slug.value = ''
    name.value = ''
  }

  return { slug, name, select, clear }
})
