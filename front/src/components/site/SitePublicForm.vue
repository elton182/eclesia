<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  slug: { type: String, required: true },
  tenantSlug: { type: String, required: true },
})

const loading = ref(true)
const sending = ref(false)
const error = ref('')
const success = ref('')
const fields = ref([])
const sucessoMensagem = ref('Mensagem enviada com sucesso.')
const values = reactive({})
const honeypot = ref('')

async function loadForm() {
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    localStorage.setItem('eclesia_tenant_slug', props.tenantSlug)
    const { data } = await api.get(`/public/site/forms/${props.slug}`)
    const form = data.data || data
    fields.value = form.fields || []
    sucessoMensagem.value = form.sucesso_mensagem || 'Mensagem enviada com sucesso.'
    for (const f of fields.value) {
      values[f.nome] = f.tipo === 'checkbox' ? false : ''
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Formulário indisponível.'
    fields.value = []
  } finally {
    loading.value = false
  }
}

async function submit() {
  sending.value = true
  error.value = ''
  success.value = ''
  try {
    await api.post(`/public/site/forms/${props.slug}/submissions`, {
      values: { ...values },
      website: honeypot.value,
    })
    success.value = sucessoMensagem.value
    for (const f of fields.value) {
      values[f.nome] = f.tipo === 'checkbox' ? false : ''
    }
  } catch (e) {
    error.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {}).flat()[0]
      || 'Falha ao enviar.'
  } finally {
    sending.value = false
  }
}

onMounted(loadForm)
watch(() => props.slug, loadForm)
</script>

<template>
  <div data-testid="site-public-form">
    <p v-if="loading" class="text-black/50 text-sm">Carregando formulário…</p>
    <form v-else-if="fields.length" class="space-y-4" @submit.prevent="submit">
      <div v-for="field in fields" :key="field.id || field.nome" class="space-y-1">
        <label class="block text-sm font-medium" :for="`f-${field.nome}`">
          {{ field.label }}
          <span v-if="field.obrigatorio" class="text-red-700">*</span>
        </label>
        <textarea
          v-if="field.tipo === 'textarea'"
          :id="`f-${field.nome}`"
          v-model="values[field.nome]"
          class="w-full border border-black/20 px-3 py-2 bg-white"
          rows="4"
          :required="field.obrigatorio"
        />
        <select
          v-else-if="field.tipo === 'select'"
          :id="`f-${field.nome}`"
          v-model="values[field.nome]"
          class="w-full border border-black/20 px-3 py-2 bg-white"
          :required="field.obrigatorio"
        >
          <option value="">Selecione…</option>
          <option v-for="opt in field.opcoes || []" :key="opt" :value="opt">{{ opt }}</option>
        </select>
        <label v-else-if="field.tipo === 'checkbox'" class="inline-flex items-center gap-2 text-sm">
          <input v-model="values[field.nome]" type="checkbox" />
          {{ field.label }}
        </label>
        <input
          v-else
          :id="`f-${field.nome}`"
          v-model="values[field.nome]"
          :type="field.tipo === 'email' ? 'email' : field.tipo === 'tel' ? 'tel' : 'text'"
          class="w-full border border-black/20 px-3 py-2 bg-white"
          :required="field.obrigatorio"
        />
      </div>
      <input v-model="honeypot" type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" />
      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
      <p v-if="success" class="text-sm text-green-800">{{ success }}</p>
      <button
        type="submit"
        class="px-5 py-2.5 bg-[#1e3a5f] text-white font-medium disabled:opacity-50"
        :disabled="sending"
      >
        {{ sending ? 'Enviando…' : 'Enviar' }}
      </button>
    </form>
    <p v-else-if="error" class="text-sm text-red-700">{{ error }}</p>
  </div>
</template>
