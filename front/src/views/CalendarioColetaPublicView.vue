<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import {
  gradeMesCalendario,
  labelDiaCurto,
  labelMesCalendario,
  MES_NOMES,
  toggleDataSelecionada,
} from '@/utils/calendario'

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const route = useRoute()
const tenantStore = useTenantStore()
const token = computed(() => String(route.params.token || ''))
const tenant = computed(() => String(route.query.tenant || ''))

const loading = ref(true)
const error = ref('')
const info = ref(null)
const nome = ref('')
const motivo = ref('')
const datas = ref([])
const enviado = ref(false)

const selectedSet = computed(() => new Set(datas.value))
const cells = computed(() => {
  if (!info.value) return []
  return gradeMesCalendario(info.value.ano, info.value.mes)
})
const mesTitulo = computed(() => {
  if (!info.value) return ''
  return `${MES_NOMES[info.value.mes] || info.value.mes} ${info.value.ano}`
})

function ensureTenant() {
  if (tenant.value && tenantStore.slug !== tenant.value) {
    tenantStore.select({ slug: tenant.value, name: tenantStore.name || '' })
  }
}

async function load() {
  loading.value = true
  error.value = ''
  ensureTenant()
  try {
    const { data } = await api.get(`public/calendario/coleta/${token.value}`, {
      headers: { 'X-Tenant': tenant.value || tenantStore.slug },
    })
    info.value = data.data || data
  } catch (e) {
    error.value = e.response?.data?.message || 'Link inválido ou expirado.'
  } finally {
    loading.value = false
  }
}

function toggleDia(iso) {
  if (!iso) return
  datas.value = toggleDataSelecionada(datas.value, iso)
}

function removerDia(iso) {
  datas.value = toggleDataSelecionada(datas.value, iso)
}

async function enviar() {
  error.value = ''
  ensureTenant()
  try {
    await api.post(
      `public/calendario/coleta/${token.value}`,
      {
        nome_exibicao: nome.value,
        datas: datas.value,
        motivo: motivo.value || null,
      },
      { headers: { 'X-Tenant': tenant.value || tenantStore.slug } },
    )
    enviado.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Falha ao enviar.'
  }
}

onMounted(load)
</script>

<template>
  <div
    class="min-h-screen px-4 py-8 md:py-12"
    style="background: var(--color-bg)"
    data-testid="calendario-coleta-publica"
  >
    <div class="max-w-md mx-auto space-y-5">
      <header class="text-center space-y-1">
        <p class="page-eyebrow">Calendário oficial</p>
        <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
          Indisponibilidades
        </h1>
        <p v-if="info" class="text-[14px]" style="color: var(--color-muted)">
          Toque nos dias em que você <strong style="color: var(--color-ink)">não pode</strong>
          celebrar em {{ labelMesCalendario(info.ano, info.mes) }}.
        </p>
      </header>

      <div v-if="loading" class="card p-6 text-sm text-center" style="color: var(--color-muted)">
        Carregando…
      </div>

      <div
        v-else-if="error"
        class="card p-6 text-sm text-center"
        style="color: var(--color-danger)"
        role="alert"
      >
        {{ error }}
      </div>

      <div
        v-else-if="enviado"
        class="card p-8 text-center space-y-2"
        data-testid="coleta-sucesso"
      >
        <p class="font-serif text-xl" style="color: var(--color-ok)">Obrigado!</p>
        <p class="text-sm" style="color: var(--color-muted)">
          Suas datas foram registradas.
        </p>
      </div>

      <template v-else-if="info">
        <div class="card p-4 sm:p-5 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-serif text-lg font-medium" style="color: var(--color-ink)">
              {{ mesTitulo }}
            </h2>
            <span
              class="text-xs font-medium px-2.5 py-1 rounded-md"
              style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
              data-testid="coleta-contador"
            >
              {{ datas.length }} {{ datas.length === 1 ? 'dia' : 'dias' }}
            </span>
          </div>

          <div
            class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase tracking-wide"
            style="color: var(--color-muted)"
            aria-hidden="true"
          >
            <span v-for="w in WEEKDAYS" :key="w" class="py-1">{{ w }}</span>
          </div>

          <div
            class="grid grid-cols-7 gap-1.5"
            role="grid"
            :aria-label="`Calendário de ${mesTitulo}`"
            data-testid="coleta-grade"
          >
            <template v-for="(cell, idx) in cells" :key="cell.iso || `pad-${idx}`">
              <span v-if="!cell.inMonth" class="aspect-square" aria-hidden="true" />
              <button
                v-else
                type="button"
                class="aspect-square rounded-lg text-sm font-medium transition-all duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                :class="selectedSet.has(cell.iso) ? 'coleta-dia-on' : 'coleta-dia-off'"
                :aria-pressed="selectedSet.has(cell.iso)"
                :aria-label="`${cell.dia} de ${mesTitulo}${selectedSet.has(cell.iso) ? ', selecionado' : ''}`"
                :data-testid="`coleta-dia-${cell.dia}`"
                @click="toggleDia(cell.iso)"
              >
                {{ cell.dia }}
              </button>
            </template>
          </div>

          <p class="text-xs text-center" style="color: var(--color-muted)">
            Dias marcados = indisponível
          </p>

          <div v-if="datas.length" class="flex flex-wrap gap-2" data-testid="coleta-chips">
            <button
              v-for="d in datas"
              :key="d"
              type="button"
              class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md transition-opacity hover:opacity-80"
              style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
              :aria-label="`Remover ${labelDiaCurto(d)}`"
              @click="removerDia(d)"
            >
              {{ labelDiaCurto(d) }}
              <span aria-hidden="true">×</span>
            </button>
          </div>
        </div>

        <div class="card p-4 sm:p-5 space-y-4">
          <div>
            <label class="fld" for="coleta-nome">Seu nome</label>
            <input
              id="coleta-nome"
              v-model="nome"
              class="input"
              autocomplete="name"
              placeholder="Como aparece na escala"
              data-testid="coleta-nome"
            />
          </div>
          <div>
            <label class="fld" for="coleta-motivo">Motivo (opcional)</label>
            <input
              id="coleta-motivo"
              v-model="motivo"
              class="input"
              placeholder="Ex.: viagem, retiro…"
            />
          </div>
          <button
            type="button"
            class="btn btn-primary w-full justify-center"
            data-testid="coleta-enviar"
            :disabled="!nome.trim() || !datas.length"
            @click="enviar"
          >
            Enviar indisponibilidades
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.coleta-dia-off {
  background: var(--color-surface-2);
  color: var(--color-ink);
  border: 1px solid transparent;
}
.coleta-dia-off:hover {
  border-color: var(--color-line);
  background: var(--color-surface);
}
.coleta-dia-on {
  background: var(--color-primary-soft);
  color: #fff;
  box-shadow: 0 1px 2px rgba(42, 20, 24, 0.18);
  transform: scale(1.02);
}
.coleta-dia-on:hover {
  background: var(--color-primary-hover);
}
button:focus-visible {
  outline-color: var(--color-primary-soft);
}
</style>
