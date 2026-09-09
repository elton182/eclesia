import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import {
  persistIgrejaId,
  loadIgrejaId,
  resolveCurrentIgrejaId,
} from '@/utils/igrejaContext'

export const useIgrejaStore = defineStore('igreja', () => {
  const igrejas = ref([])
  const currentId = ref(loadIgrejaId())
  const loading = ref(false)

  const current = computed(() => igrejas.value.find((i) => i.id === currentId.value) || null)

  const select = (id) => {
    const next = id || ''
    if (next === currentId.value) return
    currentId.value = next
    persistIgrejaId(next)
  }

  const reset = () => {
    igrejas.value = []
    currentId.value = ''
    persistIgrejaId('')
  }

  const load = async () => {
    loading.value = true
    try {
      const { data } = await api.get('/igrejas')
      igrejas.value = data.data || data || []
      const resolved = resolveCurrentIgrejaId(igrejas.value, currentId.value)
      if (resolved !== currentId.value) {
        currentId.value = resolved
        persistIgrejaId(resolved)
      }
      return igrejas.value
    } finally {
      loading.value = false
    }
  }

  return {
    igrejas,
    currentId,
    current,
    loading,
    select,
    reset,
    load,
  }
})
