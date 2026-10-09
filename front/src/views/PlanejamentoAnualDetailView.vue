<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { listPastorais } from '@/services/pastorais'
import {
  changeAnualStatus,
  createEvento,
  deleteEvento,
  downloadAnualPdf,
  getAnual,
  listEventos,
  updateEvento,
} from '@/services/planejamento'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import {
  MES_NOMES,
  formatHoraCalendario,
  gradeMesCalendario,
} from '@/utils/calendario'
import {
  STATUS_SOLICITACAO,
  canAlterarStatusSolicitacao,
  canCriarEventoPlanejamento,
  canEditarEventoPlanejamento,
  filterEventosPorMes,
  formatConflitosAlert,
  groupEventosByDay,
  labelEventoPlanejamentoPreview,
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  pastoralIdsDoUsuario,
  proximosStatusPlanejamento,
  unwrapList,
} from '@/utils/planejamento'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const loading = ref(true)
const saving = ref(false)
const anual = ref(null)
const eventos = ref([])
const pastorais = ref([])
const locais = ref([])
const mesView = ref(new Date().getMonth() + 1)
const filtroPastoral = ref('')
const filtroStatus = ref('')
const diaSelecionado = ref('')
const editingEvento = ref(null)
const showForm = ref(false)
const conflitoAlert = ref('')

const emptyForm = () => ({
  pastoral_id: '',
  titulo: '',
  data_inicio: '',
  data_fim: '',
  hora_inicio: '',
  hora_fim: '',
  participantes_media: null,
  recorrencia_texto: '',
  observacoes: '',
  local_ids: [],
  local_texto: '',
  status_solicitacao: 'proposta',
  motivo_ajuste: '',
})

const form = ref(emptyForm())

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canGerir = computed(
  () =>
    userHasPermission(auth.user, 'planejamento.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)
const canPropor = computed(
  () =>
    userHasPermission(auth.user, 'planejamento.propor', { isSuperAdmin: isPlatformAdmin.value }) ||
    canGerir.value,
)
const canVerGlobal = computed(
  () =>
    canGerir.value ||
    userHasPermission(auth.user, 'planejamento.ver_global', {
      isSuperAdmin: isPlatformAdmin.value,
    }),
)

const myPastoralIds = computed(() => pastoralIdsDoUsuario(auth.user))

const perms = computed(() => ({
  canGerir: canGerir.value,
  canPropor: canPropor.value,
  pastoralIds: myPastoralIds.value,
}))

const anoStatus = computed(() => anual.value?.status || '')
const proximos = computed(() => proximosStatusPlanejamento(anoStatus.value))
const podeCriar = computed(() => canCriarEventoPlanejamento(anoStatus.value, perms.value))
const podeAlterarStatusSolic = computed(() =>
  canAlterarStatusSolicitacao(anoStatus.value, perms.value),
)

const pastoraisOpcoes = computed(() => {
  if (canGerir.value) return pastorais.value
  if (!myPastoralIds.value.length) return pastorais.value
  return pastorais.value.filter((p) => myPastoralIds.value.includes(p.id))
})

const eventosFiltrados = computed(() => {
  let list = filterEventosPorMes(eventos.value, mesView.value)
  if (filtroPastoral.value) {
    list = list.filter((e) => e.pastoral_id === filtroPastoral.value)
  }
  if (filtroStatus.value) {
    list = list.filter((e) => e.status_solicitacao === filtroStatus.value)
  }
  if (!canVerGlobal.value && myPastoralIds.value.length) {
    list = list.filter((e) => myPastoralIds.value.includes(e.pastoral_id))
  }
  return list
})

const byDay = computed(() => groupEventosByDay(eventosFiltrados.value))
const cells = computed(() => {
  if (!anual.value) return []
  return gradeMesCalendario(anual.value.ano, mesView.value)
})

const mesTitulo = computed(() => `${MES_NOMES[mesView.value] || mesView.value} ${anual.value?.ano || ''}`)

const diaEventos = computed(() => {
  if (!diaSelecionado.value) return []
  return byDay.value.get(diaSelecionado.value) || []
})

const statusSolicitacaoOptions = computed(() =>
  Object.entries(STATUS_SOLICITACAO).map(([value, label]) => ({ value, label })),
)

function eventoTemConflito(ev) {
  return Array.isArray(ev.conflitos) && ev.conflitos.length > 0
}

function podeEditar(ev) {
  return canEditarEventoPlanejamento(anoStatus.value, ev, perms.value)
}

function pastoralNome(id) {
  return pastorais.value.find((p) => p.id === id)?.nome || ''
}

function openCreate(dayIso) {
  if (!podeCriar.value) return
  editingEvento.value = null
  form.value = emptyForm()
  form.value.data_inicio = dayIso || ''
  form.value.pastoral_id = pastoraisOpcoes.value[0]?.id || ''
  form.value.local_ids = locais.value[0] ? [locais.value[0].id] : []
  showForm.value = true
  diaSelecionado.value = dayIso || diaSelecionado.value
}

function openEdit(ev) {
  if (!podeEditar(ev)) return
  editingEvento.value = ev
  form.value = {
    pastoral_id: ev.pastoral_id || '',
    titulo: ev.titulo || '',
    data_inicio: (ev.data_inicio || '').slice(0, 10),
    data_fim: ev.data_fim ? String(ev.data_fim).slice(0, 10) : '',
    hora_inicio: formatHoraCalendario(ev.hora_inicio) || '',
    hora_fim: formatHoraCalendario(ev.hora_fim) || '',
    participantes_media: ev.participantes_media ?? null,
    recorrencia_texto: ev.recorrencia_texto || '',
    observacoes: ev.observacoes || '',
    local_ids: (ev.locais || []).map((l) => l.id || l).filter(Boolean),
    local_texto: ev.local_texto || '',
    status_solicitacao: ev.status_solicitacao || 'proposta',
    motivo_ajuste: ev.motivo_ajuste || '',
  }
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingEvento.value = null
  form.value = emptyForm()
}

function toggleLocal(id) {
  const set = new Set(form.value.local_ids)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  form.value.local_ids = [...set]
}

function buildPayload() {
  const f = form.value
  const payload = {
    pastoral_id: f.pastoral_id,
    titulo: f.titulo.trim(),
    data_inicio: f.data_inicio,
    data_fim: f.data_fim || null,
    hora_inicio: f.hora_inicio || null,
    hora_fim: f.hora_fim || null,
    participantes_media:
      f.participantes_media === '' || f.participantes_media == null
        ? null
        : Number(f.participantes_media),
    recorrencia_texto: f.recorrencia_texto || null,
    observacoes: f.observacoes || null,
    local_ids: f.local_ids,
    local_texto: f.local_texto || null,
  }
  if (canGerir.value && editingEvento.value) {
    payload.status_solicitacao = f.status_solicitacao
    payload.motivo_ajuste = f.motivo_ajuste || null
  }
  return payload
}

function showConflitos(conflitos) {
  const msg = formatConflitosAlert(conflitos)
  conflitoAlert.value = msg
  if (msg) innovToast('warning', 'Conflito', msg)
}

async function load({ silent = false } = {}) {
  if (!silent) loading.value = true
  try {
    const id = route.params.id
    const [a, evs, pasts, locsRes] = await Promise.all([
      getAnual(id),
      listEventos(id),
      listPastorais(),
      api.get('/calendario/locais').catch(() => ({ data: [] })),
    ])
    if (!a) {
      innovToast('error', 'Planejamento', 'Não encontrado')
      router.push('/planejamento')
      return
    }
    anual.value = a
    eventos.value = evs
    pastorais.value = pasts
    locais.value = unwrapList(locsRes.data)
    if (a.eventos && Array.isArray(a.eventos) && !evs.length) {
      eventos.value = a.eventos
    }
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao carregar')
    if (!silent) router.push('/planejamento')
  } finally {
    if (!silent) loading.value = false
  }
}

async function salvarEvento() {
  const payload = buildPayload()
  if (!payload.titulo) {
    innovToast('error', 'Evento', 'Informe o título')
    return
  }
  if (!payload.pastoral_id) {
    innovToast('error', 'Evento', 'Selecione a pastoral')
    return
  }
  if (!payload.data_inicio) {
    innovToast('error', 'Evento', 'Informe a data')
    return
  }
  if (!(payload.local_ids?.length || payload.local_texto)) {
    innovToast('error', 'Evento', 'Informe ao menos um local ou texto de local')
    return
  }
  saving.value = true
  try {
    let result
    if (editingEvento.value) {
      result = await updateEvento(editingEvento.value.id, payload)
    } else {
      result = await createEvento(anual.value.id, payload)
    }
    showConflitos(result.conflitos)
    innovToast('success', 'Evento', editingEvento.value ? 'Atualizado' : 'Proposto')
    closeForm()
    await load({ silent: true })
  } catch (e) {
    innovToast('error', 'Evento', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

async function removerEvento(ev) {
  if (!podeEditar(ev)) return
  const ok = await innovConfirm({
    title: 'Remover evento',
    message: `Remover "${ev.titulo}"?`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await deleteEvento(ev.id)
    innovToast('success', 'Evento', 'Removido')
    await load({ silent: true })
  } catch (e) {
    innovToast('error', 'Evento', e.response?.data?.message || 'Falha ao remover')
  }
}

async function mudarStatus(status) {
  if (!canGerir.value) return
  try {
    anual.value = await changeAnualStatus(anual.value.id, { status })
    innovToast('success', 'Planejamento', `Status: ${labelStatusPlanejamento(status)}`)
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Transição inválida')
  }
}

async function baixarPdf() {
  try {
    const blob = await downloadAnualPdf(anual.value.id, { mes: mesView.value })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `planejamento-${anual.value.ano}-${String(mesView.value).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    innovToast('error', 'PDF', e.response?.data?.message || 'Falha ao exportar')
  }
}

function selectDay(iso) {
  if (!iso) return
  diaSelecionado.value = iso
}

watch(
  () => route.params.id,
  () => load(),
)

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full space-y-6" data-testid="planejamento-detalhe">
    <button
      type="button"
      class="btn btn-ghost !px-0 text-[13px]"
      style="color: var(--color-primary-hover)"
      @click="router.push('/planejamento')"
    >
      ← Planejamento
    </button>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <template v-else-if="anual">
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p
            class="text-[11px] font-medium tracking-wider uppercase mb-1"
            style="color: var(--color-accent-dark)"
          >
            Planejamento anual
          </p>
          <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
            {{ anual.ano }}
          </h1>
          <p class="text-[14px] mt-1" style="color: var(--color-muted)">
            {{ labelStatusPlanejamento(anual.status) }}
            · visão {{ canGerir ? 'gestor' : 'coordenador' }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <template v-if="canGerir">
            <button
              v-for="st in proximos"
              :key="st"
              type="button"
              class="btn btn-ghost"
              :data-testid="`planejamento-status-${st}`"
              @click="mudarStatus(st)"
            >
              → {{ labelStatusPlanejamento(st) }}
            </button>
          </template>
          <button
            v-if="canGerir || anoStatus === 'fechado' || anoStatus === 'revisao'"
            type="button"
            class="btn btn-primary"
            data-testid="planejamento-pdf"
            @click="baixarPdf"
          >
            PDF
          </button>
        </div>
      </header>

      <div
        v-if="conflitoAlert"
        class="rounded-xl px-4 py-3 text-[13.5px]"
        style="background: var(--color-accent-soft); color: var(--color-ink); border: 1px solid var(--color-line)"
        data-testid="planejamento-conflito-alert"
      >
        {{ conflitoAlert }}
        <button
          type="button"
          class="btn btn-ghost !py-0.5 !px-2 text-xs ml-2"
          @click="conflitoAlert = ''"
        >
          Fechar
        </button>
      </div>

      <div class="card p-4 md:p-5 flex flex-wrap gap-3 items-end">
        <div>
          <label class="fld" for="pl-mes">Mês</label>
          <select id="pl-mes" v-model.number="mesView" class="input min-w-[10rem]" data-testid="planejamento-mes">
            <option v-for="(nome, i) in MES_NOMES.slice(1)" :key="i + 1" :value="i + 1">
              {{ nome }}
            </option>
          </select>
        </div>
        <div v-if="canVerGlobal">
          <label class="fld" for="pl-filtro-past">Pastoral</label>
          <select id="pl-filtro-past" v-model="filtroPastoral" class="input min-w-[12rem]">
            <option value="">Todas</option>
            <option v-for="p in pastorais" :key="p.id" :value="p.id">{{ p.nome }}</option>
          </select>
        </div>
        <div>
          <label class="fld" for="pl-filtro-st">Status</label>
          <select id="pl-filtro-st" v-model="filtroStatus" class="input min-w-[10rem]">
            <option value="">Todos</option>
            <option
              v-for="opt in statusSolicitacaoOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <button
          v-if="podeCriar"
          type="button"
          class="btn btn-primary ml-auto"
          data-testid="planejamento-novo-evento"
          @click="openCreate(diaSelecionado || `${anual.ano}-${String(mesView).padStart(2, '0')}-01`)"
        >
          Propor evento
        </button>
      </div>

      <div class="card p-4 md:p-5" data-testid="planejamento-grade">
        <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
          {{ mesTitulo }}
        </h2>
        <div class="grid grid-cols-7 gap-1 text-center text-[11px] mb-1" style="color: var(--color-muted)">
          <div v-for="d in WEEKDAYS" :key="d">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <button
            v-for="(cell, idx) in cells"
            :key="idx"
            type="button"
            class="min-h-[72px] md:min-h-[88px] rounded-lg p-1.5 text-left border-0 cursor-pointer"
            :disabled="!cell.inMonth"
            :style="
              cell.inMonth
                ? {
                    background:
                      diaSelecionado === cell.iso
                        ? 'var(--color-primary-soft)'
                        : 'var(--color-surface)',
                    color: diaSelecionado === cell.iso ? 'var(--color-on-primary)' : 'var(--color-ink)',
                    border: '1px solid var(--color-line)',
                  }
                : { background: 'transparent', cursor: 'default' }
            "
            :data-testid="cell.iso ? `planejamento-dia-${cell.iso}` : undefined"
            @click="cell.inMonth && selectDay(cell.iso)"
            @dblclick="cell.inMonth && podeCriar && openCreate(cell.iso)"
          >
            <template v-if="cell.inMonth">
              <div class="text-[12px] font-medium mb-0.5">{{ cell.dia }}</div>
              <div
                v-for="(line, li) in (byDay.get(cell.iso) || []).slice(0, 2).map(labelEventoPlanejamentoPreview)"
                :key="li"
                class="text-[10px] truncate leading-tight opacity-90"
              >
                {{ line }}
              </div>
              <div
                v-if="(byDay.get(cell.iso) || []).length > 2"
                class="text-[10px] opacity-70"
              >
                +{{ (byDay.get(cell.iso) || []).length - 2 }}
              </div>
            </template>
          </button>
        </div>
      </div>

      <section
        v-if="diaSelecionado"
        class="card p-4 md:p-5"
        data-testid="planejamento-dia-painel"
      >
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
          <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
            {{ diaSelecionado }}
          </h2>
          <button
            v-if="podeCriar"
            type="button"
            class="btn btn-ghost"
            @click="openCreate(diaSelecionado)"
          >
            + Evento neste dia
          </button>
        </div>
        <ul v-if="diaEventos.length" class="list-none p-0 m-0 space-y-3">
          <li
            v-for="ev in diaEventos"
            :key="ev.id"
            class="rounded-lg px-3 py-3"
            style="border: 1px solid var(--color-line)"
            data-testid="planejamento-evento"
          >
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="font-medium text-[14px]" style="color: var(--color-ink)">
                  {{ ev.titulo }}
                  <span
                    v-if="eventoTemConflito(ev) || (ev.tem_conflito)"
                    class="ml-1 text-[11px] font-medium px-2 py-0.5 rounded-full"
                    style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
                    data-testid="planejamento-badge-conflito"
                  >
                    Conflito
                  </span>
                </div>
                <div class="text-[12.5px] mt-0.5" style="color: var(--color-muted)">
                  {{ pastoralNome(ev.pastoral_id) || ev.pastoral?.nome }}
                  <template v-if="ev.hora_inicio">
                    · {{ formatHoraCalendario(ev.hora_inicio) }}
                    <template v-if="ev.hora_fim">–{{ formatHoraCalendario(ev.hora_fim) }}</template>
                  </template>
                </div>
                <div class="mt-1">
                  <span
                    class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                    style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
                  >
                    {{ labelStatusSolicitacao(ev.status_solicitacao) }}
                  </span>
                </div>
                <p
                  v-if="ev.motivo_ajuste"
                  class="text-[12px] mt-1"
                  style="color: var(--color-muted)"
                >
                  Ajuste: {{ ev.motivo_ajuste }}
                </p>
              </div>
              <div class="flex gap-2 shrink-0">
                <button
                  v-if="podeEditar(ev)"
                  type="button"
                  class="btn btn-ghost !py-1.5 !px-3 text-xs"
                  data-testid="planejamento-evento-editar"
                  @click="openEdit(ev)"
                >
                  Editar
                </button>
                <button
                  v-if="podeEditar(ev)"
                  type="button"
                  class="btn btn-ghost !py-1.5 !px-3 text-xs"
                  style="color: var(--color-danger)"
                  @click="removerEvento(ev)"
                >
                  Remover
                </button>
              </div>
            </div>
          </li>
        </ul>
        <p v-else class="text-[13.5px]" style="color: var(--color-muted)">
          Nenhum evento neste dia.
        </p>
      </section>

      <section
        v-if="showForm"
        class="card p-5 md:p-6"
        data-testid="planejamento-evento-form"
      >
        <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
          {{ editingEvento ? 'Editar evento' : 'Propor evento' }}
        </h2>
        <form class="flex flex-col gap-4" @submit.prevent="salvarEvento">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="fld" for="ev-pastoral">Pastoral</label>
              <select
                id="ev-pastoral"
                v-model="form.pastoral_id"
                class="input"
                required
                :disabled="!canGerir && !!editingEvento"
                data-testid="planejamento-evento-pastoral"
              >
                <option v-for="p in pastoraisOpcoes" :key="p.id" :value="p.id">
                  {{ p.nome }}
                </option>
              </select>
            </div>
            <div>
              <label class="fld" for="ev-titulo">Título</label>
              <input
                id="ev-titulo"
                v-model="form.titulo"
                class="input"
                required
                data-testid="planejamento-evento-titulo"
              />
            </div>
            <div>
              <label class="fld" for="ev-inicio">Data início</label>
              <input
                id="ev-inicio"
                v-model="form.data_inicio"
                type="date"
                class="input"
                required
              />
            </div>
            <div>
              <label class="fld" for="ev-fim">Data fim</label>
              <input id="ev-fim" v-model="form.data_fim" type="date" class="input" />
            </div>
            <div>
              <label class="fld" for="ev-hi">Hora início</label>
              <input id="ev-hi" v-model="form.hora_inicio" type="time" class="input" />
            </div>
            <div>
              <label class="fld" for="ev-hf">Hora fim</label>
              <input id="ev-hf" v-model="form.hora_fim" type="time" class="input" />
            </div>
            <div>
              <label class="fld" for="ev-part">Participantes (média)</label>
              <input
                id="ev-part"
                v-model.number="form.participantes_media"
                type="number"
                min="0"
                class="input"
              />
            </div>
            <div>
              <label class="fld" for="ev-rec">Recorrência (texto)</label>
              <input
                id="ev-rec"
                v-model="form.recorrencia_texto"
                class="input"
                placeholder="Ex.: toda 1ª terça"
              />
            </div>
          </div>

          <div>
            <span class="fld">Locais do catálogo</span>
            <div class="flex flex-wrap gap-2 mt-1">
              <label
                v-for="loc in locais"
                :key="loc.id"
                class="inline-flex items-center gap-1.5 text-[13px] cursor-pointer px-2.5 py-1.5 rounded-lg"
                style="border: 1px solid var(--color-line)"
              >
                <input
                  type="checkbox"
                  :checked="form.local_ids.includes(loc.id)"
                  @change="toggleLocal(loc.id)"
                />
                {{ loc.nome }}
              </label>
              <span v-if="!locais.length" class="text-[12.5px]" style="color: var(--color-muted)">
                Nenhum local cadastrado — use texto livre ou cadastre em Calendário → Locais.
              </span>
            </div>
          </div>

          <div>
            <label class="fld" for="ev-local-txt">Local (texto livre)</label>
            <input
              id="ev-local-txt"
              v-model="form.local_texto"
              class="input"
              placeholder="Externo / legado"
            />
          </div>

          <div>
            <label class="fld" for="ev-obs">Observações</label>
            <textarea id="ev-obs" v-model="form.observacoes" class="input min-h-[72px]" rows="2" />
          </div>

          <template v-if="podeAlterarStatusSolic && editingEvento">
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="fld" for="ev-status">Status da solicitação</label>
                <select
                  id="ev-status"
                  v-model="form.status_solicitacao"
                  class="input"
                  data-testid="planejamento-evento-status"
                >
                  <option
                    v-for="opt in statusSolicitacaoOptions"
                    :key="opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="fld" for="ev-motivo">Motivo do ajuste</label>
                <input id="ev-motivo" v-model="form.motivo_ajuste" class="input" />
              </div>
            </div>
          </template>

          <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" @click="closeForm">Cancelar</button>
            <button
              type="submit"
              class="btn btn-primary"
              data-testid="planejamento-evento-salvar"
              :disabled="saving"
            >
              {{ saving ? 'Salvando…' : 'Salvar' }}
            </button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>
