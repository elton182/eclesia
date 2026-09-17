<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import { buildMonthGrid, groupOcorrenciasByDay, toDayKey, toDatetimeLocal } from '@/utils/escalas'
import { extractApiError } from '@/utils/tenantAuth'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const canManage = computed(() =>
  userHasPermission(auth.user, 'escalas.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }),
)

const viewMode = ref('calendario')
const tipos = ref([])
const tipoId = ref('')
const ocorrencias = ref([])
const loading = ref(false)
const cursor = ref(new Date())
const showForm = ref(false)
const saving = ref(false)
const form = ref({
  escala_tipo_id: '',
  titulo: '',
  inicia_em: '',
  local: '',
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

const byDay = computed(() => groupOcorrenciasByDay(ocorrencias.value))
const weeks = computed(() => buildMonthGrid(year.value, monthIndex.value))
const todayKey = computed(() => toDayKey(new Date()))

function openCreate(dayKey = '') {
  if (!canManage.value) return
  if (!tipos.value.length) {
    innovToast('error', 'Escalas', 'Crie um tipo de escala antes de adicionar ocorrências.')
    return
  }
  const key = dayKey || todayKey.value
  form.value = {
    escala_tipo_id: tipoId.value || tipos.value[0]?.id || '',
    titulo: '',
    inicia_em: toDatetimeLocal(key),
    local: '',
  }
  showForm.value = true
}

function openCreateOnDay(cell, event) {
  event?.stopPropagation?.()
  if (!cell?.key) return
  openCreate(cell.key)
}

async function loadTipos() {
  try {
    const { data } = await api.get('/escalas/tipos')
    tipos.value = data?.data || data || []
  } catch {
    tipos.value = []
  }
}

async function loadAgenda() {
  loading.value = true
  try {
    const { data } = await api.get('/escalas/agenda', {
      params: {
        from: range.value.from,
        to: range.value.to,
        tipo_id: tipoId.value || undefined,
      },
    })
    ocorrencias.value = data?.data || data || []
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao carregar agenda.'))
  } finally {
    loading.value = false
  }
}

async function createOcorrencia() {
  if (!form.value.escala_tipo_id) {
    innovToast('error', 'Validação', 'Selecione o tipo de escala.')
    return
  }
  if (!form.value.inicia_em) {
    innovToast('error', 'Validação', 'Informe data e hora.')
    return
  }
  saving.value = true
  try {
    const { data } = await api.post(
      `/escalas/tipos/${form.value.escala_tipo_id}/ocorrencias`,
      {
        titulo: form.value.titulo || null,
        inicia_em: new Date(form.value.inicia_em).toISOString(),
        local: form.value.local || null,
      },
    )
    const created = data?.data || data
    innovToast('success', 'OK', 'Ocorrência criada.')
    showForm.value = false
    await loadAgenda()
    if (created?.id) router.push(`/escalas/ocorrencias/${created.id}`)
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao criar ocorrência.'))
  } finally {
    saving.value = false
  }
}

function prevMonth() {
  cursor.value = new Date(year.value, monthIndex.value - 1, 1)
}
function nextMonth() {
  cursor.value = new Date(year.value, monthIndex.value + 1, 1)
}

function formatWhen(iso) {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

watch([cursor, tipoId], loadAgenda)
onMounted(async () => {
  await loadTipos()
  await loadAgenda()
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="escalas-agenda-page">
    <button
      type="button"
      class="text-[13px] mb-4 bg-transparent border-0 cursor-pointer p-0"
      style="color: #8A2436"
      @click="router.push('/escalas')"
    >
      ← Tipos de escala
    </button>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
      <div>
        <h1 class="font-serif text-[28px] font-normal m-0" style="color: #2A1418">Agenda</h1>
        <p class="text-[14px] mt-1 m-0" style="color: rgba(42, 20, 24, 0.62)">
          Ocorrências de escala em lista ou calendário.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <select
          v-model="tipoId"
          class="px-3 py-2 rounded-lg text-[13px]"
          style="border: 1px solid rgba(42, 20, 24, 0.14)"
          data-testid="escalas-agenda-filtro-tipo"
        >
          <option value="">Todos os tipos</option>
          <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
        </select>
        <div
          class="inline-flex rounded-lg overflow-hidden"
          style="border: 1px solid rgba(42, 20, 24, 0.14)"
        >
          <button
            type="button"
            class="px-3 py-2 text-[13px] border-0 cursor-pointer"
            :style="
              viewMode === 'lista'
                ? { background: '#6B1C2B', color: '#FFFDFA' }
                : { background: '#FFFDFA', color: '#2A1418' }
            "
            data-testid="escalas-agenda-lista"
            @click="viewMode = 'lista'"
          >
            Lista
          </button>
          <button
            type="button"
            class="px-3 py-2 text-[13px] border-0 cursor-pointer"
            :style="
              viewMode === 'calendario'
                ? { background: '#6B1C2B', color: '#FFFDFA' }
                : { background: '#FFFDFA', color: '#2A1418' }
            "
            data-testid="escalas-agenda-calendario"
            @click="viewMode = 'calendario'"
          >
            Calendário
          </button>
        </div>
        <button
          v-if="canManage"
          type="button"
          class="px-3 py-2 rounded-lg text-[13px] font-medium border-0 cursor-pointer"
          style="background: #6B1C2B; color: #FFFDFA"
          data-testid="escalas-agenda-nova"
          @click="openCreate()"
        >
          Nova ocorrência
        </button>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
      <button
        type="button"
        class="px-2 py-1 rounded-md border-0 cursor-pointer"
        style="background: #F3EDE6; color: #2A1418"
        @click="prevMonth"
      >
        ‹
      </button>
      <span class="font-serif text-[18px] capitalize" style="color: #2A1418">{{ monthLabel }}</span>
      <button
        type="button"
        class="px-2 py-1 rounded-md border-0 cursor-pointer"
        style="background: #F3EDE6; color: #2A1418"
        @click="nextMonth"
      >
        ›
      </button>
    </div>

    <p v-if="loading" class="text-[14px]" style="color: rgba(42, 20, 24, 0.62)">Carregando…</p>

    <ul
      v-else-if="viewMode === 'lista'"
      class="list-none p-0 m-0 flex flex-col gap-2"
      data-testid="escalas-agenda-lista-view"
    >
      <li
        v-for="o in ocorrencias"
        :key="o.id"
        class="px-4 py-3 rounded-xl cursor-pointer"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        @click="router.push(`/escalas/ocorrencias/${o.id}`)"
      >
        <div class="text-[14px] font-medium" style="color: #2A1418">
          {{ o.titulo || o.tipo_nome || 'Escala' }}
        </div>
        <div class="text-[12.5px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">
          {{ formatWhen(o.inicia_em) }}
          <span v-if="o.local"> · {{ o.local }}</span>
        </div>
      </li>
      <li
        v-if="!ocorrencias.length"
        class="text-[14px] py-6 text-center"
        style="color: rgba(42, 20, 24, 0.62)"
      >
        Nenhuma ocorrência neste mês.
      </li>
    </ul>

    <div v-else data-testid="escalas-agenda-cal-view">
      <div
        class="grid grid-cols-7 gap-1 text-[11px] text-center mb-1"
        style="color: rgba(42, 20, 24, 0.55)"
      >
        <span v-for="d in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']" :key="d">{{ d }}</span>
      </div>
      <div class="flex flex-col gap-1">
        <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7 gap-1">
          <div
            v-for="(cell, ci) in week"
            :key="ci"
            class="min-h-[80px] rounded-lg p-1.5 relative group"
            :class="cell && canManage ? 'cursor-pointer' : ''"
            :style="
              cell
                ? {
                    background: cell.key === todayKey ? '#F6EDE4' : '#FFFDFA',
                    border: '1px solid rgba(42, 20, 24, 0.1)',
                  }
                : { background: 'transparent' }
            "
            @click="cell && canManage && openCreate(cell.key)"
          >
            <template v-if="cell">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[12px] font-medium" style="color: #2A1418">{{ cell.day }}</span>
                <button
                  v-if="canManage"
                  type="button"
                  class="text-[14px] leading-none px-1 border-0 rounded cursor-pointer opacity-70 hover:opacity-100"
                  style="background: transparent; color: #8A2436"
                  title="Adicionar ocorrência"
                  data-testid="escalas-cal-add-day"
                  @click="openCreateOnDay(cell, $event)"
                >
                  +
                </button>
              </div>
              <button
                v-for="o in byDay.get(cell.key) || []"
                :key="o.id"
                type="button"
                class="block w-full text-left text-[10px] leading-tight truncate px-1 py-0.5 rounded mb-0.5 border-0 cursor-pointer"
                style="background: #6B1C2B; color: #FFFDFA"
                @click.stop="router.push(`/escalas/ocorrencias/${o.id}`)"
              >
                {{ o.titulo || o.tipo_nome || 'Escala' }}
              </button>
            </template>
          </div>
        </div>
      </div>
      <p
        v-if="canManage"
        class="text-[12px] mt-3 m-0"
        style="color: rgba(42, 20, 24, 0.55)"
      >
        Clique em um dia (ou no +) para criar uma ocorrência.
      </p>
    </div>

    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.35)"
      data-testid="escalas-agenda-form-modal"
      @click.self="showForm = false"
    >
      <form
        class="w-full max-w-md rounded-xl p-5 flex flex-col gap-3"
        style="background: #FFFDFA"
        @submit.prevent="createOcorrencia"
      >
        <div class="flex items-center justify-between gap-2">
          <h3 class="font-serif text-[20px] m-0" style="color: #2A1418">Nova ocorrência</h3>
          <button
            type="button"
            class="bg-transparent border-0 cursor-pointer text-[20px] leading-none"
            style="color: #2A1418"
            @click="showForm = false"
          >
            ×
          </button>
        </div>

        <label class="text-[13px]" style="color: #2A1418">
          Tipo de escala
          <select
            v-model="form.escala_tipo_id"
            class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
            required
            data-testid="escalas-agenda-form-tipo"
          >
            <option disabled value="">Selecione…</option>
            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nome }}</option>
          </select>
        </label>

        <label class="text-[13px]" style="color: #2A1418">
          Título (opcional)
          <input
            v-model="form.titulo"
            class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
            placeholder="Ex.: Missa 10h"
          />
        </label>

        <label class="text-[13px]" style="color: #2A1418">
          Data e hora
          <input
            v-model="form.inicia_em"
            type="datetime-local"
            class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
            required
            data-testid="escalas-agenda-form-inicio"
          />
        </label>

        <label class="text-[13px]" style="color: #2A1418">
          Local (opcional)
          <input
            v-model="form.local"
            class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
          />
        </label>

        <div class="flex gap-2 justify-end mt-1">
          <button
            type="button"
            class="px-3 py-2 rounded-lg text-[13px] cursor-pointer"
            style="background: #F3EDE6; border: 0; color: #2A1418"
            @click="showForm = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="px-3 py-2 rounded-lg text-[13px] font-medium border-0 cursor-pointer"
            style="background: #6B1C2B; color: #FFFDFA"
            :disabled="saving"
            data-testid="escalas-agenda-form-salvar"
          >
            {{ saving ? 'Salvando…' : 'Criar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
