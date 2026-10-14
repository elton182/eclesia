<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { listPastorais } from '@/services/pastorais'
import {
  changeAnualStatus,
  createEvento,
  createPastoralToken,
  deleteEvento,
  downloadAnualPdf,
  getAnual,
  listEventos,
  listPastoralTokens,
  revokePastoralToken,
  updateEvento,
} from '@/services/planejamento'
import { useTenantStore } from '@/stores/tenant'
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
  styleStatusSolicitacao,
  mapPastoralLinks,
  pastoralIdsDoUsuario,
  proximosStatusPlanejamento,
  unwrapList,
} from '@/utils/planejamento'
import {
  buildRecursosPayload,
  disponibilidadeParams,
  emptySelecaoRecursos,
  formatConflitosRecursos,
  isErroCapacidade,
  labelRecursosEvento,
  moverEventoParaDia,
  selecaoFromEvento,
  selecaoTemEspaco,
} from '@/utils/planejamentoRecursos'
import { getDisponibilidade, listRecursos } from '@/services/planejamentoRecursos'
import PlanejamentoRecursosPicker from '@/components/planejamento/PlanejamentoRecursosPicker.vue'
import Hora24Input from '@/components/form/Hora24Input.vue'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()

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
const showDayModal = ref(false)
const editingEvento = ref(null)
const showForm = ref(false)
const conflitoAlert = ref('')
/** @type {import('vue').Ref<Record<string, string>>} */
const pastoralLinks = ref({})
const tokenBusy = ref('')
const recursosCatalogo = ref([])
const filtroEspaco = ref('')
const selecaoRecursos = ref(emptySelecaoRecursos())
const disponibilidade = ref([])
const dispLoading = ref(false)
const capacidadeErro = ref('')
const dragEventoId = ref('')
const dropAlvo = ref('')
let dispTimer = null
let dispSeq = 0

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
  if (filtroEspaco.value) {
    list = list.filter((e) =>
      (e.recursos || []).some((r) => r.recurso_id === filtroEspaco.value),
    )
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

const espacosCatalogo = computed(() => recursosCatalogo.value.filter((r) => r.tipo === 'espaco'))
const podeArrastar = computed(() => canGerir.value && anoStatus.value !== 'fechado')
const dispParams = computed(() =>
  disponibilidadeParams(form.value, editingEvento.value?.id || undefined),
)

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
  form.value.data_inicio = dayIso || diaSelecionado.value || ''
  form.value.pastoral_id = pastoraisOpcoes.value[0]?.id || ''
  selecaoRecursos.value = emptySelecaoRecursos()
  capacidadeErro.value = ''
  if (dayIso) diaSelecionado.value = dayIso
  showForm.value = true
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
  selecaoRecursos.value = selecaoFromEvento(ev)
  capacidadeErro.value = ''
  showDayModal.value = false
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingEvento.value = null
  form.value = emptyForm()
  selecaoRecursos.value = emptySelecaoRecursos()
  disponibilidade.value = []
  capacidadeErro.value = ''
}

async function carregarDisponibilidade() {
  const params = dispParams.value
  if (!showForm.value || !anual.value || !params) {
    disponibilidade.value = []
    return
  }
  const seq = ++dispSeq
  dispLoading.value = true
  try {
    const data = await getDisponibilidade(anual.value.id, params)
    if (seq === dispSeq) disponibilidade.value = data
  } catch {
    if (seq === dispSeq) disponibilidade.value = []
  } finally {
    if (seq === dispSeq) dispLoading.value = false
  }
}

watch(
  () => [showForm.value, JSON.stringify(dispParams.value)],
  () => {
    clearTimeout(dispTimer)
    dispTimer = setTimeout(carregarDisponibilidade, 250)
  },
)

function closeDayModal() {
  showDayModal.value = false
}

function publicLinkFor(token) {
  if (!token) return ''
  const qs = tenantStore.slug ? `?tenant=${encodeURIComponent(tenantStore.slug)}` : ''
  return `${window.location.origin}/planejamento/publico/${token}${qs}`
}

async function gerarLinkPastoral(pastoral) {
  if (!canGerir.value || !anual.value) return
  tokenBusy.value = pastoral.id
  try {
    const { token } = await createPastoralToken(anual.value.id, pastoral.id)
    pastoralLinks.value = { ...pastoralLinks.value, [pastoral.id]: token }
    const url = publicLinkFor(token)
    try {
      await navigator.clipboard.writeText(url)
      innovToast('success', 'Link', `Link de ${pastoral.nome} copiado`)
    } catch {
      innovToast('success', 'Link', 'Link gerado — copie abaixo')
    }
  } catch (e) {
    innovToast('error', 'Link', e.response?.data?.message || 'Falha ao gerar link')
  } finally {
    tokenBusy.value = ''
  }
}

async function carregarLinksPastorais() {
  if (!canGerir.value || !anual.value) return
  try {
    pastoralLinks.value = mapPastoralLinks(await listPastoralTokens(anual.value.id))
  } catch {
    pastoralLinks.value = {}
  }
}

async function revogarLinkPastoral(pastoral) {
  if (!canGerir.value || !anual.value) return
  const ok = await innovConfirm({
    title: 'Revogar link',
    message: `O link atual de ${pastoral.nome} deixará de funcionar. Continuar?`,
    confirmText: 'Revogar',
  })
  if (!ok) return
  tokenBusy.value = pastoral.id
  try {
    await revokePastoralToken(anual.value.id, pastoral.id)
    const { [pastoral.id]: _removido, ...resto } = pastoralLinks.value
    pastoralLinks.value = resto
    innovToast('success', 'Link', 'Link revogado')
  } catch (e) {
    innovToast('error', 'Link', e.response?.data?.message || 'Falha ao revogar')
  } finally {
    tokenBusy.value = ''
  }
}

async function copiarLinkPastoral(pastoralId) {
  const url = publicLinkFor(pastoralLinks.value[pastoralId])
  if (!url) return
  await navigator.clipboard.writeText(url)
  innovToast('success', 'Link', 'Copiado')
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
    recursos: buildRecursosPayload(selecaoRecursos.value),
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
    const [a, evs, pasts, locsRes, recs] = await Promise.all([
      getAnual(id),
      listEventos(id),
      listPastorais(),
      api.get('/calendario/locais').catch(() => ({ data: [] })),
      listRecursos().catch(() => []),
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
    recursosCatalogo.value = recs
    if (a.eventos && Array.isArray(a.eventos) && !evs.length) {
      eventos.value = a.eventos
    }
    if (!silent) await carregarLinksPastorais()
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
  if (!(payload.local_ids?.length || payload.local_texto || selecaoTemEspaco(selecaoRecursos.value))) {
    innovToast('error', 'Evento', 'Escolha um espaço ou informe o local em texto')
    return
  }
  capacidadeErro.value = ''
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
    showDayModal.value = false
    await load({ silent: true })
  } catch (e) {
    if (isErroCapacidade(e)) {
      capacidadeErro.value = formatConflitosRecursos(e.response.data)
      innovToast('error', 'Indisponível', capacidadeErro.value)
      carregarDisponibilidade()
    } else {
      innovToast('error', 'Evento', e.response?.data?.message || 'Falha ao salvar')
    }
  } finally {
    saving.value = false
  }
}

function onDragStart(ev, domEvent) {
  if (!podeArrastar.value || !podeEditar(ev)) return
  dragEventoId.value = ev.id
  domEvent.dataTransfer.effectAllowed = 'move'
  domEvent.dataTransfer.setData('text/plain', ev.id)
}

function onDragEnd() {
  dragEventoId.value = ''
  dropAlvo.value = ''
}

async function onDropDia(iso) {
  const id = dragEventoId.value
  onDragEnd()
  const ev = eventos.value.find((e) => e.id === id)
  if (!ev || !iso || String(ev.data_inicio).slice(0, 10) === iso) return
  const original = { data_inicio: ev.data_inicio, data_fim: ev.data_fim }
  const destino = moverEventoParaDia(ev, iso)
  eventos.value = eventos.value.map((e) => (e.id === id ? { ...e, ...destino } : e))
  try {
    const result = await updateEvento(id, destino)
    showConflitos(result.conflitos)
    innovToast('success', 'Evento', `"${ev.titulo}" movido para ${iso.split('-').reverse().join('/')}`)
    await load({ silent: true })
  } catch (e) {
    eventos.value = eventos.value.map((x) => (x.id === id ? { ...x, ...original } : x))
    const msg = isErroCapacidade(e)
      ? formatConflitosRecursos(e.response.data)
      : e.response?.data?.message || 'Não foi possível mover'
    innovToast('error', 'Evento não movido', msg)
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
  showDayModal.value = true
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
            v-if="canGerir"
            type="button"
            class="btn btn-ghost"
            data-testid="planejamento-link-recursos"
            @click="router.push('/planejamento/recursos')"
          >
            Espaços e recursos
          </button>
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
        <div v-if="espacosCatalogo.length">
          <label class="fld" for="pl-filtro-espaco">Espaço</label>
          <select
            id="pl-filtro-espaco"
            v-model="filtroEspaco"
            class="input min-w-[10rem]"
            data-testid="planejamento-filtro-espaco"
          >
            <option value="">Todos</option>
            <option v-for="r in espacosCatalogo" :key="r.id" :value="r.id">{{ r.nome }}</option>
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
        <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
          <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
            {{ mesTitulo }}
          </h2>
          <p class="text-[12px]" style="color: var(--color-muted)">
            Clique em um dia para ver eventos e propor<template v-if="podeArrastar">
              · arraste um evento para outro dia para mudar a data</template>
          </p>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-[11px] mb-1" style="color: var(--color-muted)">
          <div v-for="d in WEEKDAYS" :key="d">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <div
            v-for="(cell, idx) in cells"
            :key="idx"
            :role="cell.inMonth ? 'button' : undefined"
            :tabindex="cell.inMonth ? 0 : -1"
            class="min-h-[72px] md:min-h-[88px] rounded-lg p-1.5 text-left"
            :class="cell.inMonth ? 'cursor-pointer' : ''"
            :style="
              cell.inMonth
                ? {
                    background:
                      dropAlvo === cell.iso
                        ? 'var(--color-accent-soft)'
                        : diaSelecionado === cell.iso
                          ? 'var(--color-primary-soft)'
                          : 'var(--color-surface)',
                    color: diaSelecionado === cell.iso ? 'var(--color-on-primary)' : 'var(--color-ink)',
                    border:
                      dropAlvo === cell.iso
                        ? '1px dashed var(--color-accent-dark)'
                        : '1px solid var(--color-line)',
                  }
                : { background: 'transparent', cursor: 'default' }
            "
            :data-testid="cell.iso ? `planejamento-dia-${cell.iso}` : undefined"
            @click="cell.inMonth && selectDay(cell.iso)"
            @keydown.enter.prevent="cell.inMonth && selectDay(cell.iso)"
            @dragover.prevent="cell.inMonth && dragEventoId && (dropAlvo = cell.iso)"
            @dragleave="dropAlvo === cell.iso && (dropAlvo = '')"
            @drop.prevent="cell.inMonth && onDropDia(cell.iso)"
          >
            <template v-if="cell.inMonth">
              <div class="text-[12px] font-medium mb-0.5">{{ cell.dia }}</div>
              <div
                v-for="ev in (byDay.get(cell.iso) || []).slice(0, 2)"
                :key="ev.id"
                class="text-[10px] truncate leading-tight px-1 py-0.5 rounded mt-0.5 font-medium"
                :class="podeArrastar && podeEditar(ev) ? 'cursor-grab' : ''"
                :style="{
                  background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                  color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                  opacity: dragEventoId === ev.id ? 0.5 : 1,
                }"
                :draggable="podeArrastar && podeEditar(ev)"
                :title="[ev.titulo, labelRecursosEvento(ev)].filter(Boolean).join(' · ')"
                data-testid="planejamento-chip"
                @dragstart="onDragStart(ev, $event)"
                @dragend="onDragEnd"
              >
                {{ labelEventoPlanejamentoPreview(ev) }}
              </div>
              <div
                v-if="(byDay.get(cell.iso) || []).length > 2"
                class="text-[10px] opacity-70"
              >
                +{{ (byDay.get(cell.iso) || []).length - 2 }}
              </div>
            </template>
          </div>
        </div>
      </div>

      <section
        v-if="canGerir"
        class="card p-4 md:p-5"
        data-testid="planejamento-links-pastorais"
      >
        <h2 class="font-serif text-[18px] font-medium mb-1" style="color: var(--color-ink)">
          Links para as pastorais
        </h2>
        <p class="text-[13px] mb-4" style="color: var(--color-muted)">
          Gere um link por pastoral e envie para o coordenador propor datas sem login.
          A coleta precisa estar aberta (<strong>status Coleta</strong>) para eles enviarem propostas.
        </p>
        <ul v-if="pastorais.length" class="list-none p-0 m-0 space-y-3">
          <li
            v-for="p in pastorais"
            :key="p.id"
            class="rounded-lg px-3 py-3 flex flex-wrap items-start justify-between gap-3"
            style="border: 1px solid var(--color-line)"
          >
            <div class="min-w-0 flex-1">
              <div class="font-medium text-[14px]" style="color: var(--color-ink)">{{ p.nome }}</div>
              <code
                v-if="pastoralLinks[p.id]"
                class="block text-[11px] mt-1 break-all"
                style="color: var(--color-muted)"
                data-testid="planejamento-link-url"
              >
                {{ publicLinkFor(pastoralLinks[p.id]) }}
              </code>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
              <button
                type="button"
                class="btn btn-accent text-xs"
                :disabled="tokenBusy === p.id"
                data-testid="planejamento-gerar-link"
                @click="gerarLinkPastoral(p)"
              >
                {{ pastoralLinks[p.id] ? 'Novo link' : 'Gerar link' }}
              </button>
              <button
                v-if="pastoralLinks[p.id]"
                type="button"
                class="btn btn-ghost text-xs"
                @click="copiarLinkPastoral(p.id)"
              >
                Copiar
              </button>
              <button
                v-if="pastoralLinks[p.id]"
                type="button"
                class="btn btn-ghost text-xs"
                style="color: var(--color-danger)"
                :disabled="tokenBusy === p.id"
                data-testid="planejamento-revogar-link"
                @click="revogarLinkPastoral(p)"
              >
                Revogar
              </button>
            </div>
          </li>
        </ul>
        <p v-else class="text-[13px]" style="color: var(--color-muted)">
          Cadastre pastorais em
          <router-link to="/pastorais" class="underline" style="color: var(--color-primary)">
            Pastorais
          </router-link>
          para gerar links.
        </p>
      </section>

      <!-- Modal do dia -->
      <div
        v-if="showDayModal && diaSelecionado"
        class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
        style="background: rgba(42, 20, 24, 0.4)"
        data-testid="planejamento-day-modal"
        @click.self="closeDayModal"
      >
        <div
          class="w-full max-w-lg rounded-t-2xl md:rounded-2xl p-5 max-h-[90vh] overflow-y-auto"
          style="background: var(--color-surface)"
        >
          <div class="flex items-start justify-between gap-3 mb-4">
            <div>
              <p class="page-eyebrow">Dia selecionado</p>
              <h3 class="font-serif text-lg" style="color: var(--color-ink)">{{ diaSelecionado }}</h3>
            </div>
            <button type="button" class="btn btn-ghost text-xs" @click="closeDayModal">Fechar</button>
          </div>

          <ul v-if="diaEventos.length" class="list-none p-0 m-0 space-y-3 mb-4">
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
                      v-if="eventoTemConflito(ev) || ev.tem_conflito"
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
                  <div
                    v-if="labelRecursosEvento(ev) || ev.local_texto"
                    class="text-[12px] mt-0.5"
                    style="color: var(--color-muted)"
                    data-testid="planejamento-evento-recursos"
                  >
                    {{ [labelRecursosEvento(ev), ev.local_texto].filter(Boolean).join(' · ') }}
                  </div>
                  <span
                    class="inline-block mt-1 text-[11px] font-medium px-2 py-0.5 rounded-full"
                    :style="{
                      color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                      background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                    }"
                  >
                    {{ labelStatusSolicitacao(ev.status_solicitacao) }}
                  </span>
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
          <p v-else class="text-[13.5px] mb-4" style="color: var(--color-muted)">
            Nenhum evento neste dia.
          </p>

          <button
            v-if="podeCriar"
            type="button"
            class="btn btn-primary w-full"
            data-testid="planejamento-modal-novo"
            @click="openCreate(diaSelecionado)"
          >
            Propor evento neste dia
          </button>
          <p v-else class="text-[12.5px]" style="color: var(--color-muted)">
            Não é possível propor eventos neste status do ano.
          </p>
        </div>
      </div>

      <!-- Modal do formulário -->
      <div
        v-if="showForm"
        class="fixed inset-0 z-[60] flex items-end md:items-center justify-center"
        style="background: rgba(42, 20, 24, 0.45)"
        data-testid="planejamento-evento-form"
        @click.self="closeForm"
      >
        <div
          class="w-full max-w-xl rounded-t-2xl md:rounded-2xl p-5 max-h-[92vh] overflow-y-auto"
          style="background: var(--color-surface)"
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
                <Hora24Input id="ev-hi" v-model="form.hora_inicio" />
              </div>
              <div>
                <label class="fld" for="ev-hf">Hora fim</label>
                <Hora24Input id="ev-hf" v-model="form.hora_fim" />
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

            <PlanejamentoRecursosPicker
              v-model="selecaoRecursos"
              :disponibilidade="disponibilidade"
              :loading="dispLoading"
              :sem-data="!dispParams"
            />

            <p
              v-if="capacidadeErro"
              class="rounded-lg px-3 py-2 text-[13px]"
              style="background: var(--color-accent-soft); color: var(--color-danger); border: 1px solid var(--color-line)"
              data-testid="planejamento-capacidade-erro"
            >
              {{ capacidadeErro }}
            </p>

            <div>
              <label class="fld" for="ev-local-txt">Local (texto livre)</label>
              <input
                id="ev-local-txt"
                v-model="form.local_texto"
                class="input"
                placeholder="Use quando o evento for fora dos espaços cadastrados"
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
        </div>
      </div>
    </template>
  </div>
</template>
