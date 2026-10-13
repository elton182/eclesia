<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { labelStatusPlanejamento, labelStatusSolicitacao, styleStatusSolicitacao } from '@/utils/planejamento'
import { innovToast } from '@/plugins/toast'

const route = useRoute()
const tenantStore = useTenantStore()
const token = computed(() => String(route.params.token || ''))
const tenant = computed(() => String(route.query.tenant || ''))

const meta = ref(null)
const eventos = ref([])
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const form = ref({
  titulo: '',
  data_inicio: '',
  hora_inicio: '',
  local_texto: '',
  observacoes: '',
})

const headers = computed(() => ({
  'X-Tenant': tenant.value || tenantStore.slug || '',
}))

async function load() {
  loading.value = true
  error.value = ''
  if (tenant.value && tenantStore.slug !== tenant.value) {
    tenantStore.select({ slug: tenant.value, name: tenantStore.name || '' })
  }
  try {
    const [m, e] = await Promise.all([
      api.get(`/public/planejamento/${token.value}`, { headers: headers.value }),
      api.get(`/public/planejamento/${token.value}/eventos`, { headers: headers.value }),
    ])
    meta.value = m.data.data || m.data
    eventos.value = e.data.data || e.data || []
    if (!form.value.data_inicio && meta.value?.ano) {
      form.value.data_inicio = `${meta.value.ano}-01-01`
    }
  } catch (err) {
    error.value = err.response?.data?.message || err.response?.data?.errors?.token?.[0] || 'Link inválido ou expirado.'
  } finally {
    loading.value = false
  }
}

async function criar() {
  saving.value = true
  try {
    await api.post(
      `/public/planejamento/${token.value}/eventos`,
      {
        titulo: form.value.titulo,
        data_inicio: form.value.data_inicio,
        hora_inicio: form.value.hora_inicio || null,
        local_texto: form.value.local_texto,
        observacoes: form.value.observacoes || null,
      },
      { headers: headers.value },
    )
    innovToast('success', 'Planejamento', 'Proposta enviada')
    form.value.titulo = ''
    form.value.observacoes = ''
    await load()
  } catch (err) {
    const msg =
      err.response?.data?.message ||
      err.response?.data?.errors?.status?.[0] ||
      'Não foi possível enviar'
    innovToast('error', 'Planejamento', msg)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen px-4 py-8" style="background: var(--color-bg, #F7F4EF)">
    <div class="max-w-lg mx-auto">
      <p class="page-eyebrow">Planejamento Anual</p>
      <h1 class="font-serif text-[28px] font-normal mb-1" style="color: var(--color-ink)">
        {{ meta?.pastoral_nome || 'Pastoral' }}
      </h1>
      <p v-if="meta" class="text-sm mb-6" style="color: var(--color-muted)">
        Ano {{ meta.ano }} · {{ labelStatusPlanejamento(meta.status) }}
      </p>

      <p v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</p>
      <p v-else-if="error" class="text-sm" style="color: #8a2424" data-testid="planejamento-publico-erro">
        {{ error }}
      </p>

      <template v-else>
        <section class="card p-5 mb-6 space-y-3" data-testid="planejamento-publico-form">
          <h2 class="font-serif text-lg" style="color: var(--color-ink)">Nova proposta</h2>
          <p v-if="meta?.status !== 'coleta'" class="text-sm" style="color: var(--color-muted)">
            A coleta não está aberta neste momento. Peça ao padre para liberar o status
            <strong>Coleta</strong>.
          </p>
          <form v-else class="space-y-3" @submit.prevent="criar">
            <div>
              <label class="fld" for="pub-titulo">Título</label>
              <input id="pub-titulo" v-model="form.titulo" class="input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="fld" for="pub-data">Data</label>
                <input id="pub-data" v-model="form.data_inicio" type="date" class="input" required />
              </div>
              <div>
                <label class="fld" for="pub-hora">Hora</label>
                <input id="pub-hora" v-model="form.hora_inicio" type="time" class="input" />
              </div>
            </div>
            <div>
              <label class="fld" for="pub-local">Local</label>
              <input id="pub-local" v-model="form.local_texto" class="input" required placeholder="Ex.: Salão paroquial" />
            </div>
            <div>
              <label class="fld" for="pub-obs">Observações</label>
              <textarea id="pub-obs" v-model="form.observacoes" class="input" rows="2" />
            </div>
            <button type="submit" class="btn btn-primary w-full" :disabled="saving">
              {{ saving ? 'Enviando…' : 'Enviar proposta' }}
            </button>
          </form>
        </section>

        <section>
          <h2
            class="text-[11px] font-semibold uppercase tracking-wider mb-3"
            style="color: var(--color-accent-dark)"
          >
            Suas propostas
          </h2>
          <ul class="space-y-2 list-none p-0 m-0">
            <li v-for="ev in eventos" :key="ev.id" class="card p-4">
              <div class="font-medium text-sm" style="color: var(--color-ink)">{{ ev.titulo }}</div>
              <div class="text-xs mt-1" style="color: var(--color-muted)">
                {{ String(ev.data_inicio || '').slice(0, 10) }}
                <template v-if="ev.hora_inicio"> · {{ String(ev.hora_inicio).slice(0, 5) }}</template>
                <template v-if="ev.local_texto"> · {{ ev.local_texto }}</template>
              </div>
              <span
                class="inline-block mt-2 text-[11px] font-medium px-2 py-0.5 rounded-full"
                :style="{
                  background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                  color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                }"
              >
                {{ labelStatusSolicitacao(ev.status_solicitacao) }}
              </span>
            </li>
          </ul>
          <p v-if="!eventos.length" class="text-sm" style="color: var(--color-muted)">
            Nenhuma proposta ainda.
          </p>
        </section>
      </template>
    </div>
  </div>
</template>
