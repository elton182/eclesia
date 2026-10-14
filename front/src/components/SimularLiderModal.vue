<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import InnovModal from '@/components/base/InnovModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import api from '@/services/api'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
})

const emit = defineEmits(['update:modelValue'])

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const equipes = ref([])
const selected = ref([])
const loading = ref(false)
const submitting = ref(false)
const loadError = ref('')

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return
    selected.value = []
    loadError.value = ''
    loading.value = true
    try {
      const { data } = await api.get('/ecc/equipes')
      const list = data?.data || data || []
      equipes.value = Array.isArray(list) ? list : []
      if (!equipes.value.length) {
        loadError.value = 'Nenhuma equipe cadastrada nesta igreja.'
      }
    } catch (e) {
      loadError.value = e?.response?.data?.message || 'Não foi possível carregar as equipes.'
      equipes.value = []
    } finally {
      loading.value = false
    }
  },
)

function toggle(id) {
  const set = new Set(selected.value)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  selected.value = [...set]
}

function close() {
  emit('update:modelValue', false)
}

async function ativar() {
  if (!selected.value.length || submitting.value) return
  submitting.value = true
  try {
    await auth.activateViewAsLider(selected.value)
    close()
    await router.push({ name: 'ecc-minha-equipe' })
  } catch (e) {
    toast.showToast(
      'error',
      'Simular líder',
      e?.response?.data?.message || 'Não foi possível ativar a simulação.',
    )
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <InnovModal :model-value="modelValue" size="md" @update:model-value="emit('update:modelValue', $event)">
    <div data-testid="simular-lider-modal">
      <h2 id="modal-title" class="font-serif text-[1.25rem] font-medium mb-1" style="color: var(--color-ink)">
        Simular líder de equipe
      </h2>
      <p class="text-[13px] mb-4" style="color: var(--color-muted)">
        Escolha as equipes cuja visão você quer validar. Sua identidade de admin permanece; a API e a
        interface passam a se comportar como líder.
      </p>

      <div v-if="loading" class="text-[13px] py-4" style="color: var(--color-muted)">
        Carregando equipes…
      </div>
      <p v-else-if="loadError" class="text-[13px] py-2" style="color: var(--color-danger, #b42318)">
        {{ loadError }}
      </p>
      <ul v-else class="flex flex-col gap-2 max-h-64 overflow-y-auto mb-4" data-testid="simular-lider-lista">
        <li v-for="eq in equipes" :key="eq.id">
          <label
            class="flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer text-[13.5px]"
            style="border: 1px solid var(--color-line)"
          >
            <input
              type="checkbox"
              class="accent-[var(--color-primary)]"
              :value="eq.id"
              :checked="selected.includes(eq.id)"
              :data-testid="`simular-lider-eq-${eq.id}`"
              @change="toggle(eq.id)"
            />
            <span style="color: var(--color-ink)">{{ eq.nome }}</span>
          </label>
        </li>
      </ul>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-ghost" data-testid="simular-lider-cancelar" @click="close">
          Cancelar
        </button>
        <button
          type="button"
          class="btn btn-primary"
          data-testid="simular-lider-ativar"
          :disabled="!selected.length || submitting || loading || !!loadError"
          @click="ativar"
        >
          {{ submitting ? 'Ativando…' : 'Ativar simulação' }}
        </button>
      </div>
    </div>
  </InnovModal>
</template>
