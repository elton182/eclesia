<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const DIAS = [
  { value: 0, label: 'Domingo' },
  { value: 1, label: 'Segunda' },
  { value: 2, label: 'Terça' },
  { value: 3, label: 'Quarta' },
  { value: 4, label: 'Quinta' },
  { value: 5, label: 'Sexta' },
  { value: 6, label: 'Sábado' },
]

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const locais = ref([])
const slots = ref([])
const editingLocalId = ref(null)
const editingSlotId = ref(null)
const localForm = ref({ nome: '' })
const slotForm = ref({ local_id: '', dia_semana: 0, hora: '19:30' })

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'calendario.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

function labelDia(n) {
  return DIAS.find((d) => d.value === Number(n))?.label || String(n)
}

function labelLocal(id) {
  return locais.value.find((l) => l.id === id)?.nome || '—'
}

function secaoPorDia(dow) {
  return Number(dow) === 0 || Number(dow) === 6 ? 'fds' : 'semana'
}

function resetLocalForm() {
  editingLocalId.value = null
  localForm.value = { nome: '' }
}

function resetSlotForm() {
  editingSlotId.value = null
  slotForm.value = {
    local_id: locais.value[0]?.id || '',
    dia_semana: 0,
    hora: '19:30',
  }
}

async function load() {
  loading.value = true
  try {
    const [l, s] = await Promise.all([
      api.get('/calendario/locais'),
      api.get('/calendario/slots-padrao'),
    ])
    locais.value = l.data.data || l.data || []
    slots.value = s.data.data || s.data || []
    if (!slotForm.value.local_id && locais.value[0]) {
      slotForm.value.local_id = locais.value[0].id
    }
  } catch (e) {
    innovToast('error', 'Locais', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

function editarLocal(loc) {
  editingLocalId.value = loc.id
  localForm.value = { nome: loc.nome }
}

async function salvarLocal() {
  if (!localForm.value.nome.trim()) return
  try {
    if (editingLocalId.value) {
      const { data } = await api.put(`/calendario/locais/${editingLocalId.value}`, {
        nome: localForm.value.nome.trim(),
      })
      const updated = data.data || data
      locais.value = locais.value.map((l) => (l.id === updated.id ? { ...l, ...updated } : l))
      slots.value = slots.value.map((s) =>
        s.local_id === updated.id ? { ...s, local: { ...(s.local || {}), ...updated } } : s,
      )
      innovToast('success', 'Local', 'Atualizado')
    } else {
      const { data } = await api.post('/calendario/locais', {
        nome: localForm.value.nome.trim(),
        ordem: locais.value.length + 1,
      })
      const created = data.data || data
      locais.value = [...locais.value, created]
      if (!slotForm.value.local_id) slotForm.value.local_id = created.id
      innovToast('success', 'Local', 'Cadastrado')
    }
    resetLocalForm()
  } catch (e) {
    innovToast('error', 'Local', e.response?.data?.message || 'Falha')
  }
}

async function removeLocal(loc) {
  const ok = await innovConfirm({
    title: 'Remover local',
    message: `Remover “${loc.nome}”? Horários vinculados também serão afetados.`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await api.delete(`/calendario/locais/${loc.id}`)
    locais.value = locais.value.filter((l) => l.id !== loc.id)
    slots.value = slots.value.filter((s) => s.local_id !== loc.id)
    if (editingLocalId.value === loc.id) resetLocalForm()
    if (slotForm.value.local_id === loc.id) {
      slotForm.value.local_id = locais.value[0]?.id || ''
    }
    innovToast('success', 'Local', 'Removido')
  } catch (e) {
    innovToast('error', 'Local', e.response?.data?.message || 'Falha')
  }
}

function editarSlot(slot) {
  editingSlotId.value = slot.id
  slotForm.value = {
    local_id: slot.local_id,
    dia_semana: Number(slot.dia_semana),
    hora: String(slot.hora || '').slice(0, 5) || '19:30',
  }
}

async function salvarSlot() {
  if (!slotForm.value.local_id || !slotForm.value.hora) {
    innovToast('error', 'Horário', 'Informe local e hora.')
    return
  }
  const dow = Number(slotForm.value.dia_semana)
  const payload = {
    local_id: slotForm.value.local_id,
    dia_semana: dow,
    hora: slotForm.value.hora.slice(0, 5),
    secao: secaoPorDia(dow),
  }
  try {
    if (editingSlotId.value) {
      const { data } = await api.put(`/calendario/slots-padrao/${editingSlotId.value}`, payload)
      const updated = data.data || data
      slots.value = slots.value.map((s) => (s.id === updated.id ? { ...s, ...updated } : s))
      innovToast('success', 'Horário', 'Atualizado')
    } else {
      const { data } = await api.post('/calendario/slots-padrao', payload)
      const created = data.data || data
      slots.value = [...slots.value, created]
      innovToast('success', 'Horário', 'Slot padrão criado')
    }
    resetSlotForm()
  } catch (e) {
    innovToast('error', 'Horário', e.response?.data?.message || 'Falha')
  }
}

async function removeSlot(slot) {
  const ok = await innovConfirm({
    title: 'Remover horário',
    message: 'Remover este slot padrão?',
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await api.delete(`/calendario/slots-padrao/${slot.id}`)
    slots.value = slots.value.filter((s) => s.id !== slot.id)
    if (editingSlotId.value === slot.id) resetSlotForm()
    innovToast('success', 'Horário', 'Removido')
  } catch (e) {
    innovToast('error', 'Horário', e.response?.data?.message || 'Falha')
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full space-y-6" data-testid="calendario-locais">
    <header class="mb-2">
      <p class="page-eyebrow">Calendário</p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Locais e horários
      </h1>
      <p class="text-[14px] mt-1" style="color: var(--color-muted)">
        Cadastre locais de celebração e os horários padrão por dia da semana. Novos meses
        copiam esses slots para a grade.
      </p>
    </header>

    <div v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</div>

    <template v-else>
      <div class="card p-5 md:p-6 space-y-4">
        <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">Locais</h2>
        <ul class="space-y-2 list-none p-0 m-0" data-testid="calendario-locais-lista">
          <li
            v-for="loc in locais"
            :key="loc.id"
            class="flex flex-wrap items-center justify-between gap-3 py-2 border-b"
            style="border-color: var(--color-line)"
          >
            <span style="color: var(--color-ink)">{{ loc.nome }}</span>
            <div v-if="canManage" class="flex gap-2">
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                data-testid="calendario-local-editar"
                @click="editarLocal(loc)"
              >
                Editar
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                style="color: var(--color-danger)"
                @click="removeLocal(loc)"
              >
                Remover
              </button>
            </div>
          </li>
          <li v-if="!locais.length" class="text-sm py-4" style="color: var(--color-muted)">
            Nenhum local ainda. Cadastre Matriz, capelas, etc.
          </li>
        </ul>
        <form
          v-if="canManage"
          class="flex flex-col sm:flex-row gap-2 sm:items-end"
          @submit.prevent="salvarLocal"
        >
          <div class="flex-1">
            <label class="fld" for="novo-local">{{ editingLocalId ? 'Editar local' : 'Novo local' }}</label>
            <input
              id="novo-local"
              v-model="localForm.nome"
              class="input"
              placeholder="Ex.: MATRIZ"
              data-testid="calendario-local-nome"
            />
          </div>
          <button
            v-if="editingLocalId"
            type="button"
            class="btn btn-ghost"
            @click="resetLocalForm"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            data-testid="calendario-local-salvar"
            :disabled="!localForm.nome.trim()"
          >
            {{ editingLocalId ? 'Salvar local' : 'Adicionar local' }}
          </button>
        </form>
      </div>

      <div class="card p-5 md:p-6 space-y-4">
        <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
          Horários padrão
        </h2>
        <ul class="space-y-2 list-none p-0 m-0" data-testid="calendario-slots-lista">
          <li
            v-for="slot in slots"
            :key="slot.id"
            class="flex flex-wrap items-center justify-between gap-2 py-2 border-b text-sm"
            style="border-color: var(--color-line); color: var(--color-ink)"
          >
            <span>
              {{ labelDia(slot.dia_semana) }} · {{ String(slot.hora).slice(0, 5) }} ·
              {{ slot.local?.nome || labelLocal(slot.local_id) }}
            </span>
            <div v-if="canManage" class="flex gap-2">
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                data-testid="calendario-slot-editar"
                @click="editarSlot(slot)"
              >
                Editar
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                style="color: var(--color-danger)"
                @click="removeSlot(slot)"
              >
                Remover
              </button>
            </div>
          </li>
          <li v-if="!slots.length" class="text-sm py-4" style="color: var(--color-muted)">
            Nenhum horário padrão. Ex.: Domingo 09:30 na Matriz.
          </li>
        </ul>
        <form
          v-if="canManage"
          class="grid gap-3 md:grid-cols-4 md:items-end"
          @submit.prevent="salvarSlot"
        >
          <div>
            <label class="fld" for="slot-local">Local</label>
            <select
              id="slot-local"
              v-model="slotForm.local_id"
              class="input"
              data-testid="calendario-slot-local"
            >
              <option disabled value="">Selecione…</option>
              <option v-for="loc in locais" :key="loc.id" :value="loc.id">{{ loc.nome }}</option>
            </select>
          </div>
          <div>
            <label class="fld" for="slot-dia">Dia</label>
            <select id="slot-dia" v-model.number="slotForm.dia_semana" class="input">
              <option v-for="d in DIAS" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="fld" for="slot-hora">Hora</label>
            <input id="slot-hora" v-model="slotForm.hora" type="time" class="input" />
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="editingSlotId"
              type="button"
              class="btn btn-ghost"
              @click="resetSlotForm"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              data-testid="calendario-slot-salvar"
              :disabled="!slotForm.local_id || !slotForm.hora"
            >
              {{ editingSlotId ? 'Salvar horário' : 'Adicionar horário' }}
            </button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>
