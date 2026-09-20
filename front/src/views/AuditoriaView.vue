<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import {
  AUDIT_ACTION_LABELS,
  auditActionLabel,
  auditTypeLabel,
  auditDiffSummary,
  formatAuditDate,
} from '@/utils/auditLabels'

const loading = ref(false)
const logs = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const filters = ref({
  action: '',
  auditable_type: '',
  q: '',
  page: 1,
})
const selected = ref(null)

const actionOptions = computed(() =>
  Object.entries(AUDIT_ACTION_LABELS).map(([value, label]) => ({ value, label })),
)

const load = async () => {
  loading.value = true
  try {
    const params = { page: filters.value.page, per_page: 25 }
    if (filters.value.action) params.action = filters.value.action
    if (filters.value.auditable_type) params.auditable_type = filters.value.auditable_type
    if (filters.value.q) params.q = filters.value.q

    const res = await api.get('/audit-logs', { params })
    logs.value = res.data.data || []
    meta.value = res.data.meta || {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      total: res.data.total || logs.value.length,
    }
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar auditoria')
    logs.value = []
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  filters.value.page = 1
  load()
}

const goPage = (page) => {
  if (page < 1 || page > (meta.value.last_page || 1)) return
  filters.value.page = page
  load()
}

watch(
  () => filters.value.action,
  () => applyFilters(),
)

onMounted(load)
</script>

<template>
  <div class="p-4 md:p-6 max-w-6xl mx-auto" data-testid="auditoria-view">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold" style="font-family: Newsreader, Georgia, serif; color: #2A1418">
        Auditoria
      </h1>
      <p class="text-sm mt-1" style="color: rgba(42, 20, 24, 0.65)">
        Quem criou, editou ou excluiu cadastros — e registros de login.
      </p>
    </header>

    <div class="flex flex-col md:flex-row gap-3 mb-4">
      <label class="flex flex-col gap-1 text-sm flex-1">
        <span class="font-medium" style="color: #2A1418">Busca</span>
        <input
          v-model="filters.q"
          type="search"
          class="rounded-lg border px-3 py-2 bg-white"
          style="border-color: rgba(42, 20, 24, 0.18)"
          placeholder="Ator ou recurso…"
          data-testid="auditoria-busca"
          @keyup.enter="applyFilters"
        />
      </label>
      <label class="flex flex-col gap-1 text-sm md:w-48">
        <span class="font-medium" style="color: #2A1418">Ação</span>
        <select
          v-model="filters.action"
          class="rounded-lg border px-3 py-2 bg-white"
          style="border-color: rgba(42, 20, 24, 0.18)"
          data-testid="auditoria-filtro-acao"
        >
          <option value="">Todas</option>
          <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </label>
      <label class="flex flex-col gap-1 text-sm md:w-40">
        <span class="font-medium" style="color: #2A1418">Tipo</span>
        <select
          v-model="filters.auditable_type"
          class="rounded-lg border px-3 py-2 bg-white"
          style="border-color: rgba(42, 20, 24, 0.18)"
          data-testid="auditoria-filtro-tipo"
          @change="applyFilters"
        >
          <option value="">Todos</option>
          <option value="User">Usuário</option>
          <option value="Pessoa">Pessoa</option>
          <option value="Igreja">Igreja</option>
          <option value="Casal">Casal</option>
          <option value="EccEquipe">Equipe ECC</option>
          <option value="EccEvento">Evento ECC</option>
        </select>
      </label>
      <div class="flex items-end">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium text-white"
          style="background: #6B1C2B"
          data-testid="auditoria-filtrar"
          @click="applyFilters"
        >
          Filtrar
        </button>
      </div>
    </div>

    <div
      v-if="loading"
      class="py-12 text-center text-sm"
      style="color: rgba(42, 20, 24, 0.55)"
    >
      Carregando…
    </div>

    <div v-else class="overflow-x-auto rounded-xl border" style="border-color: rgba(42, 20, 24, 0.12)">
      <table class="w-full text-sm text-left" data-testid="auditoria-tabela">
        <thead style="background: #F7F4EF; color: #2A1418">
          <tr>
            <th class="px-3 py-2.5 font-medium">Quando</th>
            <th class="px-3 py-2.5 font-medium">Ação</th>
            <th class="px-3 py-2.5 font-medium">Quem</th>
            <th class="px-3 py-2.5 font-medium">Recurso</th>
            <th class="px-3 py-2.5 font-medium">Campos</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="logs.length === 0">
            <td colspan="5" class="px-3 py-8 text-center" style="color: rgba(42, 20, 24, 0.5)">
              Nenhum evento encontrado.
            </td>
          </tr>
          <tr
            v-for="log in logs"
            :key="log.id"
            class="border-t cursor-pointer hover:bg-[#F7F4EF]/60"
            style="border-color: rgba(42, 20, 24, 0.08)"
            data-testid="auditoria-linha"
            @click="selected = log"
          >
            <td class="px-3 py-2.5 whitespace-nowrap" style="color: rgba(42, 20, 24, 0.75)">
              {{ formatAuditDate(log.created_at) }}
            </td>
            <td class="px-3 py-2.5 font-medium" style="color: #4E1220">
              {{ auditActionLabel(log.action) }}
            </td>
            <td class="px-3 py-2.5">
              {{ log.actor_label || (log.actor_type === 'anonymous' ? 'Anônimo' : '—') }}
            </td>
            <td class="px-3 py-2.5">
              <span style="color: rgba(42, 20, 24, 0.55)">{{ auditTypeLabel(log.auditable_type) }}</span>
              <span v-if="log.auditable_label" class="ml-1">{{ log.auditable_label }}</span>
            </td>
            <td class="px-3 py-2.5" style="color: rgba(42, 20, 24, 0.6)">
              {{ auditDiffSummary(log) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta.last_page > 1"
      class="flex items-center justify-between mt-4 text-sm"
      style="color: rgba(42, 20, 24, 0.7)"
    >
      <span>Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }})</span>
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-1 rounded border bg-white disabled:opacity-40"
          style="border-color: rgba(42, 20, 24, 0.18)"
          :disabled="meta.current_page <= 1"
          @click="goPage(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          type="button"
          class="px-3 py-1 rounded border bg-white disabled:opacity-40"
          style="border-color: rgba(42, 20, 24, 0.18)"
          :disabled="meta.current_page >= meta.last_page"
          @click="goPage(meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>

    <div
      v-if="selected"
      class="fixed inset-0 z-40 flex items-end md:items-center justify-center bg-black/40 p-4"
      data-testid="auditoria-detalhe"
      @click.self="selected = null"
    >
      <div
        class="w-full max-w-lg rounded-xl p-5 max-h-[80vh] overflow-y-auto"
        style="background: #FFFDFA; color: #2A1418"
      >
        <div class="flex justify-between items-start gap-3 mb-4">
          <h2 class="text-lg font-semibold" style="font-family: Newsreader, Georgia, serif">
            {{ auditActionLabel(selected.action) }}
          </h2>
          <button type="button" class="text-sm underline" @click="selected = null">Fechar</button>
        </div>
        <dl class="space-y-2 text-sm">
          <div>
            <dt class="opacity-60">Quando</dt>
            <dd>{{ formatAuditDate(selected.created_at) }}</dd>
          </div>
          <div>
            <dt class="opacity-60">Quem</dt>
            <dd>{{ selected.actor_label || selected.actor_type }}</dd>
          </div>
          <div>
            <dt class="opacity-60">Recurso</dt>
            <dd>
              {{ auditTypeLabel(selected.auditable_type) }}
              <span v-if="selected.auditable_label"> · {{ selected.auditable_label }}</span>
            </dd>
          </div>
          <div v-if="selected.ip_address">
            <dt class="opacity-60">IP</dt>
            <dd>{{ selected.ip_address }}</dd>
          </div>
          <div v-if="selected.old_values && Object.keys(selected.old_values).length">
            <dt class="opacity-60 mb-1">Antes</dt>
            <dd>
              <pre class="text-xs p-2 rounded overflow-x-auto" style="background: #F7F4EF">{{
                JSON.stringify(selected.old_values, null, 2)
              }}</pre>
            </dd>
          </div>
          <div v-if="selected.new_values && Object.keys(selected.new_values).length">
            <dt class="opacity-60 mb-1">Depois</dt>
            <dd>
              <pre class="text-xs p-2 rounded overflow-x-auto" style="background: #F7F4EF">{{
                JSON.stringify(selected.new_values, null, 2)
              }}</pre>
            </dd>
          </div>
          <div v-if="selected.metadata && Object.keys(selected.metadata).length">
            <dt class="opacity-60 mb-1">Metadados</dt>
            <dd>
              <pre class="text-xs p-2 rounded overflow-x-auto" style="background: #F7F4EF">{{
                JSON.stringify(selected.metadata, null, 2)
              }}</pre>
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
