<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import { buildMonthGrid, groupOcorrenciasByDay, toDayKey } from '@/utils/escalas'
import {
  filterEventos,
  groupEventosByMonth,
  tipoAbrev,
  tipoCor,
  tipoLabel,
} from '@/utils/eccEventos'

const props = defineProps({
  /** ecc | geral */
  origem: { type: String, default: 'ecc' },
  /** Base path API */
  apiBase: { type: String, default: '/ecc/eventos' },
  /** escopo dos tipos: ecc | geral */
  tiposEscopo: { type: String, default: 'ecc' },
  detailRouteName: { type: String, default: 'ecc-evento-detail' },
  pageTitle: { type: String, default: 'Eventos' },
  pageSubtitle: {
    type: String,
    default: 'Encontros, jornada anual, reuniões de servos e de perseverança.',
  },
})

const router = useRouter()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()

const authOpts = () => ({ isSuperAdmin: authAdminStore.isAuthenticated })
const canManage = computed(() =>
  userHasPermission(authStore.user, 'ecc.eventos.manage', authOpts()),
)

const subEv = ref('lista')
const filtroTipoId = ref('')
const search = ref('')
const eventos = ref([])
const tipos = ref([])
const loading = ref(false)
const cursor = ref(new Date())
const selDia = ref(null)
const showForm = ref(false)
const showTipoForm = ref(false)
const saving = ref(false)
const savingTipo = ref(false)
const form = ref({
  titulo: '',
  evento_tipo_id: '',
  data: '',
  hora: '20:00',
  local: '',
})
const tipoForm = ref({
  nome: '',
  abrev: '',
  cor: '#6B1C2B',
  permite_compras: false,
})

const year = computed(() => cursor.value.getFullYear())
const monthIndex = computed(() => cursor.value.getMonth())
const monthLabel = computed(() =>
  cursor.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }),
)

const range = computed(() => {
  const start = new Date(year.value, monthIndex.value, 1)
  const end = new Date(year.value, monthIndex.value + 1, 0, 23, 59, 59)
  return { from: start.toISOString(), to: end.toISOString() }
})

const filtrados = computed(() =>
  filterEventos(eventos.value, {
    tipoId: filtroTipoId.value,
    search: search.value,
    tipos: tipos.value,
  }),
)

const gruposLista = computed(() => groupEventosByMonth(filtrados.value))
const byDay = computed(() => groupOcorrenciasByDay(filtrados.value))
const weeks = computed(() => buildMonthGrid(year.value, monthIndex.value))
const todayKey = computed(() => toDayKey(new Date()))

const listaDiaOuMes = computed(() => {
  if (selDia.value) return byDay.value.get(selDia.value) || []
  return filtrados.value
})

async function loadTipos() {
  try {
    const { data } = await api.get('/eventos/tipos', {
      params: { escopo: props.tiposEscopo },
    })
    tipos.value = data?.data || data || []
  } catch {
    tipos.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const params =
      subEv.value === 'calendario'
        ? { from: range.value.from, to: range.value.to }
        : {}
    if (filtroTipoId.value) params.tipo_id = filtroTipoId.value
    const { data } = await api.get(props.apiBase, { params })
    eventos.value = data?.data || data || []
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao carregar eventos.')
    eventos.value = []
  } finally {
    loading.value = false
  }
}

function prevMonth() {
  cursor.value = new Date(year.value, monthIndex.value - 1, 1)
  selDia.value = null
}
function nextMonth() {
  cursor.value = new Date(year.value, monthIndex.value + 1, 1)
  selDia.value = null
}

function formatWhen(iso) {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function abrirEvento(ev) {
  router.push({ name: props.detailRouteName, params: { id: ev.id } })
}

/**
 * @param {string} [dateKey] YYYY-MM-DD — data pré-preenchida (ex.: clique no calendário)
 */
function openNovo(dateKey) {
  if (!canManage.value) return
  form.value = {
    titulo: '',
    evento_tipo_id: filtroTipoId.value || tipos.value[0]?.id || '',
    data: typeof dateKey === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateKey) ? dateKey : '',
    hora: '20:00',
    local: '',
  }
  showForm.value = true
}

function onCalCellClick(cell) {
  if (!cell) return
  selDia.value = selDia.value === cell.key ? null : cell.key
}

function onCalCellDblClick(cell) {
  if (!cell || !canManage.value) return
  selDia.value = cell.key
  openNovo(cell.key)
}

function closeForm() {
  showForm.value = false
}

async function salvar() {
  if (!form.value.titulo || !form.value.data || !form.value.evento_tipo_id) {
    innovToast('error', 'Validação', 'Título, tipo e data são obrigatórios.')
    return
  }
  saving.value = true
  try {
    const inicia = `${form.value.data}T${form.value.hora || '00:00'}:00`
    await api.post(props.apiBase, {
      titulo: form.value.titulo,
      evento_tipo_id: form.value.evento_tipo_id,
      origem: props.origem,
      inicia_em: new Date(inicia).toISOString(),
      local: form.value.local || null,
    })
    closeForm()
    innovToast('success', 'OK', 'Evento criado.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao salvar.')
  } finally {
    saving.value = false
  }
}

async function salvarTipo() {
  if (!tipoForm.value.nome) {
    innovToast('error', 'Validação', 'Informe o nome do tipo.')
    return
  }
  savingTipo.value = true
  try {
    await api.post('/eventos/tipos', {
      nome: tipoForm.value.nome,
      abrev: tipoForm.value.abrev || undefined,
      cor: tipoForm.value.cor,
      permite_compras: tipoForm.value.permite_compras,
      escopo: props.tiposEscopo === 'geral' ? 'geral' : 'ambos',
    })
    showTipoForm.value = false
    tipoForm.value = { nome: '', abrev: '', cor: '#6B1C2B', permite_compras: false }
    innovToast('success', 'OK', 'Tipo cadastrado.')
    await loadTipos()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao criar tipo.')
  } finally {
    savingTipo.value = false
  }
}

watch([subEv, cursor, filtroTipoId], () => {
  if (subEv.value === 'calendario' || filtroTipoId.value !== undefined) load()
})

onMounted(async () => {
  await loadTipos()
  await load()
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="ecc-eventos-page">
    <div class="mb-6">
      <p class="text-xs font-semibold uppercase tracking-wide" style="color: #B4703F">Agenda</p>
      <h1 class="text-2xl font-semibold mt-1" style="font-family: Newsreader, Georgia, serif; color: #2A1418">
        {{ pageTitle }}
      </h1>
      <p class="text-sm mt-1" style="color: #6B4A50">{{ pageSubtitle }}</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-4" data-testid="ecc-eventos-segmented">
      <button
        type="button"
        class="px-3 py-1.5 text-sm font-semibold rounded-lg border"
        :style="subEv === 'lista' ? { background: '#6B1C2B', color: '#fff', borderColor: '#6B1C2B' } : { background: '#FFFDFA', borderColor: '#E8DFD6', color: '#6B4A50' }"
        @click="subEv = 'lista'"
      >
        Lista
      </button>
      <button
        type="button"
        class="px-3 py-1.5 text-sm font-semibold rounded-lg border"
        :style="subEv === 'calendario' ? { background: '#6B1C2B', color: '#fff', borderColor: '#6B1C2B' } : { background: '#FFFDFA', borderColor: '#E8DFD6', color: '#6B4A50' }"
        @click="subEv = 'calendario'"
      >
        Calendário
      </button>
    </div>

    <div class="flex flex-wrap gap-2 items-center mb-4">
      <button
        v-for="t in tipos"
        :key="t.id"
        type="button"
        class="px-3 py-1 text-xs font-semibold rounded-full border inline-flex items-center gap-1.5"
        :style="filtroTipoId === t.id ? { background: t.cor || '#6B1C2B', color: '#fff', borderColor: t.cor || '#6B1C2B' } : { background: '#FFFDFA', borderColor: '#E8DFD6', color: '#6B4A50' }"
        @click="filtroTipoId = filtroTipoId === t.id ? '' : t.id"
      >
        <span class="w-2 h-2 rounded-full" :style="{ background: filtroTipoId === t.id ? '#fff' : (t.cor || '#6B1C2B') }" />
        {{ t.nome }}
      </button>
      <button
        v-if="canManage"
        type="button"
        class="px-3 py-1 text-xs font-semibold rounded-full border"
        style="border-color: #E8DFD6; color: #6B1C2B; background: #FFFDFA"
        data-testid="ecc-eventos-novo-tipo"
        @click="showTipoForm = true"
      >
        + Tipo
      </button>
      <input
        v-model="search"
        type="search"
        placeholder="Buscar…"
        class="ml-auto border rounded-lg px-3 py-1.5 text-sm min-w-[160px]"
        style="border-color: #E8DFD6; background: #FFFDFA"
        data-testid="ecc-eventos-search"
      />
      <button
        v-if="canManage"
        type="button"
        class="px-3 py-1.5 text-sm font-semibold rounded-lg text-white"
        style="background: #6B1C2B"
        data-testid="ecc-eventos-novo"
        @click="openNovo"
      >
        Novo evento
      </button>
    </div>

    <p v-if="loading" class="text-sm" style="color: #6B4A50">Carregando…</p>

    <template v-else-if="subEv === 'lista'">
      <div v-for="g in gruposLista" :key="g.chave" class="mb-6">
        <div class="text-xs font-semibold uppercase mb-2" style="color: #B4703F">{{ g.label }}</div>
        <div class="rounded-xl border overflow-hidden" style="border-color: #E8DFD6; background: #FFFDFA">
          <button
            v-for="e in g.itens"
            :key="e.id"
            type="button"
            class="w-full text-left flex items-center gap-3 px-4 py-3 border-b last:border-b-0 hover:bg-[#F7F4EF]"
            style="border-color: #E8DFD6"
            @click="abrirEvento(e)"
          >
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: tipoCor(tipos, e.evento_tipo_id || e.tipo) }" />
            <div class="grow min-w-0">
              <div class="font-semibold text-sm" style="color: #2A1418">{{ e.titulo }}</div>
              <div class="text-xs" style="color: #6B4A50">
                {{ formatWhen(e.inicia_em) }}
                <template v-if="e.local"> · {{ e.local }}</template>
                · {{ tipoLabel(tipos, e.evento_tipo_id || e.tipo) }}
              </div>
            </div>
          </button>
        </div>
      </div>
      <p v-if="gruposLista.length === 0" class="text-sm py-8 text-center" style="color: #6B4A50">
        Nenhum evento com esse filtro.
      </p>
    </template>

    <template v-else>
      <div class="flex items-center justify-between mb-3">
        <button type="button" class="px-2 py-1 border rounded-lg" style="border-color: #E8DFD6" @click="prevMonth">‹</button>
        <div class="font-semibold capitalize" style="color: #2A1418">{{ monthLabel }}</div>
        <button type="button" class="px-2 py-1 border rounded-lg" style="border-color: #E8DFD6" @click="nextMonth">›</button>
      </div>
      <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold mb-1" style="color: #6B4A50">
        <div v-for="d in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']" :key="d">{{ d }}</div>
      </div>
      <p v-if="canManage" class="text-xs mb-2" style="color: #6B4A50">
        Duplo clique em um dia para criar evento · ou use o + na célula
      </p>
      <div class="grid grid-cols-7 gap-1 mb-4" data-testid="ecc-eventos-cal-grid">
        <template v-for="(week, wi) in weeks" :key="wi">
          <div
            v-for="(cell, ci) in week"
            :key="ci"
            class="min-h-[88px] lg:min-h-[100px] rounded-lg border p-1.5 text-left relative cursor-pointer"
            :class="{ 'opacity-40': !cell }"
            :style="{
              borderColor: '#E8DFD6',
              background: cell && selDia === cell.key ? '#F5E9DA' : '#FFFDFA',
            }"
            :data-testid="cell ? `ecc-cal-day-${cell.key}` : undefined"
            @click="onCalCellClick(cell)"
            @dblclick="onCalCellDblClick(cell)"
          >
            <template v-if="cell">
              <div class="flex items-center justify-between gap-0.5">
                <div
                  class="text-xs font-semibold w-5 h-5 grid place-items-center rounded-full"
                  :style="cell.key === todayKey ? { background: '#6B1C2B', color: '#fff' } : { color: '#2A1418' }"
                >
                  {{ cell.day }}
                </div>
                <button
                  v-if="canManage"
                  type="button"
                  class="text-[11px] font-bold leading-none w-5 h-5 rounded grid place-items-center shrink-0"
                  style="color: #6B1C2B"
                  title="Novo evento neste dia"
                  data-testid="ecc-cal-add-day"
                  @click.stop="openNovo(cell.key)"
                >
                  +
                </button>
              </div>
              <div
                v-for="ev in (byDay.get(cell.key) || []).slice(0, 2)"
                :key="ev.id"
                class="text-[10px] font-semibold truncate px-1 rounded mt-0.5 text-white cursor-pointer"
                :style="{ background: tipoCor(tipos, ev.evento_tipo_id || ev.tipo) }"
                @click.stop="abrirEvento(ev)"
              >
                {{ tipoAbrev(tipos, ev.evento_tipo_id || ev.tipo) }}
              </div>
            </template>
          </div>
        </template>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <div class="text-xs font-semibold uppercase" style="color: #B4703F">
          {{ selDia ? `Dia ${selDia}` : `Eventos de ${monthLabel}` }}
          <button
            v-if="selDia"
            type="button"
            class="ml-2 text-[11px] font-semibold normal-case"
            style="color: #6B1C2B"
            @click="selDia = null"
          >
            Ver o mês todo
          </button>
        </div>
        <button
          v-if="canManage && selDia"
          type="button"
          class="px-3 py-1.5 text-xs font-semibold rounded-lg text-white"
          style="background: #6B1C2B"
          data-testid="ecc-cal-novo-dia"
          @click="openNovo(selDia)"
        >
          Novo neste dia
        </button>
      </div>
      <div class="rounded-xl border overflow-hidden" style="border-color: #E8DFD6; background: #FFFDFA">
        <button
          v-for="e in listaDiaOuMes"
          :key="e.id"
          type="button"
          class="w-full text-left flex items-center gap-3 px-4 py-3 border-b last:border-b-0"
          style="border-color: #E8DFD6"
          @click="abrirEvento(e)"
        >
          <span class="w-2.5 h-2.5 rounded-full" :style="{ background: tipoCor(tipos, e.evento_tipo_id || e.tipo) }" />
          <div>
            <div class="font-semibold text-sm">{{ e.titulo }}</div>
            <div class="text-xs" style="color: #6B4A50">{{ formatWhen(e.inicia_em) }} · {{ tipoLabel(tipos, e.evento_tipo_id || e.tipo) }}</div>
          </div>
        </button>
        <div v-if="listaDiaOuMes.length === 0" class="px-4 py-6 text-sm text-center" style="color: #6B4A50">
          Nenhum evento neste período.
          <button
            v-if="canManage && selDia"
            type="button"
            class="block mx-auto mt-2 text-sm font-semibold"
            style="color: #6B1C2B"
            @click="openNovo(selDia)"
          >
            Criar evento neste dia
          </button>
        </div>
      </div>
    </template>

    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      data-testid="ecc-eventos-modal"
      @click.self="closeForm"
    >
      <div class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5" style="background: #FFFDFA" data-testid="ecc-eventos-form">
        <h3 class="text-lg font-semibold mb-4" style="font-family: Newsreader, Georgia, serif">Novo evento</h3>
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Título</label>
        <input v-model="form.titulo" class="w-full border rounded-lg px-3 py-2 mb-3 text-sm" style="border-color: #E8DFD6" />
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Tipo</label>
        <select v-model="form.evento_tipo_id" class="w-full border rounded-lg px-3 py-2 mb-3 text-sm" style="border-color: #E8DFD6">
          <option value="" disabled>Selecione…</option>
          <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
        </select>
        <div class="flex gap-2 mb-3">
          <div class="flex-1">
            <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Data</label>
            <input v-model="form.data" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" style="border-color: #E8DFD6" />
          </div>
          <div class="flex-1">
            <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Hora</label>
            <input v-model="form.hora" type="time" class="w-full border rounded-lg px-3 py-2 text-sm" style="border-color: #E8DFD6" />
          </div>
        </div>
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Local</label>
        <input v-model="form.local" class="w-full border rounded-lg px-3 py-2 mb-4 text-sm" style="border-color: #E8DFD6" />
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-2 text-sm font-semibold" @click="closeForm">Cancelar</button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold rounded-lg text-white"
            style="background: #6B1C2B"
            :disabled="saving"
            data-testid="ecc-eventos-salvar"
            @click="salvar"
          >
            Salvar
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showTipoForm"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      @click.self="showTipoForm = false"
    >
      <div class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5" style="background: #FFFDFA">
        <h3 class="text-lg font-semibold mb-4" style="font-family: Newsreader, Georgia, serif">Novo tipo</h3>
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Nome</label>
        <input v-model="tipoForm.nome" class="w-full border rounded-lg px-3 py-2 mb-3 text-sm" style="border-color: #E8DFD6" />
        <div class="flex gap-2 mb-3">
          <div class="flex-1">
            <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Abreviação</label>
            <input v-model="tipoForm.abrev" class="w-full border rounded-lg px-3 py-2 text-sm" style="border-color: #E8DFD6" />
          </div>
          <div class="w-28">
            <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Cor</label>
            <input v-model="tipoForm.cor" type="color" class="w-full h-10 border rounded-lg" style="border-color: #E8DFD6" />
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm mb-4" style="color: #2A1418">
          <input v-model="tipoForm.permite_compras" type="checkbox" />
          Permite lista de compras
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-2 text-sm font-semibold" @click="showTipoForm = false">Cancelar</button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold rounded-lg text-white"
            style="background: #6B1C2B"
            :disabled="savingTipo"
            @click="salvarTipo"
          >
            Salvar tipo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
