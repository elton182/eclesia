<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { toDatetimeLocal } from '@/utils/calendario'
import { labelAnoStatus, labelEventoStatus, styleEventoStatus, toDayKeyLocal } from '@/utils/planejamento'
import { innovToast } from '@/plugins/toast'

const route = useRoute()
const tenantStore = useTenantStore()
const token = computed(() => String(route.params.token || ''))
const tenant = computed(() => String(route.query.tenant || ''))

const meta = ref(null)
const eventos = ref([])
const loading = ref(true)
const error = ref('')
const form = ref({
  titulo: '',
  descricao: '',
  data_inicio: toDatetimeLocal(toDayKeyLocal(new Date()), '09:00'),
  dia_inteiro: true,
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
  } catch (err) {
    error.value = err.response?.data?.message || 'Link inválido ou expirado.'
  } finally {
    loading.value = false
  }
}

async function criar() {
  try {
    await api.post(
      `/public/planejamento/${token.value}/eventos`,
      {
        titulo: form.value.titulo,
        descricao: form.value.descricao || null,
        data_inicio: new Date(form.value.data_inicio).toISOString(),
        dia_inteiro: form.value.dia_inteiro,
      },
      { headers: headers.value },
    )
    innovToast('success', 'Planejamento', 'Proposta enviada')
    form.value.titulo = ''
    form.value.descricao = ''
    await load()
  } catch (err) {
    innovToast('error', 'Planejamento', err.response?.data?.message || 'Não foi possível enviar')
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
        Ano {{ meta.ano }} · {{ labelAnoStatus(meta.status) }}
      </p>

      <p v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</p>
      <p v-else-if="error" class="text-sm" style="color: #8a2424">{{ error }}</p>

      <template v-else>
        <section class="card p-5 mb-6 space-y-3" data-testid="planejamento-publico-form">
          <h2 class="font-serif text-lg" style="color: var(--color-ink)">Nova proposta</h2>
          <p v-if="meta?.status !== 'coleta'" class="text-sm" style="color: var(--color-muted)">
            A coleta não está aberta neste momento.
          </p>
          <form v-else class="space-y-3" @submit.prevent="criar">
            <div>
              <label class="fld" for="pub-titulo">Título</label>
              <input id="pub-titulo" v-model="form.titulo" class="input" required />
            </div>
            <div>
              <label class="fld" for="pub-desc">Descrição</label>
              <textarea id="pub-desc" v-model="form.descricao" class="input" rows="2" />
            </div>
            <div>
              <label class="fld" for="pub-data">Data</label>
              <input id="pub-data" v-model="form.data_inicio" type="datetime-local" class="input" required />
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.dia_inteiro" type="checkbox" /> Dia inteiro
            </label>
            <button type="submit" class="btn btn-primary w-full">Enviar proposta</button>
          </form>
        </section>

        <section>
          <h2 class="text-[11px] font-semibold uppercase tracking-wider mb-3" style="color: var(--color-accent-dark)">
            Suas propostas
          </h2>
          <ul class="space-y-2">
            <li v-for="ev in eventos" :key="ev.id" class="card p-4">
              <div class="font-medium text-sm" style="color: var(--color-ink)">{{ ev.titulo }}</div>
              <div class="text-xs mt-1" style="color: var(--color-muted)">
                {{ toDayKeyLocal(ev.data_inicio) }}
              </div>
              <span
                class="inline-block mt-2 text-[11px] font-medium px-2 py-0.5 rounded-full"
                :style="{
                  background: styleEventoStatus(ev.status).bg,
                  color: styleEventoStatus(ev.status).fg,
                }"
              >
                {{ labelEventoStatus(ev.status) }}
              </span>
            </li>
          </ul>
          <p v-if="!eventos.length" class="text-sm" style="color: var(--color-muted)">Nenhuma proposta ainda.</p>
        </section>
      </template>
    </div>
  </div>
</template>
