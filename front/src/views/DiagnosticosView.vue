<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'

const loading = ref(false)
const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const filters = ref({
  motivo: '',
  q: '',
  page: 1,
})
const selected = ref(null)
const loadingDetail = ref(false)

const motivoLabels = {
  foto: 'Foto',
  geral: 'Geral',
  pwa: 'PWA',
  outro: 'Outro',
}

function formatDate(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('pt-BR')
  } catch {
    return iso
  }
}

async function load() {
  loading.value = true
  try {
    const params = { page: filters.value.page, per_page: 25 }
    if (filters.value.motivo) params.motivo = filters.value.motivo
    if (filters.value.q) params.q = filters.value.q
    const res = await api.get('/client-diagnostics', { params })
    rows.value = res.data.data || []
    meta.value = res.data.meta || {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      total: res.data.total || rows.value.length,
    }
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar diagnósticos')
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function openDetail(row) {
  selected.value = row
  loadingDetail.value = true
  try {
    const res = await api.get(`/client-diagnostics/${row.id}`)
    selected.value = res.data.data || row
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao abrir detalhe')
  } finally {
    loadingDetail.value = false
  }
}

function applyFilters() {
  filters.value.page = 1
  load()
}

function goPage(page) {
  if (page < 1 || page > (meta.value.last_page || 1)) return
  filters.value.page = page
  load()
}

watch(
  () => filters.value.motivo,
  () => applyFilters(),
)

onMounted(load)
</script>

<template>
  <div class="p-4 md:p-6 max-w-6xl mx-auto" data-testid="diagnosticos-view">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold" style="font-family: Newsreader, Georgia, serif; color: #2A1418">
        Diagnósticos do app
      </h1>
      <p class="text-sm mt-1" style="color: rgba(42, 20, 24, 0.65)">
        Envios voluntários do menu do perfil (versão, PWA, permissões e cliques na foto).
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
          placeholder="Usuário, nota ou id…"
          data-testid="diagnosticos-busca"
          @keyup.enter="applyFilters"
        >
      </label>
      <label class="flex flex-col gap-1 text-sm md:w-44">
        <span class="font-medium" style="color: #2A1418">Motivo</span>
        <select
          v-model="filters.motivo"
          class="rounded-lg border px-3 py-2 bg-white"
          style="border-color: rgba(42, 20, 24, 0.18)"
          data-testid="diagnosticos-filtro-motivo"
        >
          <option value="">Todos</option>
          <option value="foto">Foto</option>
          <option value="pwa">PWA</option>
          <option value="geral">Geral</option>
          <option value="outro">Outro</option>
        </select>
      </label>
      <div class="flex items-end">
        <button
          type="button"
          class="btn btn-primary"
          data-testid="diagnosticos-filtrar"
          @click="applyFilters"
        >
          Filtrar
        </button>
      </div>
    </div>

    <div
      v-if="loading"
      class="text-sm"
      style="color: rgba(42, 20, 24, 0.65)"
    >
      Carregando…
    </div>

    <div
      v-else
      class="overflow-x-auto rounded-xl border bg-white"
      style="border-color: rgba(42, 20, 24, 0.12)"
    >
      <table class="w-full text-sm text-left" data-testid="diagnosticos-tabela">
        <thead>
          <tr style="color: rgba(42, 20, 24, 0.55)">
            <th class="px-3 py-2 font-medium">Quando</th>
            <th class="px-3 py-2 font-medium">Usuário</th>
            <th class="px-3 py-2 font-medium">Motivo</th>
            <th class="px-3 py-2 font-medium">Versão</th>
            <th class="px-3 py-2 font-medium">PWA</th>
            <th class="px-3 py-2 font-medium">Câmera</th>
            <th class="px-3 py-2 font-medium">Eventos</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="border-t cursor-pointer hover:bg-[rgba(42,20,24,0.03)]"
            style="border-color: rgba(42, 20, 24, 0.08)"
            data-testid="diagnosticos-linha"
            @click="openDetail(row)"
          >
            <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.created_at) }}</td>
            <td class="px-3 py-2">{{ row.user?.name || '—' }}</td>
            <td class="px-3 py-2">{{ motivoLabels[row.motivo] || row.motivo }}</td>
            <td class="px-3 py-2 font-mono text-[12px]">{{ row.meta?.app_version || '—' }}</td>
            <td class="px-3 py-2">{{ row.meta?.standalone ? 'Sim' : 'Não' }}</td>
            <td class="px-3 py-2">{{ row.meta?.camera_permission || '—' }}</td>
            <td class="px-3 py-2">{{ row.events_count ?? row.events?.length ?? 0 }}</td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="7" class="px-3 py-6 text-center" style="color: rgba(42, 20, 24, 0.55)">
              Nenhum diagnóstico enviado ainda.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta.last_page > 1"
      class="flex items-center justify-between mt-3 text-sm"
    >
      <button type="button" class="btn btn-ghost" @click="goPage(filters.page - 1)">Anterior</button>
      <span style="color: rgba(42, 20, 24, 0.65)">
        Página {{ meta.current_page }} de {{ meta.last_page }}
      </span>
      <button type="button" class="btn btn-ghost" @click="goPage(filters.page + 1)">Próxima</button>
    </div>

    <div
      v-if="selected"
      class="fixed inset-0 z-40 flex items-end md:items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.35)"
      data-testid="diagnosticos-detalhe"
      @click.self="selected = null"
    >
      <div
        class="w-full max-w-2xl max-h-[85vh] overflow-auto rounded-xl p-4 shadow-lg"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.12)"
      >
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h2 class="text-lg font-medium m-0" style="color: #2A1418">
              Diagnóstico
            </h2>
            <p class="text-[12px] m-0 mt-1" style="color: rgba(42, 20, 24, 0.55)">
              {{ selected.id }} · {{ formatDate(selected.created_at) }}
            </p>
          </div>
          <button type="button" class="btn btn-ghost" @click="selected = null">Fechar</button>
        </div>

        <p v-if="loadingDetail" class="text-sm" style="color: rgba(42, 20, 24, 0.55)">Carregando…</p>
        <template v-else>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm mb-4">
            <div>
              <dt class="text-[11px] uppercase" style="color: rgba(42, 20, 24, 0.5)">Usuário</dt>
              <dd class="m-0">{{ selected.user?.name || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[11px] uppercase" style="color: rgba(42, 20, 24, 0.5)">Motivo</dt>
              <dd class="m-0">{{ motivoLabels[selected.motivo] || selected.motivo }}</dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-[11px] uppercase" style="color: rgba(42, 20, 24, 0.5)">Nota</dt>
              <dd class="m-0">{{ selected.nota || '—' }}</dd>
            </div>
          </dl>

          <h3 class="text-sm font-medium mb-1" style="color: #2A1418">Meta</h3>
          <pre
            class="text-[11px] p-3 rounded-lg overflow-auto mb-4"
            style="background: #F3EDE6; color: #2A1418"
            data-testid="diagnosticos-meta"
          >{{ JSON.stringify(selected.meta || {}, null, 2) }}</pre>

          <h3 class="text-sm font-medium mb-1" style="color: #2A1418">Eventos</h3>
          <pre
            class="text-[11px] p-3 rounded-lg overflow-auto"
            style="background: #F3EDE6; color: #2A1418"
            data-testid="diagnosticos-events"
          >{{ JSON.stringify(selected.events || [], null, 2) }}</pre>
        </template>
      </div>
    </div>
  </div>
</template>
