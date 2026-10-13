<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import { buildMonthGrid, toDatetimeLocal } from '@/utils/calendario'
import {
  groupEventosByDay,
  labelAnoStatus,
  labelEventoStatus,
  moveEventoToDay,
  nextAnoStatuses,
  PLANEJAMENTO_ANO_STATUS_STYLE,
  styleEventoStatus,
  toDayKeyLocal,
} from '@/utils/planejamento'
import { innovToast } from '@/plugins/toast'

const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)
const can = (p) =>
  userHasPermission(authTenant.user, p, { isSuperAdmin: isPlatformAdmin.value })
const canManage = computed(() => can('planejamento.gerir') || isPlatformAdmin.value)
const canPropose = computed(() => can('planejamento.propor') || canManage.value)

const anos = ref([])
const anoAtual = ref(null)
const eventos = ref([])
const pastorais = ref([])
const hiddenPastorais = ref(new Set())
const loading = ref(false)
const novoAno = ref(new Date().getFullYear())

const cursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const year = computed(() => cursor.value.getFullYear())
const monthIndex = computed(() => cursor.value.getMonth())
const monthLabel = computed(() =>
  cursor.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }),
)
const weeks = computed(() => buildMonthGrid(year.value, monthIndex.value))
const todayKey = toDayKeyLocal(new Date())

const pastoraisVisiveis = computed(() =>
  pastorais.value.filter((p) => !hiddenPastorais.value.has(p.id)),
)

const eventosFiltrados = computed(() => {
  const allowed = new Set(pastoraisVisiveis.value.map((p) => p.id))
  return eventos.value.filter((e) => allowed.has(e.pastoral_id))
})

const byDay = computed(() => groupEventosByDay(eventosFiltrados.value))

const pastoralNome = (id) => pastorais.value.find((p) => p.id === id)?.nome || 'Pastoral'

const anoStyle = computed(() => {
  const s = anoAtual.value?.status
  return PLANEJAMENTO_ANO_STATUS_STYLE[s] || PLANEJAMENTO_ANO_STATUS_STYLE.aberto
})

const dayModalOpen = ref(false)
const selDia = ref(null)
const form = ref({
  pastoral_id: '',
  titulo: '',
  descricao: '',
  data_inicio: '',
  dia_inteiro: true,
})
const editEvento = ref(null)
const draggingId = ref(null)

const eventosDoDia = computed(() => {
  if (!selDia.value) return []
  return byDay.value.get(selDia.value) || []
})

const podeLancar = computed(() => {
  if (!anoAtual.value) return false
  if (anoAtual.value.status === 'fechado') return false
  if (canManage.value) return anoAtual.value.status !== 'fechado'
  return canPropose.value && anoAtual.value.status === 'coleta'
})

async function loadAnos() {
  const { data } = await api.get('/planejamento/anos')
  anos.value = data.data || data || []
  if (!anoAtual.value && anos.value.length) {
    const civil = new Date().getFullYear()
    anoAtual.value =
      anos.value.find((a) => a.ano === civil) || anos.value[0]
  }
}

async function loadPastorais() {
  try {
    const { data } = await api.get('/site/pastorais')
    pastorais.value = data.data || data || []
  } catch {
    pastorais.value = []
  }
}

async function loadEventos() {
  if (!anoAtual.value?.id) {
    eventos.value = []
    return
  }
  loading.value = true
  try {
    const from = `${year.value}-${String(monthIndex.value + 1).padStart(2, '0')}-01`
    const last = new Date(year.value, monthIndex.value + 1, 0).getDate()
    const to = `${year.value}-${String(monthIndex.value + 1).padStart(2, '0')}-${String(last).padStart(2, '0')}`
    const { data } = await api.get(`/planejamento/anos/${anoAtual.value.id}/eventos`, {
      params: { from, to },
    })
    eventos.value = data.data || data || []
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao carregar eventos')
  } finally {
    loading.value = false
  }
}

async function abrirAno() {
  try {
    const { data } = await api.post('/planejamento/anos', { ano: Number(novoAno.value) })
    const created = data.data || data
    anos.value = [created, ...anos.value]
    anoAtual.value = created
    innovToast('success', 'Planejamento', `Ano ${created.ano} aberto`)
    await loadEventos()
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao abrir ano')
  }
}

async function setStatus(status) {
  if (!anoAtual.value) return
  try {
    const { data } = await api.post(`/planejamento/anos/${anoAtual.value.id}/status`, { status })
    anoAtual.value = data.data || data
    const idx = anos.value.findIndex((a) => a.id === anoAtual.value.id)
    if (idx >= 0) anos.value[idx] = anoAtual.value
    innovToast('success', 'Planejamento', `Status: ${labelAnoStatus(status)}`)
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao mudar status')
  }
}

function selectAno(id) {
  anoAtual.value = anos.value.find((a) => a.id === id) || null
  if (anoAtual.value) {
    cursor.value = new Date(anoAtual.value.ano, cursor.value.getMonth(), 1)
  }
}

function togglePastoral(id) {
  const next = new Set(hiddenPastorais.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  hiddenPastorais.value = next
}

function prevMonth() {
  cursor.value = new Date(year.value, monthIndex.value - 1, 1)
}
function nextMonth() {
  cursor.value = new Date(year.value, monthIndex.value + 1, 1)
}

function openDay(cell) {
  if (!cell) return
  selDia.value = cell.key
  editEvento.value = null
  form.value = {
    pastoral_id: pastorais.value[0]?.id || '',
    titulo: '',
    descricao: '',
    data_inicio: toDatetimeLocal(cell.key, '09:00'),
    dia_inteiro: true,
  }
  dayModalOpen.value = true
}

function openEdit(ev) {
  editEvento.value = ev
  form.value = {
    pastoral_id: ev.pastoral_id,
    titulo: ev.titulo,
    descricao: ev.descricao || '',
    data_inicio: ev.data_inicio
      ? toDatetimeLocal(toDayKeyLocal(ev.data_inicio), new Date(ev.data_inicio).toTimeString().slice(0, 5))
      : '',
    dia_inteiro: !!ev.dia_inteiro,
  }
  dayModalOpen.value = true
}

async function saveEvento() {
  if (!anoAtual.value) return
  const body = {
    pastoral_id: form.value.pastoral_id,
    titulo: form.value.titulo,
    descricao: form.value.descricao || null,
    data_inicio: new Date(form.value.data_inicio).toISOString(),
    dia_inteiro: form.value.dia_inteiro,
  }
  try {
    if (editEvento.value) {
      await api.put(`/planejamento/eventos/${editEvento.value.id}`, body)
      innovToast('success', 'Planejamento', 'Evento atualizado')
    } else {
      await api.post(`/planejamento/anos/${anoAtual.value.id}/eventos`, body)
      innovToast('success', 'Planejamento', 'Evento criado')
    }
    dayModalOpen.value = false
    await loadEventos()
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao salvar')
  }
}

async function patchStatus(ev, status) {
  try {
    await api.patch(`/planejamento/eventos/${ev.id}`, { status })
    await loadEventos()
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao revisar')
  }
}

function onDragStart(ev, e) {
  if (!canManage.value || anoAtual.value?.status === 'fechado') return
  draggingId.value = ev.id
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', ev.id)
}

function onDragOver(e) {
  if (!canManage.value) return
  e.preventDefault()
}

async function onDrop(cell, e) {
  e.preventDefault()
  if (!canManage.value || !cell || anoAtual.value?.status === 'fechado') return
  const id = e.dataTransfer.getData('text/plain') || draggingId.value
  draggingId.value = null
  const ev = eventos.value.find((x) => x.id === id)
  if (!ev) return
  if (toDayKeyLocal(ev.data_inicio) === cell.key) return
  const payload = moveEventoToDay(cell.key, ev)
  try {
    await api.patch(`/planejamento/eventos/${ev.id}`, payload)
    await loadEventos()
  } catch (err) {
    innovToast('error', 'Planejamento', err.response?.data?.message || 'Falha ao mover')
  }
}

watch([anoAtual, cursor], () => loadEventos())

onMounted(async () => {
  await Promise.all([loadAnos(), loadPastorais()])
  await loadEventos()
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="planejamento-view">
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="page-eyebrow">Planejamento</p>
        <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
          Planejamento Anual
        </h1>
        <p class="text-[14px] mt-1 max-w-xl" style="color: var(--color-muted)">
          Pastorais propõem datas; padres revisam e fecham o ano civil.
        </p>
      </div>
      <div v-if="anoAtual" class="flex flex-wrap items-center gap-2">
        <span
          class="text-[12px] font-medium px-2.5 py-1 rounded-full"
          :style="{ background: anoStyle.bg, color: anoStyle.fg }"
          data-testid="planejamento-ano-status"
        >
          {{ anoAtual.ano }} · {{ labelAnoStatus(anoAtual.status) }}
        </span>
        <select
          class="input text-sm"
          data-testid="planejamento-ano-select"
          :value="anoAtual.id"
          @change="selectAno($event.target.value)"
        >
          <option v-for="a in anos" :key="a.id" :value="a.id">{{ a.ano }}</option>
        </select>
        <template v-if="canManage">
          <button
            v-for="s in nextAnoStatuses(anoAtual.status)"
            :key="s"
            type="button"
            class="btn btn-ghost text-xs"
            @click="setStatus(s)"
          >
            {{ labelAnoStatus(s) }}
          </button>
        </template>
      </div>
    </header>

    <section
      v-if="canManage && !anos.length"
      class="border border-dashed rounded-xl p-8 text-center mb-8"
      style="border-color: var(--color-line)"
      data-testid="planejamento-empty"
    >
      <h2 class="font-serif text-[20px] mb-2" style="color: var(--color-ink)">Nenhum ano aberto</h2>
      <p class="text-sm mb-4" style="color: var(--color-muted)">
        Abra o ano civil para liberar o planejamento das pastorais.
      </p>
      <div class="flex flex-wrap justify-center gap-2 items-end">
        <div>
          <label class="fld" for="novo-ano">Ano</label>
          <input id="novo-ano" v-model.number="novoAno" type="number" class="input" min="2000" max="2100" />
        </div>
        <button type="button" class="btn btn-primary" data-testid="planejamento-abrir-ano" @click="abrirAno">
          Abrir ano
        </button>
      </div>
    </section>

    <div v-else-if="anoAtual" class="flex flex-col lg:flex-row gap-6">
      <aside
        class="lg:w-56 shrink-0 card p-4 h-fit"
        data-testid="planejamento-filtros"
      >
        <div class="text-[11px] font-semibold uppercase tracking-wider mb-3" style="color: var(--color-accent-dark)">
          Pastorais
        </div>
        <p v-if="!pastorais.length" class="text-xs" style="color: var(--color-muted)">
          Cadastre pastorais na aba Pastorais deste módulo.
        </p>
        <label
          v-for="p in pastorais"
          :key="p.id"
          class="flex items-center gap-2 text-sm py-1.5 cursor-pointer"
        >
          <input
            type="checkbox"
            :checked="!hiddenPastorais.has(p.id)"
            @change="togglePastoral(p.id)"
          />
          <span class="truncate" style="color: var(--color-ink)">{{ p.nome }}</span>
        </label>
        <div v-if="canManage" class="mt-4 pt-3 border-t" style="border-color: var(--color-line)">
          <label class="fld" for="abrir-outro">Abrir outro ano</label>
          <div class="flex gap-2">
            <input id="abrir-outro" v-model.number="novoAno" type="number" class="input flex-1" min="2000" max="2100" />
            <button type="button" class="btn btn-ghost text-xs" @click="abrirAno">Abrir</button>
          </div>
        </div>
      </aside>

      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between mb-3">
          <button type="button" class="btn btn-ghost px-2 py-1" @click="prevMonth">‹</button>
          <div class="font-serif text-[18px] capitalize" style="color: var(--color-ink)">{{ monthLabel }}</div>
          <button type="button" class="btn btn-ghost px-2 py-1" @click="nextMonth">›</button>
        </div>
        <p v-if="canManage && anoAtual.status !== 'fechado'" class="text-xs mb-2" style="color: var(--color-muted)">
          Clique no dia para abrir · arraste eventos para mudar a data
        </p>
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold mb-1" style="color: var(--color-muted)">
          <div v-for="d in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']" :key="d">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7 gap-1" data-testid="planejamento-cal-grid">
          <template v-for="(week, wi) in weeks" :key="wi">
            <div
              v-for="(cell, ci) in week"
              :key="ci"
              class="min-h-[96px] lg:min-h-[110px] rounded-lg border p-1.5 text-left"
              :class="{ 'opacity-40': !cell }"
              :style="{
                borderColor: 'var(--color-line)',
                background: cell && selDia === cell.key ? 'var(--color-accent-soft)' : 'var(--color-surface)',
              }"
              :data-testid="cell ? `planejamento-day-${cell.key}` : undefined"
              @click="openDay(cell)"
              @dragover="onDragOver"
              @drop="onDrop(cell, $event)"
            >
              <template v-if="cell">
                <div
                  class="text-xs font-semibold w-5 h-5 grid place-items-center rounded-full mb-0.5"
                  :style="
                    cell.key === todayKey
                      ? { background: 'var(--color-primary)', color: 'var(--color-on-primary)' }
                      : { color: 'var(--color-ink)' }
                  "
                >
                  {{ cell.day }}
                </div>
                <div
                  v-for="ev in (byDay.get(cell.key) || []).slice(0, 3)"
                  :key="ev.id"
                  class="text-[10px] font-semibold truncate px-1 rounded mt-0.5 cursor-grab"
                  draggable="true"
                  :style="{
                    background: styleEventoStatus(ev.status).chip,
                    color: '#fff',
                  }"
                  :title="`${ev.titulo} · ${labelEventoStatus(ev.status)}`"
                  @click.stop="openEdit(ev)"
                  @dragstart="onDragStart(ev, $event)"
                >
                  {{ ev.titulo }}
                </div>
                <div
                  v-if="(byDay.get(cell.key) || []).length > 3"
                  class="text-[10px] mt-0.5"
                  style="color: var(--color-muted)"
                >
                  +{{ (byDay.get(cell.key) || []).length - 3 }}
                </div>
              </template>
            </div>
          </template>
        </div>
        <p v-if="loading" class="text-xs mt-2" style="color: var(--color-muted)">Carregando…</p>
      </div>
    </div>

    <div
      v-if="dayModalOpen"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      data-testid="planejamento-day-modal"
      @click.self="dayModalOpen = false"
    >
      <div
        class="w-full max-w-lg rounded-t-2xl md:rounded-2xl p-5 max-h-[90vh] overflow-y-auto"
        style="background: var(--color-surface)"
      >
        <h3 class="font-serif text-lg mb-3" style="color: var(--color-ink)">
          {{ editEvento ? 'Editar evento' : `Dia ${selDia}` }}
        </h3>

        <ul v-if="!editEvento && eventosDoDia.length" class="mb-4 space-y-2">
          <li
            v-for="ev in eventosDoDia"
            :key="ev.id"
            class="card p-3 flex flex-wrap items-start justify-between gap-2"
          >
            <div class="min-w-0">
              <div class="text-sm font-medium" style="color: var(--color-ink)">{{ ev.titulo }}</div>
              <div class="text-xs" style="color: var(--color-muted)">{{ pastoralNome(ev.pastoral_id) }}</div>
              <span
                class="inline-block mt-1 text-[11px] font-medium px-2 py-0.5 rounded-full"
                :style="{
                  background: styleEventoStatus(ev.status).bg,
                  color: styleEventoStatus(ev.status).fg,
                }"
              >
                {{ labelEventoStatus(ev.status) }}
              </span>
            </div>
            <div class="flex flex-wrap gap-1">
              <button type="button" class="btn btn-ghost text-xs" @click="openEdit(ev)">Editar</button>
              <template v-if="canManage && anoAtual?.status !== 'fechado'">
                <button
                  v-for="s in ['em_revisao', 'aprovado', 'rejeitado']"
                  :key="s"
                  type="button"
                  class="btn btn-ghost text-xs"
                  @click="patchStatus(ev, s)"
                >
                  {{ labelEventoStatus(s) }}
                </button>
              </template>
            </div>
          </li>
        </ul>

        <form v-if="podeLancar || editEvento" class="space-y-3" @submit.prevent="saveEvento">
          <div>
            <label class="fld" for="ev-pastoral">Pastoral</label>
            <select id="ev-pastoral" v-model="form.pastoral_id" class="input" required :disabled="!!editEvento && !canManage">
              <option disabled value="">Selecione…</option>
              <option v-for="p in pastorais" :key="p.id" :value="p.id">{{ p.nome }}</option>
            </select>
          </div>
          <div>
            <label class="fld" for="ev-titulo">Título</label>
            <input id="ev-titulo" v-model="form.titulo" class="input" required />
          </div>
          <div>
            <label class="fld" for="ev-desc">Descrição</label>
            <textarea id="ev-desc" v-model="form.descricao" class="input" rows="2" />
          </div>
          <div>
            <label class="fld" for="ev-data">Data / hora</label>
            <input id="ev-data" v-model="form.data_inicio" type="datetime-local" class="input" required />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.dia_inteiro" type="checkbox" />
            Dia inteiro
          </label>
          <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" @click="dayModalOpen = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-testid="planejamento-salvar-evento">
              Salvar
            </button>
          </div>
        </form>
        <p v-else class="text-sm" style="color: var(--color-muted)">
          Ano fechado ou sem permissão para lançar novos eventos.
        </p>
      </div>
    </div>
  </div>
</template>
