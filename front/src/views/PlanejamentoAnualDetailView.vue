<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { createPastoral, listPastorais } from '@/services/pastorais'
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
  addDiasIso,
  formatHoraCalendario,
  gradeMesCalendario,
  gradeSemanaCalendario,
  inicioSemanaDomingo,
  labelDiaCurto,
} from '@/utils/calendario'
import {
  STATUS_SOLICITACAO,
  anotarConflitosEventos,
  canAlterarStatusSolicitacao,
  canCriarEventoPlanejamento,
  canEditarEventoPlanejamento,
  contarEventosComConflito,
  filterEventosPorMes,
  filterEventosPorPastoraisVisiveis,
  filterEventosPorSemana,
  formatConflitosAlert,
  groupEventosByDay,
  labelEventoPlanejamentoPreview,
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  mapaCoresPastorais,
  GRADE_SEMANA_HORA_FIM,
  GRADE_SEMANA_HORA_INICIO,
  horasGradeSemana,
  layoutEventoGradeSemana,
  mapPastoralLinks,
  particionarEventosGradeSemana,
  pastoralIdsDoUsuario,
  proximosStatusPlanejamento,
  styleStatusSolicitacao,
  togglePastoralVisivel,
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
import DateInput from '@/components/form/DateInput.vue'
import SelectSearchable from '@/components/form/SelectSearchable.vue'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import { brToIso, isoToBr } from '@/utils/dateBr'

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
/** @type {import('vue').Ref<'mes'|'semana'>} */
const visaoCalendario = ref('mes')
const semanaInicio = ref(
  inicioSemanaDomingo(
    `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}-${String(new Date().getDate()).padStart(2, '0')}`,
  ),
)
/** @type {import('vue').Ref<Set<string>>} */
const pastoraisVisiveis = ref(new Set())
const pastoraisFiltroInicializado = ref(false)
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
const novaPastoralNome = ref('')
const salvandoPastoral = ref(false)
/** @type {import('vue').Ref<''|'agenda'|'links'>} */
const pdfBusy = ref('')
let dispTimer = null
let dispSeq = 0

const horasGrade = horasGradeSemana(GRADE_SEMANA_HORA_INICIO, GRADE_SEMANA_HORA_FIM)
const gradeHoraPx = 48
const gradeBodyHeightPx = horasGrade.length * gradeHoraPx

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

const pastoraisSelectOptions = computed(() =>
  pastoraisOpcoes.value.map((p) => ({ value: p.id, label: p.nome })),
)

const pastoraisPainel = computed(() => {
  if (canVerGlobal.value) return pastorais.value
  return pastoraisOpcoes.value
})

const coresPastorais = computed(() => mapaCoresPastorais(pastoraisPainel.value))

const eventosFiltrados = computed(() => {
  let list =
    visaoCalendario.value === 'semana'
      ? filterEventosPorSemana(eventos.value, semanaInicio.value)
      : filterEventosPorMes(eventos.value, mesView.value)
  if (canVerGlobal.value || pastoraisPainel.value.length) {
    list = filterEventosPorPastoraisVisiveis(list, pastoraisVisiveis.value)
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
  if (visaoCalendario.value === 'semana') {
    return gradeSemanaCalendario(semanaInicio.value, {
      ano: anual.value.ano,
      mes: mesView.value,
    })
  }
  return gradeMesCalendario(anual.value.ano, mesView.value)
})

const mesTitulo = computed(() => `${MES_NOMES[mesView.value] || mesView.value} ${anual.value?.ano || ''}`)

const periodoTitulo = computed(() => {
  if (visaoCalendario.value !== 'semana' || !semanaInicio.value) return mesTitulo.value
  const fim = addDiasIso(semanaInicio.value, 6)
  return `${labelDiaCurto(semanaInicio.value)} – ${labelDiaCurto(fim)} · ${anual.value?.ano || ''}`
})

const conflitosVisiveisCount = computed(() => contarEventosComConflito(eventosFiltrados.value))

const maxChipsPorDia = computed(() => (visaoCalendario.value === 'semana' ? 8 : 2))

const diaEventos = computed(() => {
  if (!diaSelecionado.value) return []
  return byDay.value.get(diaSelecionado.value) || []
})

const espacosCatalogo = computed(() => recursosCatalogo.value.filter((r) => r.tipo === 'espaco'))
const podeArrastar = computed(() => canGerir.value && anoStatus.value !== 'fechado')
const dispParams = computed(() =>
  disponibilidadeParams(
    {
      ...form.value,
      data_inicio: brToIso(form.value.data_inicio) || '',
      data_fim: brToIso(form.value.data_fim) || '',
    },
    editingEvento.value?.id || undefined,
  ),
)

const statusSolicitacaoOptions = computed(() =>
  Object.entries(STATUS_SOLICITACAO).map(([value, label]) => ({ value, label })),
)

function eventoTemConflito(ev) {
  return ev?.tem_conflito === true || (Array.isArray(ev?.conflitos) && ev.conflitos.length > 0)
}

function podeEditar(ev) {
  return canEditarEventoPlanejamento(anoStatus.value, ev, perms.value)
}

function pastoralNome(id) {
  return pastorais.value.find((p) => p.id === id)?.nome || ''
}

function pastoralCor(id) {
  return coresPastorais.value[id] || 'var(--color-primary)'
}

function eventosDoDia(iso) {
  return byDay.value.get(iso) || []
}

function eventosSemHora(iso) {
  return particionarEventosGradeSemana(eventosDoDia(iso)).semHora
}

function eventosComHora(iso) {
  return particionarEventosGradeSemana(eventosDoDia(iso)).comHora
}

function estiloEventoGrade(ev) {
  const layout = layoutEventoGradeSemana(ev, {
    inicioHora: GRADE_SEMANA_HORA_INICIO,
    fimHora: GRADE_SEMANA_HORA_FIM,
  })
  if (!layout) return {}
  return {
    top: `${layout.topPct}%`,
    height: `${layout.heightPct}%`,
    ...chipStyle(ev),
  }
}

function chipStyle(ev) {
  const cor = pastoralCor(ev.pastoral_id)
  const conflito = eventoTemConflito(ev)
  return {
    background: conflito ? 'var(--color-accent-soft)' : `${cor}22`,
    color: conflito ? 'var(--color-accent-dark)' : cor,
    border: conflito ? '1px solid var(--color-accent-dark)' : `1px solid ${cor}55`,
    opacity: dragEventoId.value === ev.id ? 0.5 : 1,
  }
}

/** ids conhecidos no último sync (para detectar pastorais novas) */
const pastoraisIdsConhecidos = ref(/** @type {Set<string>} */ (new Set()))

function syncPastoraisVisiveis(list) {
  const ids = (list || []).map((p) => p.id).filter(Boolean)
  if (!pastoraisFiltroInicializado.value) {
    pastoraisVisiveis.value = new Set(ids)
    pastoraisIdsConhecidos.value = new Set(ids)
    pastoraisFiltroInicializado.value = true
    return
  }
  const prev = pastoraisVisiveis.value
  const known = pastoraisIdsConhecidos.value
  const next = new Set()
  for (const id of ids) {
    if (prev.has(id)) next.add(id)
    else if (!known.has(id)) next.add(id)
  }
  pastoraisVisiveis.value = next
  pastoraisIdsConhecidos.value = new Set(ids)
}

function marcarTodasPastorais() {
  pastoraisVisiveis.value = new Set(pastoraisPainel.value.map((p) => p.id).filter(Boolean))
}

function desmarcarTodasPastorais() {
  pastoraisVisiveis.value = new Set()
}

function onTogglePastoral(id) {
  pastoraisVisiveis.value = togglePastoralVisivel(pastoraisVisiveis.value, id)
}

function setVisao(modo) {
  visaoCalendario.value = modo === 'semana' ? 'semana' : 'mes'
  if (visaoCalendario.value === 'semana' && anual.value) {
    const ancora =
      diaSelecionado.value ||
      `${anual.value.ano}-${String(mesView.value).padStart(2, '0')}-01`
    semanaInicio.value = inicioSemanaDomingo(ancora)
  }
}

function navegarSemana(delta) {
  semanaInicio.value = addDiasIso(semanaInicio.value, delta * 7)
  const mid = addDiasIso(semanaInicio.value, 3)
  if (mid) {
    const m = Number(mid.slice(5, 7))
    if (m >= 1 && m <= 12) mesView.value = m
  }
}

watch(mesView, (mes) => {
  if (!anual.value || visaoCalendario.value !== 'semana') return
  const ancora = `${anual.value.ano}-${String(mes).padStart(2, '0')}-01`
  const atual = semanaInicio.value || ''
  if (!atual.startsWith(`${anual.value.ano}-${String(mes).padStart(2, '0')}`)) {
    semanaInicio.value = inicioSemanaDomingo(ancora)
  }
})

function openCreate(dayIso) {
  if (!podeCriar.value) return
  editingEvento.value = null
  form.value = emptyForm()
  const iso = dayIso || diaSelecionado.value || ''
  form.value.data_inicio = isoToBr(iso)
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
    data_inicio: isoToBr((ev.data_inicio || '').slice(0, 10)),
    data_fim: ev.data_fim ? isoToBr(String(ev.data_fim).slice(0, 10)) : '',
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
  const dataInicio = brToIso(f.data_inicio)
  const dataFim = brToIso(f.data_fim)
  const payload = {
    pastoral_id: f.pastoral_id,
    titulo: f.titulo.trim(),
    data_inicio: dataInicio,
    data_fim: dataFim || null,
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
    eventos.value = anotarConflitosEventos(evs)
    pastorais.value = pasts
    syncPastoraisVisiveis(
      canVerGlobal.value
        ? pasts
        : pasts.filter((p) => !myPastoralIds.value.length || myPastoralIds.value.includes(p.id)),
    )
    if (visaoCalendario.value === 'semana') {
      const ancora = `${a.ano}-${String(mesView.value).padStart(2, '0')}-01`
      if (!semanaInicio.value) semanaInicio.value = inicioSemanaDomingo(ancora)
    }
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
    innovToast('error', 'Evento', 'Informe a data no formato dd/mm/aaaa')
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

/**
 * @param {'agenda'|'links'} tipo
 */
async function baixarPdf(tipo) {
  if (pdfBusy.value || !anual.value) return
  pdfBusy.value = tipo
  try {
    const params =
      tipo === 'links'
        ? { tipo: 'links' }
        : { tipo: 'agenda', mes: mesView.value }
    const blob = await downloadAnualPdf(anual.value.id, params)
    if (blob?.type && blob.type.includes('json')) {
      const text = await blob.text()
      const body = JSON.parse(text)
      throw Object.assign(new Error('pdf'), {
        response: { data: body },
      })
    }
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download =
      tipo === 'links'
        ? `planejamento-links-${anual.value.ano}.pdf`
        : `planejamento-agenda-${anual.value.ano}-${String(mesView.value).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
    if (tipo === 'links') {
      await carregarLinksPastorais()
      innovToast(
        'success',
        'PDF',
        'Links das pastorais · tokens faltantes foram criados',
      )
    } else {
      innovToast('success', 'PDF', `Agenda de ${MES_NOMES[mesView.value] || mesView.value}`)
    }
  } catch (e) {
    let msg = 'Falha ao exportar'
    const data = e.response?.data
    if (data instanceof Blob) {
      try {
        const body = JSON.parse(await data.text())
        msg = body.message || body.errors?.status?.[0] || body.errors?.mes?.[0] || msg
      } catch {
        /* ignore */
      }
    } else if (data?.message || data?.errors?.status?.[0] || data?.errors?.mes?.[0]) {
      msg = data.message || data.errors?.status?.[0] || data.errors?.mes?.[0]
    }
    innovToast('error', 'PDF', msg)
  } finally {
    pdfBusy.value = ''
  }
}

async function incluirPastoral() {
  if (!canGerir.value) return
  const nome = novaPastoralNome.value.trim()
  if (!nome) {
    innovToast('error', 'Pastoral', 'Informe o nome')
    return
  }
  salvandoPastoral.value = true
  try {
    const created = await createPastoral({ nome, ativa: true })
    novaPastoralNome.value = ''
    innovToast('success', 'Pastoral', 'Incluída no planejamento')
    await load({ silent: true })
    if (created?.id) {
      try {
        await gerarLinkPastoral(created)
      } catch {
        /* link opcional */
      }
    }
  } catch (e) {
    innovToast('error', 'Pastoral', e.response?.data?.message || 'Falha ao incluir')
  } finally {
    salvandoPastoral.value = false
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
          <template v-if="canGerir || anoStatus === 'fechado' || anoStatus === 'revisao' || anoStatus === 'coleta'">
            <button
              type="button"
              class="btn btn-ghost"
              data-testid="planejamento-pdf-agenda"
              :disabled="!!pdfBusy || anoStatus === 'rascunho'"
              @click="baixarPdf('agenda')"
            >
              {{ pdfBusy === 'agenda' ? 'Gerando…' : 'PDF agenda' }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              data-testid="planejamento-pdf-links"
              :disabled="!!pdfBusy || anoStatus === 'rascunho'"
              @click="baixarPdf('links')"
            >
              {{ pdfBusy === 'links' ? 'Gerando…' : 'PDF links' }}
            </button>
          </template>
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

      <div
        v-if="conflitosVisiveisCount > 0"
        class="rounded-xl px-4 py-3 text-[13.5px] flex flex-wrap items-center gap-2"
        style="background: var(--color-accent-soft); color: var(--color-accent-dark); border: 1px solid var(--color-line)"
        data-testid="planejamento-conflitos-resumo"
      >
        <span class="font-medium">{{ conflitosVisiveisCount }} evento(s) com conflito de local/horário</span>
        <span class="text-[12px]" style="color: var(--color-muted)">
          Destacados na grade — a gravação não é bloqueada por este tipo de conflito.
        </span>
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
        <div>
          <span class="fld">Visão</span>
          <div
            class="flex rounded-lg overflow-hidden"
            style="border: 1px solid var(--color-line)"
            role="group"
            aria-label="Visão do calendário"
            data-testid="planejamento-visao-toggle"
          >
            <button
              type="button"
              class="btn !rounded-none !px-3 text-xs"
              :class="visaoCalendario === 'mes' ? 'btn-primary' : 'btn-ghost'"
              data-testid="planejamento-visao-mes"
              @click="setVisao('mes')"
            >
              Mensal
            </button>
            <button
              type="button"
              class="btn !rounded-none !px-3 text-xs"
              :class="visaoCalendario === 'semana' ? 'btn-primary' : 'btn-ghost'"
              data-testid="planejamento-visao-semana"
              @click="setVisao('semana')"
            >
              Semanal
            </button>
          </div>
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

      <div class="flex flex-col lg:flex-row gap-4 items-start">
        <aside
          v-if="canGerir || pastoraisPainel.length"
          class="card p-4 w-full lg:w-56 shrink-0"
          data-testid="planejamento-filtro-pastorais"
        >
          <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="font-serif text-[16px] font-medium m-0" style="color: var(--color-ink)">
              Pastorais
            </h2>
            <div v-if="pastoraisPainel.length" class="flex gap-1">
              <button
                type="button"
                class="btn btn-ghost !py-0.5 !px-2 text-[11px]"
                data-testid="planejamento-pastorais-todas"
                @click="marcarTodasPastorais"
              >
                Todas
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-0.5 !px-2 text-[11px]"
                data-testid="planejamento-pastorais-nenhuma"
                @click="desmarcarTodasPastorais"
              >
                Nenhuma
              </button>
            </div>
          </div>
          <ul v-if="pastoraisPainel.length" class="list-none p-0 m-0 space-y-1.5">
            <li v-for="p in pastoraisPainel" :key="p.id">
              <label
                class="flex items-center gap-2 cursor-pointer rounded-md px-1.5 py-1.5 text-[13px]"
                style="color: var(--color-ink)"
                :data-testid="`planejamento-pastoral-check-${p.id}`"
              >
                <input
                  type="checkbox"
                  class="shrink-0"
                  :checked="pastoraisVisiveis.has(p.id)"
                  @change="onTogglePastoral(p.id)"
                />
                <span
                  class="inline-block w-2.5 h-2.5 rounded-sm shrink-0"
                  :style="{ background: pastoralCor(p.id) }"
                  aria-hidden="true"
                />
                <span class="truncate">{{ p.nome }}</span>
              </label>
            </li>
          </ul>
          <p v-else class="text-[12.5px] mb-3" style="color: var(--color-muted)">
            Nenhuma pastoral ainda.
          </p>
          <form
            v-if="canGerir"
            class="mt-3 pt-3 flex flex-col gap-2"
            style="border-top: 1px solid var(--color-line)"
            data-testid="planejamento-incluir-pastoral"
            @submit.prevent="incluirPastoral"
          >
            <label class="fld" for="pl-nova-pastoral">Incluir pastoral</label>
            <input
              id="pl-nova-pastoral"
              v-model="novaPastoralNome"
              class="input"
              placeholder="Nome…"
              maxlength="120"
              data-testid="planejamento-nova-pastoral-nome"
            />
            <button
              type="submit"
              class="btn btn-accent text-xs w-full"
              :disabled="salvandoPastoral"
              data-testid="planejamento-nova-pastoral-salvar"
            >
              {{ salvandoPastoral ? 'Salvando…' : 'Incluir' }}
            </button>
          </form>
        </aside>

        <div class="card p-4 md:p-5 flex-1 min-w-0 w-full" data-testid="planejamento-grade">
          <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
            <div class="flex flex-wrap items-center gap-2">
              <template v-if="visaoCalendario === 'semana'">
                <button
                  type="button"
                  class="btn btn-ghost !px-2 text-xs"
                  data-testid="planejamento-semana-prev"
                  @click="navegarSemana(-1)"
                >
                  ←
                </button>
                <h2 class="font-serif text-[18px] font-medium m-0" style="color: var(--color-ink)">
                  {{ periodoTitulo }}
                </h2>
                <button
                  type="button"
                  class="btn btn-ghost !px-2 text-xs"
                  data-testid="planejamento-semana-next"
                  @click="navegarSemana(1)"
                >
                  →
                </button>
              </template>
              <h2
                v-else
                class="font-serif text-[18px] font-medium m-0"
                style="color: var(--color-ink)"
              >
                {{ mesTitulo }}
              </h2>
            </div>
            <p class="text-[12px]" style="color: var(--color-muted)">
              Clique em um dia para ver eventos e propor<template v-if="podeArrastar">
                · arraste um evento para outro dia para mudar a data</template>
            </p>
          </div>
          <!-- Visão semanal com eixo de horas -->
          <div
            v-if="visaoCalendario === 'semana'"
            class="overflow-x-auto"
            data-testid="planejamento-semana-horas"
          >
            <div class="min-w-[720px]">
              <div
                class="grid gap-0"
                style="grid-template-columns: 3.25rem repeat(7, minmax(0, 1fr))"
              >
                <div class="text-[10px] pr-1 pb-2" style="color: var(--color-muted)">GMT-03</div>
                <div
                  v-for="(cell, idx) in cells"
                  :key="`h-${idx}`"
                  class="text-center pb-2 cursor-pointer"
                  :data-testid="cell.iso ? `planejamento-dia-${cell.iso}` : undefined"
                  @click="cell.iso && selectDay(cell.iso)"
                >
                  <div class="text-[10px] uppercase tracking-wide" style="color: var(--color-muted)">
                    {{ WEEKDAYS[idx] }}
                  </div>
                  <div
                    class="font-serif text-[20px] leading-none mt-0.5"
                    :style="{
                      color:
                        diaSelecionado === cell.iso ? 'var(--color-primary)' : 'var(--color-ink)',
                    }"
                  >
                    {{ cell.dia }}
                  </div>
                </div>
              </div>

              <div
                class="grid gap-0 mb-1"
                style="grid-template-columns: 3.25rem repeat(7, minmax(0, 1fr))"
              >
                <div class="text-[10px] pr-1 pt-1" style="color: var(--color-muted)">Dia todo</div>
                <div
                  v-for="(cell, idx) in cells"
                  :key="`a-${idx}`"
                  class="min-h-[28px] px-0.5 py-0.5 rounded-sm"
                  :style="{
                    border: '1px solid var(--color-line)',
                    background:
                      dropAlvo === cell.iso ? 'var(--color-accent-soft)' : 'var(--color-surface)',
                  }"
                  @click="cell.iso && selectDay(cell.iso)"
                  @dragover.prevent="cell.iso && dragEventoId && (dropAlvo = cell.iso)"
                  @dragleave="dropAlvo === cell.iso && (dropAlvo = '')"
                  @drop.prevent="cell.iso && onDropDia(cell.iso)"
                >
                  <div
                    v-for="ev in eventosSemHora(cell.iso)"
                    :key="ev.id"
                    class="text-[10px] truncate leading-tight px-1 py-0.5 rounded mb-0.5 font-medium"
                    :class="podeArrastar && podeEditar(ev) ? 'cursor-grab' : ''"
                    :style="chipStyle(ev)"
                    :draggable="podeArrastar && podeEditar(ev)"
                    :data-testid="eventoTemConflito(ev) ? 'planejamento-chip-conflito' : 'planejamento-chip'"
                    @dragstart="onDragStart(ev, $event)"
                    @dragend="onDragEnd"
                    @click.stop="selectDay(cell.iso)"
                  >
                    {{ labelEventoPlanejamentoPreview(ev) }}
                  </div>
                </div>
              </div>

              <div
                class="grid gap-0 relative"
                :style="{
                  gridTemplateColumns: '3.25rem repeat(7, minmax(0, 1fr))',
                  height: gradeBodyHeightPx + 'px',
                }"
              >
                <div class="relative">
                  <div
                    v-for="(h, hi) in horasGrade"
                    :key="h"
                    class="absolute left-0 right-0 text-[10px] pr-1 -translate-y-1/2 text-right"
                    :style="{ top: hi * gradeHoraPx + 'px', color: 'var(--color-muted)' }"
                  >
                    {{ h }}
                  </div>
                </div>
                <div
                  v-for="(cell, idx) in cells"
                  :key="`c-${idx}`"
                  class="relative cursor-pointer"
                  :style="{
                    borderLeft: '1px solid var(--color-line)',
                    borderRight: idx === 6 ? '1px solid var(--color-line)' : undefined,
                    background:
                      dropAlvo === cell.iso
                        ? 'var(--color-accent-soft)'
                        : diaSelecionado === cell.iso
                          ? 'color-mix(in srgb, var(--color-primary-soft) 55%, transparent)'
                          : 'transparent',
                    opacity: cell.inMonth ? 1 : 0.55,
                  }"
                  @click="cell.iso && selectDay(cell.iso)"
                  @dragover.prevent="cell.iso && dragEventoId && (dropAlvo = cell.iso)"
                  @dragleave="dropAlvo === cell.iso && (dropAlvo = '')"
                  @drop.prevent="cell.iso && onDropDia(cell.iso)"
                >
                  <div
                    v-for="(h, hi) in horasGrade"
                    :key="`${cell.iso}-${h}`"
                    class="absolute left-0 right-0"
                    :style="{
                      top: hi * gradeHoraPx + 'px',
                      borderTop: '1px solid var(--color-line)',
                      opacity: 0.7,
                    }"
                  />
                  <div
                    v-for="ev in eventosComHora(cell.iso)"
                    :key="ev.id"
                    class="absolute left-0.5 right-0.5 text-[10px] leading-tight px-1 py-0.5 rounded font-medium overflow-hidden z-[1]"
                    :class="podeArrastar && podeEditar(ev) ? 'cursor-grab' : ''"
                    :style="estiloEventoGrade(ev)"
                    :draggable="podeArrastar && podeEditar(ev)"
                    :title="
                      [
                        ev.titulo,
                        pastoralNome(ev.pastoral_id),
                        labelRecursosEvento(ev),
                        eventoTemConflito(ev) ? 'Conflito de agenda' : '',
                      ]
                        .filter(Boolean)
                        .join(' · ')
                    "
                    :data-testid="eventoTemConflito(ev) ? 'planejamento-chip-conflito' : 'planejamento-chip'"
                    @dragstart="onDragStart(ev, $event)"
                    @dragend="onDragEnd"
                    @click.stop="selectDay(cell.iso)"
                  >
                    {{ labelEventoPlanejamentoPreview(ev) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Visão mensal -->
          <template v-else>
            <div class="grid grid-cols-7 gap-1 text-center text-[11px] mb-1" style="color: var(--color-muted)">
              <div v-for="d in WEEKDAYS" :key="d">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7 gap-1">
              <div
                v-for="(cell, idx) in cells"
                :key="idx"
                :role="cell.iso && cell.inMonth ? 'button' : undefined"
                :tabindex="cell.iso && cell.inMonth ? 0 : -1"
                class="rounded-lg p-1.5 text-left min-h-[72px] md:min-h-[88px]"
                :class="cell.iso && cell.inMonth ? 'cursor-pointer' : ''"
                :style="
                  cell.iso && cell.inMonth
                    ? {
                        background:
                          dropAlvo === cell.iso
                            ? 'var(--color-accent-soft)'
                            : diaSelecionado === cell.iso
                              ? 'var(--color-primary-soft)'
                              : 'var(--color-surface)',
                        color:
                          diaSelecionado === cell.iso ? 'var(--color-on-primary)' : 'var(--color-ink)',
                        border:
                          dropAlvo === cell.iso
                            ? '1px dashed var(--color-accent-dark)'
                            : (byDay.get(cell.iso) || []).some(eventoTemConflito)
                              ? '1.5px solid var(--color-accent-dark)'
                              : '1px solid var(--color-line)',
                      }
                    : { background: 'transparent', cursor: 'default' }
                "
                :data-testid="cell.iso ? `planejamento-dia-${cell.iso}` : undefined"
                @click="cell.iso && cell.inMonth && selectDay(cell.iso)"
                @keydown.enter.prevent="cell.iso && cell.inMonth && selectDay(cell.iso)"
                @dragover.prevent="
                  cell.iso && cell.inMonth && dragEventoId && (dropAlvo = cell.iso)
                "
                @dragleave="dropAlvo === cell.iso && (dropAlvo = '')"
                @drop.prevent="cell.iso && cell.inMonth && onDropDia(cell.iso)"
              >
                <template v-if="cell.iso && cell.inMonth">
                  <div class="text-[12px] font-medium mb-0.5 flex items-center justify-between gap-1">
                    <span>{{ cell.dia }}</span>
                    <span
                      v-if="(byDay.get(cell.iso) || []).some(eventoTemConflito)"
                      class="text-[9px] font-semibold uppercase tracking-wide"
                      style="color: var(--color-accent-dark)"
                      data-testid="planejamento-dia-conflito"
                    >
                      Conflito
                    </span>
                  </div>
                  <div
                    v-for="ev in (byDay.get(cell.iso) || []).slice(0, maxChipsPorDia)"
                    :key="ev.id"
                    class="text-[10px] truncate leading-tight px-1 py-0.5 rounded mt-0.5 font-medium"
                    :class="podeArrastar && podeEditar(ev) ? 'cursor-grab' : ''"
                    :style="chipStyle(ev)"
                    :draggable="podeArrastar && podeEditar(ev)"
                    :title="
                      [
                        ev.titulo,
                        pastoralNome(ev.pastoral_id),
                        labelRecursosEvento(ev),
                        eventoTemConflito(ev) ? 'Conflito de agenda' : '',
                      ]
                        .filter(Boolean)
                        .join(' · ')
                    "
                    :data-testid="eventoTemConflito(ev) ? 'planejamento-chip-conflito' : 'planejamento-chip'"
                    @dragstart="onDragStart(ev, $event)"
                    @dragend="onDragEnd"
                  >
                    {{ labelEventoPlanejamentoPreview(ev) }}
                  </div>
                  <div
                    v-if="(byDay.get(cell.iso) || []).length > maxChipsPorDia"
                    class="text-[10px] opacity-70"
                  >
                    +{{ (byDay.get(cell.iso) || []).length - maxChipsPorDia }}
                  </div>
                </template>
              </div>
            </div>
          </template>

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
              <h3 class="font-serif text-lg" style="color: var(--color-ink)">
                {{ isoToBr(diaSelecionado) }}
              </h3>
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
                <SelectSearchable
                  id="ev-pastoral"
                  v-model="form.pastoral_id"
                  :options="pastoraisSelectOptions"
                  placeholder="Buscar pastoral…"
                  required
                  :disabled="!canGerir && !!editingEvento"
                  data-testid="planejamento-evento-pastoral"
                />
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
              <DateInput
                v-model="form.data_inicio"
                name="ev-inicio"
                label="Data início"
                required
              />
              <DateInput v-model="form.data_fim" name="ev-fim" label="Data fim" />
              <div>
                <label class="fld" for="ev-hi">Hora início</label>
                <input
                  id="ev-hi"
                  v-model="form.hora_inicio"
                  type="time"
                  class="input"
                  data-testid="planejamento-evento-hora-inicio"
                />
              </div>
              <div>
                <label class="fld" for="ev-hf">Hora fim</label>
                <input
                  id="ev-hf"
                  v-model="form.hora_fim"
                  type="time"
                  class="input"
                  data-testid="planejamento-evento-hora-fim"
                />
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
