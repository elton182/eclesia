<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm, innovPrompt } from '@/plugins/dialog'
import {
  agruparItensPorData,
  gradeMesCalendario,
  indexarIndisponibilidades,
  labelDiaCurto,
  labelMesCalendario,
  labelObservacaoOpcao,
  labelStatusCalendario,
  MES_NOMES,
  previewLinhasDia,
  proximosStatusCalendario,
  proximoMesCalendario,
  resumoDiaMontagem,
} from '@/utils/calendario'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { userHasPermission } from '@/utils/userRoles'

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()

const loading = ref(true)
const mensal = ref(null)
const locais = ref([])
const tipos = ref([])
const saving = ref(false)
const eventoForm = ref({
  local_id: '',
  tipo_id: '',
  titulo: '',
  hora: '',
  notas: '',
  celebrante_nome: '',
})
const linkUrl = ref('')
const diaSelecionado = ref('')
/** Rascunhos locais ainda não persistidos (permite várias linhas vazias de uma vez). */
const obsDrafts = ref([])
const savingObsIds = ref(new Set())

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'calendario.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)
const fechado = computed(() => mensal.value?.status === 'fechado')
const proximos = computed(() => proximosStatusCalendario(mensal.value?.status || ''))
const gradePorData = computed(() => agruparItensPorData(mensal.value?.itens, ['fds', 'semana']))
const todosPorData = computed(() => agruparItensPorData(mensal.value?.itens, []))
const indisponiveis = computed(() => indexarIndisponibilidades(mensal.value?.indisponibilidades))
const festas = computed(() => (mensal.value?.itens || []).filter((i) => i.secao === 'festa'))
const casamentos = computed(() => (mensal.value?.itens || []).filter((i) => i.secao === 'casamento'))
const observacoes = computed(() => mensal.value?.observacoes || [])
const observacoesTabela = computed(() => [...observacoes.value, ...obsDrafts.value])
const temposLiturgicos = computed(() => mensal.value?.tempos_liturgicos || [])

const cells = computed(() => {
  if (!mensal.value) return []
  return gradeMesCalendario(mensal.value.ano, mensal.value.mes)
})

const mesTitulo = computed(() => {
  if (!mensal.value) return ''
  return `${MES_NOMES[mensal.value.mes] || mensal.value.mes} ${mensal.value.ano}`
})

const diaResumo = computed(() => {
  if (!diaSelecionado.value) return null
  return resumoDiaMontagem(diaSelecionado.value, todosPorData.value, indisponiveis.value)
})

const diaItensGrade = computed(() => {
  if (!diaSelecionado.value) return []
  return gradePorData.value.get(diaSelecionado.value) || []
})

const diaFestas = computed(() => {
  if (!diaSelecionado.value) return []
  return (todosPorData.value.get(diaSelecionado.value) || []).filter((i) => i.secao === 'festa')
})

const diaCasamentos = computed(() => {
  if (!diaSelecionado.value) return []
  return (todosPorData.value.get(diaSelecionado.value) || []).filter((i) => i.secao === 'casamento')
})

const totalIndisponibilidades = computed(() => mensal.value?.indisponibilidades?.length || 0)

const tipoSelecionado = computed(() => tipos.value.find((t) => t.id === eventoForm.value.tipo_id))
const exigeTitulo = computed(() => !!tipoSelecionado.value?.exige_titulo)

function resetEventoForm() {
  eventoForm.value = {
    local_id: locais.value[0]?.id || '',
    tipo_id: tipos.value.find((t) => t.slug === 'missa')?.id || tipos.value[0]?.id || '',
    titulo: '',
    hora: '',
    notas: '',
    celebrante_nome: '',
  }
}

/** Normaliza hora para HH:00 (minutos sempre zero). */
function horaComMinutosZero(hora) {
  if (!hora) return ''
  const m = String(hora).match(/^(\d{1,2})/)
  if (!m) return hora
  return `${m[1].padStart(2, '0')}:00`
}

function onHoraInput() {
  if (eventoForm.value.hora) {
    eventoForm.value.hora = horaComMinutosZero(eventoForm.value.hora)
  }
}

/** @type {import('vue').ComputedRef<Map<string, ReturnType<typeof resumoDiaMontagem>>>} */
const resumoPorIso = computed(() => {
  const map = new Map()
  for (const cell of cells.value) {
    if (!cell.iso) continue
    map.set(cell.iso, resumoDiaMontagem(cell.iso, todosPorData.value, indisponiveis.value))
  }
  return map
})

function cellResumo(iso) {
  return (
    resumoPorIso.value.get(iso) || {
      iso,
      itens: [],
      indisponiveis: [],
      temGrade: false,
      temIndisponivel: false,
    }
  )
}

function linhasPreview(iso) {
  return previewLinhasDia(cellResumo(iso).itens, 2)
}

/** Normaliza item da API (data ISO, hora curta). */
function normalizeItem(raw) {
  const item = raw?.data && typeof raw.data === 'object' && raw.data.id ? raw.data : raw
  const data = item?.data
  const dataIso =
    typeof data === 'string'
      ? data.slice(0, 10)
      : data && typeof data === 'object' && data.date
        ? String(data.date).slice(0, 10)
        : data
  return { ...item, data: dataIso }
}

function upsertItemLocal(item) {
  const normalized = normalizeItem(item)
  const atual = [...(mensal.value?.itens || [])]
  const idx = atual.findIndex((i) => i.id === normalized.id)
  if (idx >= 0) atual[idx] = { ...atual[idx], ...normalized }
  else atual.push(normalized)
  mensal.value = { ...mensal.value, itens: atual }
}

function selecionarDia(iso) {
  if (!iso) return
  diaSelecionado.value = iso
  resetEventoForm()
}

function fecharDiaModal() {
  diaSelecionado.value = ''
  resetEventoForm()
}

function onDiaModalKeydown(e) {
  if (e.key === 'Escape' && diaSelecionado.value) {
    e.preventDefault()
    fecharDiaModal()
  }
}

watch(diaSelecionado, (iso) => {
  if (iso) {
    document.addEventListener('keydown', onDiaModalKeydown)
    document.body.style.overflow = 'hidden'
  } else {
    document.removeEventListener('keydown', onDiaModalKeydown)
    document.body.style.overflow = ''
  }
})

async function load(opts = {}) {
  const silent = !!opts.silent
  if (!silent) loading.value = true
  try {
    const [m, l, t] = await Promise.all([
      api.get(`/calendario/mensais/${route.params.id}`),
      api.get('/calendario/locais'),
      api.get('/calendario/evento-tipos'),
    ])
    mensal.value = m.data.data || m.data
    if (Array.isArray(mensal.value.itens)) {
      mensal.value = {
        ...mensal.value,
        itens: mensal.value.itens.map(normalizeItem),
      }
    }
    locais.value = l.data.data || l.data || []
    tipos.value = t.data.data || t.data || []
    if (!silent) {
      resetEventoForm()
      obsDrafts.value = []
    }
    syncLinkUrl()
  } catch (e) {
    innovToast('error', 'Calendário', e.response?.data?.message || 'Falha ao carregar')
    if (!silent) router.push('/calendario')
  } finally {
    if (!silent) loading.value = false
  }
}

function buildColetaUrl(token) {
  if (!token) return ''
  const tenant = tenantStore.slug
  const qs = tenant ? `?tenant=${encodeURIComponent(tenant)}` : ''
  return `${window.location.origin}/calendario/coleta/${token}${qs}`
}

function syncLinkUrl() {
  const links = mensal.value?.coleta_links || []
  const ativo = [...links].reverse().find((l) => l.ativo && l.token)
  linkUrl.value = ativo ? buildColetaUrl(ativo.token) : ''
}

async function salvarMeta() {
  saving.value = true
  try {
    const { data } = await api.put(`/calendario/mensais/${mensal.value.id}`, {
      titulo: mensal.value.titulo,
      subtitulo: mensal.value.subtitulo,
    })
    const payload = data.data || data
    mensal.value = {
      ...mensal.value,
      ...payload,
      itens: mensal.value.itens,
      observacoes: mensal.value.observacoes,
      tempos_liturgicos: mensal.value.tempos_liturgicos,
      indisponibilidades: mensal.value.indisponibilidades,
      coleta_links: mensal.value.coleta_links,
    }
    innovToast('success', 'Calendário', 'Salvo')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha')
  } finally {
    saving.value = false
  }
}

async function adicionarLinhaObs() {
  const localId = `draft-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  obsDrafts.value.push({ localId, titulo: '', descricao: '' })
  await nextTick()
  document.getElementById(`obs-titulo-${localId}`)?.focus()
}

function isObsDraft(obs) {
  return Boolean(obs?.localId) && !obs?.id
}

function obsRowKey(obs) {
  return obs.id || obs.localId
}

function isObsSaving(obs) {
  return savingObsIds.value.has(obsRowKey(obs))
}

async function persistirOuSalvarObs(obs) {
  const titulo = String(obs.titulo || '').trim()
  const descricao = String(obs.descricao || '').trim()
  const key = obsRowKey(obs)

  if (isObsDraft(obs)) {
    if (!titulo || !descricao) return
    if (savingObsIds.value.has(key)) return
    const next = new Set(savingObsIds.value)
    next.add(key)
    savingObsIds.value = next
    try {
      const { data } = await api.post(`/calendario/mensais/${mensal.value.id}/observacoes`, {
        titulo,
        descricao,
      })
      const created = data.data || data
      obsDrafts.value = obsDrafts.value.filter((d) => d.localId !== obs.localId)
      mensal.value = {
        ...mensal.value,
        observacoes: [...(mensal.value.observacoes || []), created],
      }
    } catch (e) {
      innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao adicionar')
    } finally {
      const done = new Set(savingObsIds.value)
      done.delete(key)
      savingObsIds.value = done
    }
    return
  }

  await salvarObservacao(obs)
}

async function salvarObservacao(obs) {
  try {
    const { data } = await api.put(`/calendario/observacoes/${obs.id}`, {
      titulo: obs.titulo,
      descricao: obs.descricao,
      ordem: obs.ordem,
    })
    const updated = data.data || data
    mensal.value = {
      ...mensal.value,
      observacoes: (mensal.value.observacoes || []).map((o) =>
        o.id === updated.id ? { ...o, ...updated } : o,
      ),
    }
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar observação')
  }
}

async function removerObservacao(obs) {
  if (isObsDraft(obs)) {
    const vazia = !String(obs.titulo || '').trim() && !String(obs.descricao || '').trim()
    if (!vazia) {
      const ok = await innovConfirm({
        title: 'Descartar linha',
        message: 'Esta observação ainda não foi salva. Descartar?',
        confirmText: 'Descartar',
        danger: true,
      })
      if (!ok) return
    }
    obsDrafts.value = obsDrafts.value.filter((d) => d.localId !== obs.localId)
    return
  }

  const rotulo = String(obs.titulo || '').trim() || 'esta observação'
  const ok = await innovConfirm({
    title: 'Remover observação',
    message: `Remover “${rotulo}”? Células que a referenciam perdem o vínculo.`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await api.delete(`/calendario/observacoes/${obs.id}`)
    mensal.value = {
      ...mensal.value,
      observacoes: (mensal.value.observacoes || []).filter((o) => o.id !== obs.id),
      itens: (mensal.value.itens || []).map((i) =>
        i.observacao_id === obs.id ? { ...i, observacao_id: null } : i,
      ),
    }
    innovToast('success', 'Observação', 'Removida')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

async function salvarTempoLiturgico(tempo) {
  try {
    const { data } = await api.put(`/calendario/tempos-liturgicos/${tempo.id}`, {
      rotulo: tempo.rotulo || '',
    })
    const updated = data.data || data
    mensal.value = {
      ...mensal.value,
      tempos_liturgicos: (mensal.value.tempos_liturgicos || []).map((t) =>
        t.id === updated.id ? { ...t, ...updated } : t,
      ),
    }
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar tempo litúrgico')
  }
}

function labelObsSelect(obs, index) {
  return labelObservacaoOpcao(obs, index + 1)
}

async function mudarStatus(status) {
  try {
    const { data } = await api.post(`/calendario/mensais/${mensal.value.id}/status`, { status })
    mensal.value = data.data || data
    innovToast('success', 'Calendário', `Status: ${labelStatusCalendario(status)}`)
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Transição inválida')
  }
}

async function salvarItem(item) {
  try {
    const { data } = await api.put(`/calendario/itens/${item.id}`, {
      celebrante_nome: item.celebrante_nome,
      notas: item.notas,
      titulo: item.titulo,
      observacao_id: item.observacao_id || null,
    })
    upsertItemLocal(data.data || data)
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar item')
  }
}

async function removerItem(item) {
  if (fechado.value || !canManage.value) return
  const rotulo =
    [item.hora && String(item.hora).slice(0, 5), item.local?.nome || item.titulo || item.celebrante_nome]
      .filter(Boolean)
      .join(' · ') || 'este evento'
  const ok = await innovConfirm({
    title: 'Remover evento',
    message: `Remover ${rotulo} do calendário?`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await api.delete(`/calendario/itens/${item.id}`)
    mensal.value = {
      ...mensal.value,
      itens: (mensal.value.itens || []).filter((i) => i.id !== item.id),
    }
    innovToast('success', 'Calendário', 'Evento removido')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

async function addEvento() {
  try {
    const dataIso = diaSelecionado.value
    if (!dataIso) {
      innovToast('error', 'Evento', 'Selecione um dia.')
      return
    }
    if (!eventoForm.value.tipo_id) {
      innovToast('error', 'Evento', 'Selecione o tipo.')
      return
    }
    if (!eventoForm.value.hora) {
      innovToast('error', 'Evento', 'Informe o horário.')
      return
    }
    if (exigeTitulo.value && !eventoForm.value.titulo.trim()) {
      innovToast('error', 'Evento', 'Informe o nome do evento.')
      return
    }
    const payload = {
      data: dataIso,
      tipo_id: eventoForm.value.tipo_id,
      local_id: eventoForm.value.local_id || null,
      hora: horaComMinutosZero(eventoForm.value.hora),
      notas: eventoForm.value.notas || null,
      celebrante_nome: eventoForm.value.celebrante_nome || null,
      titulo: exigeTitulo.value ? eventoForm.value.titulo.trim() : null,
    }
    await api.post(`/calendario/mensais/${mensal.value.id}/itens`, payload)
    innovToast('success', 'Evento', 'Adicionado')
    fecharDiaModal()
    await load({ silent: true })
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao adicionar')
  }
}

async function gerarLink() {
  try {
    const { data } = await api.post(`/calendario/mensais/${mensal.value.id}/coleta-links`, {
      rotulo: 'Coleta de indisponibilidades',
    })
    const token = data.token || data.data?.token
    linkUrl.value = buildColetaUrl(token)
    if (data.data) {
      const links = mensal.value.coleta_links || []
      const idx = links.findIndex((l) => l.id === data.data.id)
      mensal.value.coleta_links =
        idx >= 0
          ? links.map((l, i) => (i === idx ? { ...l, ...data.data } : l))
          : [...links, data.data]
    }
    if (linkUrl.value && navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(linkUrl.value)
        innovToast('success', 'Coleta', 'Link copiado')
        return
      } catch {
        /* fallback toast abaixo */
      }
    }
    innovToast('success', 'Coleta', 'Link pronto para compartilhar')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao gerar link')
  }
}

async function baixarPdf() {
  try {
    const res = await api.get(`/calendario/mensais/${mensal.value.id}/pdf`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `calendario-${mensal.value.ano}-${String(mensal.value.mes).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    innovToast('error', 'PDF', e.response?.data?.message || 'Falha ao exportar')
  }
}

async function copiarProximo() {
  if (!canManage.value || !mensal.value) return
  const dest = proximoMesCalendario(mensal.value.ano, mensal.value.mes)
  const ok = await innovConfirm({
    title: 'Copiar para o próximo mês',
    message: `Criar ${labelMesCalendario(dest.ano, dest.mes)} a partir deste mês? Observações e celebrantes da grade serão copiados.`,
    confirmText: 'Copiar',
  })
  if (!ok) return
  saving.value = true
  try {
    const { data } = await api.post(`/calendario/mensais/${mensal.value.id}/copiar-proximo`)
    const created = data.data || data
    innovToast('success', 'Calendário', `Rascunho de ${labelMesCalendario(created.ano, created.mes)} criado`)
    router.push(`/calendario/${created.id}`)
  } catch (e) {
    innovToast('error', 'Calendário', e.response?.data?.message || 'Falha ao copiar')
  } finally {
    saving.value = false
  }
}

async function excluirMensal() {
  if (!canManage.value || !mensal.value) return
  const ok = await innovConfirm({
    title: 'Excluir calendário',
    message: `Excluir ${labelMesCalendario(mensal.value.ano, mensal.value.mes)}? Esta ação não pode ser desfeita.`,
    confirmText: 'Excluir',
  })
  if (!ok) return
  saving.value = true
  try {
    await api.delete(`/calendario/mensais/${mensal.value.id}`)
    innovToast('success', 'Calendário', 'Excluído')
    router.push('/calendario')
  } catch (e) {
    innovToast('error', 'Calendário', e.response?.data?.message || 'Falha ao excluir')
  } finally {
    saving.value = false
  }
}

async function ensureLocal() {
  if (locais.value.length) return
  const nome = await innovPrompt({
    title: 'Primeiro local',
    message: 'Cadastre um local de celebração para montar a grade (ex.: Matriz, São Gabriel).',
    label: 'Nome do local',
    placeholder: 'MATRIZ',
    confirmText: 'Cadastrar',
  })
  if (!nome) return
  const { data } = await api.post('/calendario/locais', { nome, ordem: 1 })
  locais.value = [data.data || data]
}

onMounted(async () => {
  await load()
  if (canManage.value) await ensureLocal()
})

onUnmounted(() => {
  document.removeEventListener('keydown', onDiaModalKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div
    v-if="loading"
    class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 text-[14px]"
    style="color: var(--color-muted)"
  >
    Carregando…
  </div>
  <div
    v-else-if="mensal"
    class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full space-y-6"
    data-testid="calendario-detalhe"
  >
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <button
          type="button"
          class="text-[13px] mb-2 border-0 bg-transparent cursor-pointer p-0"
          style="color: var(--color-muted)"
          @click="router.push('/calendario')"
        >
          ← Voltar
        </button>
        <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
          {{ labelMesCalendario(mensal.ano, mensal.mes) }}
        </h1>
        <p class="text-[14px] mt-1" style="color: var(--color-muted)">
          {{ labelStatusCalendario(mensal.status) }}
          <span v-if="totalIndisponibilidades">
            · {{ totalIndisponibilidades }}
            {{ totalIndisponibilidades === 1 ? 'indisponibilidade' : 'indisponibilidades' }}
          </span>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="st in proximos"
          :key="st"
          type="button"
          class="btn btn-ghost"
          :data-testid="`calendario-status-${st}`"
          :disabled="!canManage"
          @click="mudarStatus(st)"
        >
          {{ labelStatusCalendario(st) }}
        </button>
        <button
          v-if="canManage"
          type="button"
          class="btn btn-ghost"
          data-testid="calendario-copiar-proximo"
          :disabled="saving"
          @click="copiarProximo"
        >
          Copiar p/ próximo
        </button>
        <button type="button" class="btn btn-primary" data-testid="calendario-pdf" @click="baixarPdf">
          Exportar PDF
        </button>
        <button
          v-if="canManage"
          type="button"
          class="btn btn-ghost"
          style="color: var(--color-danger)"
          data-testid="calendario-excluir"
          :disabled="saving"
          @click="excluirMensal"
        >
          Excluir
        </button>
      </div>
    </div>

    <div class="card p-4 md:p-5 space-y-4" data-testid="calendario-montagem-grade">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
            {{ mesTitulo }}
          </h2>
          <p class="text-[13px] mt-0.5" style="color: var(--color-muted)">
            Clique em um dia para abrir o formulário de celebrantes e indisponibilidades.
          </p>
        </div>
        <div class="flex flex-wrap gap-3 text-[11px]" style="color: var(--color-muted)">
          <span class="inline-flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full" style="background: var(--color-primary-soft)" />
            Celebração
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full" style="background: var(--color-danger)" />
            Indisponível
          </span>
        </div>
      </div>

      <div
        class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase tracking-wide"
        style="color: var(--color-muted)"
        aria-hidden="true"
      >
        <span v-for="w in WEEKDAYS" :key="w" class="py-1">{{ w }}</span>
      </div>

      <div
        class="grid grid-cols-7 gap-1.5 sm:gap-2"
        role="grid"
        :aria-label="`Montagem de ${mesTitulo}`"
      >
        <template v-for="(cell, idx) in cells" :key="cell.iso || `pad-${idx}`">
          <div v-if="!cell.inMonth" class="min-h-[4.5rem] sm:min-h-[5.5rem]" aria-hidden="true" />
          <button
            v-else
            type="button"
            class="montagem-dia text-left p-1.5 sm:p-2 rounded-lg transition-all duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
            :class="{
              'montagem-dia-sel': diaSelecionado === cell.iso,
              'montagem-dia-ind': cellResumo(cell.iso).temIndisponivel,
              'montagem-dia-grade': cellResumo(cell.iso).temGrade,
            }"
            :aria-pressed="diaSelecionado === cell.iso"
            :data-testid="`montagem-dia-${cell.dia}`"
            @click="selecionarDia(cell.iso)"
          >
            <div class="flex items-start justify-between gap-1 mb-1">
              <span class="text-sm font-semibold" style="color: var(--color-ink)">{{ cell.dia }}</span>
              <span class="flex items-center gap-0.5">
                <span
                  v-if="cellResumo(cell.iso).temGrade"
                  class="w-1.5 h-1.5 rounded-full"
                  style="background: var(--color-primary-soft)"
                  title="Com celebração"
                />
                <span
                  v-if="cellResumo(cell.iso).temIndisponivel"
                  class="w-1.5 h-1.5 rounded-full"
                  style="background: var(--color-danger)"
                  title="Com indisponibilidade"
                />
              </span>
            </div>
            <div
              v-if="linhasPreview(cell.iso).length"
              class="space-y-0.5"
            >
              <div
                v-for="(linha, li) in linhasPreview(cell.iso)"
                :key="`${cell.iso}-${li}`"
                class="text-[10px] leading-tight truncate"
                style="color: var(--color-ink)"
              >
                {{ linha }}
              </div>
            </div>
            <div
              v-if="cellResumo(cell.iso).temIndisponivel"
              class="text-[10px] leading-tight mt-0.5 truncate"
              style="color: var(--color-danger)"
            >
              {{ cellResumo(cell.iso).indisponiveis.slice(0, 2).join(', ') }}
              <span v-if="cellResumo(cell.iso).indisponiveis.length > 2">…</span>
            </div>
          </button>
        </template>
      </div>
    </div>

    <div v-if="canManage && !fechado" class="card p-4 md:p-5 space-y-3">
      <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
        Coleta de indisponibilidades
      </h2>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-ghost" data-testid="calendario-gerar-link" @click="gerarLink">
          {{ linkUrl ? 'Copiar link' : 'Gerar link público' }}
        </button>
      </div>
      <p v-if="linkUrl" class="text-sm break-all font-mono" data-testid="calendario-link-url">
        {{ linkUrl }}
      </p>
      <p v-if="totalIndisponibilidades" class="text-sm" style="color: var(--color-muted)">
        {{ totalIndisponibilidades }} registro(s) — veja os dias marcados em vermelho na grade.
      </p>
    </div>

    <details class="card p-4 md:p-5">
      <summary
        class="font-serif text-[18px] font-medium cursor-pointer"
        style="color: var(--color-ink)"
      >
        Cabeçalho do PDF
      </summary>
      <div class="mt-4 space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="fld">Título</label>
            <input v-model="mensal.titulo" class="input" :disabled="fechado || !canManage" />
          </div>
          <div>
            <label class="fld">Subtítulo</label>
            <input v-model="mensal.subtitulo" class="input" :disabled="fechado || !canManage" />
          </div>
        </div>
        <div v-if="canManage && !fechado" class="flex justify-end">
          <button type="button" class="btn btn-ghost" :disabled="saving" @click="salvarMeta">
            Salvar cabeçalho
          </button>
        </div>
      </div>
    </details>

    <details class="card p-4 md:p-5" data-testid="calendario-tempos-liturgicos">
      <summary
        class="font-serif text-[18px] font-medium cursor-pointer"
        style="color: var(--color-ink)"
      >
        Tempo litúrgico (fins de semana)
      </summary>
      <p class="mt-2 text-sm" style="color: var(--color-muted)">
        Rótulo por domingo — aparece na linha “Tempo Litúrgico” do PDF.
      </p>
      <div class="mt-4 space-y-3">
        <div
          v-for="tempo in temposLiturgicos"
          :key="tempo.id"
          class="grid gap-2 md:grid-cols-[7rem_1fr] items-center"
        >
          <span class="text-sm font-medium" style="color: var(--color-ink)">
            {{ labelDiaCurto(tempo.data_domingo) }}
          </span>
          <input
            v-model="tempo.rotulo"
            class="input"
            placeholder="Ex.: 23º Domingo do Tempo Comum"
            :disabled="fechado || !canManage"
            :data-testid="`tempo-rotulo-${tempo.id}`"
            @change="salvarTempoLiturgico(tempo)"
          />
        </div>
        <p v-if="!temposLiturgicos.length" class="text-sm" style="color: var(--color-muted)">
          Nenhum domingo neste mês.
        </p>
      </div>
    </details>

    <details class="card p-4 md:p-5" open data-testid="calendario-observacoes">
      <summary
        class="font-serif text-[18px] font-medium cursor-pointer"
        style="color: var(--color-ink)"
      >
        Observações fixas
      </summary>
      <p class="mt-2 text-sm" style="color: var(--color-muted)">
        Clique em Adicionar para criar linhas e preencha à vontade — salva ao completar título e
        descrição. Na grade do dia, associe a observação à celebração; a lista aparece no final do
        PDF.
      </p>

      <p
        v-if="!observacoesTabela.length"
        class="mt-4 text-sm"
        style="color: var(--color-muted)"
      >
        Nenhuma observação cadastrada ainda.
      </p>

      <div
        v-if="observacoesTabela.length"
        class="mt-4 overflow-x-auto md:rounded-xl md:border"
        style="border-color: var(--color-line)"
        data-testid="calendario-observacoes-tabela"
      >
        <table class="obs-fixas-table w-full text-sm text-left">
          <thead class="obs-fixas-head" style="background: var(--color-bg); color: var(--color-ink)">
            <tr>
              <th class="px-3 py-2.5 font-medium min-w-[10rem]">Título</th>
              <th class="px-3 py-2.5 font-medium min-w-[16rem]">Descrição</th>
              <th v-if="canManage && !fechado" class="px-3 py-2.5 font-medium text-right w-24">
                <span class="sr-only">Ações</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="obs in observacoesTabela"
              :key="obsRowKey(obs)"
              class="obs-fixas-row border-t align-top"
              style="border-color: var(--color-line)"
            >
              <td class="px-3 py-2" data-label="Título">
                <label class="fld md:sr-only" :for="`obs-titulo-${obsRowKey(obs)}`">Título</label>
                <input
                  :id="`obs-titulo-${obsRowKey(obs)}`"
                  v-model="obs.titulo"
                  class="input md:!py-1.5 md:!text-sm"
                  placeholder="Ex.: ECC"
                  :disabled="fechado || !canManage || isObsSaving(obs)"
                  @change="persistirOuSalvarObs(obs)"
                />
              </td>
              <td class="px-3 py-2" data-label="Descrição">
                <label class="fld md:sr-only" :for="`obs-desc-${obsRowKey(obs)}`">Descrição</label>
                <textarea
                  :id="`obs-desc-${obsRowKey(obs)}`"
                  v-model="obs.descricao"
                  class="input min-h-[4rem] md:min-h-[2.75rem] md:!py-1.5 md:!text-sm"
                  placeholder="Texto que aparece no PDF"
                  :disabled="fechado || !canManage || isObsSaving(obs)"
                  @change="persistirOuSalvarObs(obs)"
                />
              </td>
              <td
                v-if="canManage && !fechado"
                class="px-3 py-2 text-right"
                data-label="Ações"
              >
                <button
                  type="button"
                  class="btn btn-ghost !py-1 !px-2 text-xs"
                  style="color: var(--color-danger)"
                  :data-testid="`obs-remover-${obsRowKey(obs)}`"
                  :disabled="isObsSaving(obs)"
                  @click="removerObservacao(obs)"
                >
                  Remover
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="canManage && !fechado" class="mt-4 flex justify-end">
        <button
          type="button"
          class="btn btn-ghost"
          data-testid="obs-add-linha"
          :disabled="savingObsIds.size > 0"
          @click="adicionarLinhaObs"
        >
          + Adicionar observação
        </button>
      </div>
    </details>

    <details v-if="festas.length || casamentos.length" class="card p-4 md:p-5">
      <summary
        class="font-serif text-[18px] font-medium cursor-pointer"
        style="color: var(--color-ink)"
      >
        Festas e casamentos (lista)
      </summary>
      <ul class="mt-3 text-sm space-y-1 list-none p-0 m-0">
        <li v-for="f in festas" :key="f.id" style="color: var(--color-ink)">
          {{ f.data }} {{ f.hora }} — {{ f.titulo }} · {{ f.celebrante_nome }}
        </li>
        <li v-for="c in casamentos" :key="c.id" style="color: var(--color-ink)">
          {{ c.data }} {{ c.hora }} — {{ c.local?.nome }} · {{ c.celebrante_nome }}
        </li>
      </ul>
    </details>

    <Teleport to="body">
      <Transition name="dia-modal-fade">
        <div
          v-if="diaSelecionado && diaResumo"
          class="fixed inset-0 z-[180] flex items-end sm:items-center justify-center p-0 sm:p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="montagem-dia-titulo"
          data-testid="montagem-dia-modal"
        >
          <div
            class="fixed inset-0"
            style="background: rgba(42, 20, 24, 0.35); backdrop-filter: blur(2px)"
            data-testid="montagem-dia-backdrop"
            @click="fecharDiaModal"
          />

          <div
            class="relative w-full sm:max-w-lg md:max-w-4xl lg:max-w-5xl max-h-[92vh] overflow-y-auto rounded-t-2xl sm:rounded-xl p-5 md:p-6 shadow-xl space-y-4"
            style="background: var(--color-surface); border: 1px solid var(--color-line)"
            data-testid="montagem-dia-painel"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2
                  id="montagem-dia-titulo"
                  class="font-serif text-[20px] font-medium"
                  style="color: var(--color-ink)"
                >
                  {{ labelDiaCurto(diaSelecionado) }}
                </h2>
                <p class="text-[13px] mt-0.5" style="color: var(--color-muted)">
                  {{ diaSelecionado }} · adicionar celebração à grade ou editar celebrantes
                </p>
              </div>
              <button
                type="button"
                class="btn btn-ghost !px-2.5 !py-1.5 text-lg leading-none"
                aria-label="Fechar"
                @click="fecharDiaModal"
              >
                ×
              </button>
            </div>

            <div
              v-if="diaResumo.temIndisponivel"
              class="rounded-lg px-3 py-2.5 text-sm space-y-1"
              style="background: color-mix(in srgb, var(--color-danger) 8%, transparent)"
              data-testid="montagem-dia-indisponiveis"
            >
              <p class="text-xs font-medium uppercase tracking-wide" style="color: var(--color-danger)">
                Indisponíveis neste dia
              </p>
              <p style="color: var(--color-ink)">{{ diaResumo.indisponiveis.join(', ') }}</p>
            </div>
            <p v-else class="text-sm" style="color: var(--color-muted)">
              Nenhuma indisponibilidade registrada neste dia.
            </p>

            <div class="space-y-3">
              <h3 class="text-xs font-medium uppercase tracking-wide" style="color: var(--color-muted)">
                Celebrações do dia
              </h3>

              <p v-if="!diaItensGrade.length" class="text-sm" style="color: var(--color-muted)">
                Nenhuma celebração neste dia ainda.
              </p>

              <div
                v-else
                class="overflow-x-auto md:rounded-xl md:border"
                style="border-color: var(--color-line)"
                data-testid="montagem-dia-tabela"
              >
                <table class="montagem-grade-table w-full text-sm text-left">
                  <thead class="montagem-grade-head" style="background: var(--color-bg); color: var(--color-ink)">
                    <tr>
                      <th class="px-3 py-2.5 font-medium whitespace-nowrap">Hora</th>
                      <th class="px-3 py-2.5 font-medium">Local</th>
                      <th class="px-3 py-2.5 font-medium">Tipo</th>
                      <th class="px-3 py-2.5 font-medium min-w-[9rem]">Celebrante</th>
                      <th class="px-3 py-2.5 font-medium min-w-[12rem]">Notas</th>
                      <th class="px-3 py-2.5 font-medium min-w-[10rem]">Observação</th>
                      <th v-if="canManage && !fechado" class="px-3 py-2.5 font-medium text-right w-24">
                        <span class="sr-only">Ações</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="item in diaItensGrade"
                      :key="item.id"
                      class="montagem-grade-row border-t align-top"
                      style="border-color: var(--color-line)"
                    >
                      <td
                        class="montagem-grade-meta px-3 py-2.5 md:whitespace-nowrap font-medium"
                        style="color: var(--color-ink)"
                        data-label="Hora"
                      >
                        <div class="flex items-start justify-between gap-2 md:block">
                          <span class="md:hidden text-sm font-medium" style="color: var(--color-ink)">
                            {{ item.tipo?.nome || item.titulo || 'Evento' }}
                            · {{ item.local?.nome || '—' }} · {{ item.hora || '—' }}
                          </span>
                          <span class="hidden md:inline">{{ item.hora || '—' }}</span>
                          <button
                            v-if="canManage && !fechado"
                            type="button"
                            class="btn btn-ghost !py-1 !px-2 text-xs md:hidden shrink-0"
                            style="color: var(--color-danger)"
                            :data-testid="`montagem-remover-m-${item.id}`"
                            @click="removerItem(item)"
                          >
                            Remover
                          </button>
                        </div>
                      </td>
                      <td
                        class="montagem-grade-hide-sm px-3 py-2.5"
                        style="color: var(--color-ink)"
                        data-label="Local"
                      >
                        {{ item.local?.nome || '—' }}
                      </td>
                      <td
                        class="montagem-grade-hide-sm px-3 py-2.5"
                        style="color: var(--color-muted)"
                        data-label="Tipo"
                      >
                        {{ item.tipo?.nome || item.titulo || 'Evento' }}
                      </td>
                      <td class="px-3 py-2" data-label="Celebrante">
                        <label class="fld md:sr-only" :for="`cel-${item.id}`">Celebrante</label>
                        <input
                          :id="`cel-${item.id}`"
                          v-model="item.celebrante_nome"
                          class="input md:!py-1.5 md:!text-sm"
                          placeholder="Nome do celebrante"
                          :disabled="fechado || !canManage"
                          @change="salvarItem(item)"
                        />
                      </td>
                      <td class="px-3 py-2" data-label="Notas">
                        <label class="fld md:sr-only" :for="`notas-${item.id}`">Notas</label>
                        <input
                          :id="`notas-${item.id}`"
                          v-model="item.notas"
                          class="input md:!py-1.5 md:!text-sm"
                          placeholder="Notas"
                          :disabled="fechado || !canManage"
                          @change="salvarItem(item)"
                        />
                      </td>
                      <td class="px-3 py-2" data-label="Observação">
                        <label class="fld md:sr-only" :for="`obs-${item.id}`">Observação fixa</label>
                        <select
                          :id="`obs-${item.id}`"
                          v-model="item.observacao_id"
                          class="input md:!py-1.5 md:!text-sm"
                          :disabled="fechado || !canManage"
                          :data-testid="`item-obs-${item.id}`"
                          @change="salvarItem(item)"
                        >
                          <option value="">— nenhuma —</option>
                          <option
                            v-for="(obs, idx) in observacoes"
                            :key="obs.id"
                            :value="obs.id"
                          >
                            {{ labelObsSelect(obs, idx) }}
                          </option>
                        </select>
                      </td>
                      <td
                        v-if="canManage && !fechado"
                        class="montagem-grade-hide-sm px-3 py-2 text-right"
                        data-label="Ações"
                      >
                        <button
                          type="button"
                          class="btn btn-ghost !py-1 !px-2 text-xs"
                          style="color: var(--color-danger)"
                          :data-testid="`montagem-remover-${item.id}`"
                          @click="removerItem(item)"
                        >
                          Remover
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="diaFestas.length || diaCasamentos.length" class="space-y-2 text-sm">
              <div
                v-for="f in diaFestas"
                :key="f.id"
                class="flex items-center justify-between gap-2"
                style="color: var(--color-ink)"
              >
                <span>Festa · {{ f.hora || '—' }} — {{ f.titulo }} · {{ f.celebrante_nome || '—' }}</span>
                <button
                  v-if="canManage && !fechado"
                  type="button"
                  class="btn btn-ghost !py-1 !px-2 text-xs"
                  style="color: var(--color-danger)"
                  @click="removerItem(f)"
                >
                  Remover
                </button>
              </div>
              <div
                v-for="c in diaCasamentos"
                :key="c.id"
                class="flex items-center justify-between gap-2"
                style="color: var(--color-ink)"
              >
                <span>
                  Casamento · {{ c.hora || '—' }} — {{ c.local?.nome || '—' }} ·
                  {{ c.celebrante_nome || '—' }}
                </span>
                <button
                  v-if="canManage && !fechado"
                  type="button"
                  class="btn btn-ghost !py-1 !px-2 text-xs"
                  style="color: var(--color-danger)"
                  @click="removerItem(c)"
                >
                  Remover
                </button>
              </div>
            </div>

            <p
              v-if="fechado"
              class="text-sm rounded-lg px-3 py-2"
              style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
            >
              Calendário fechado. Use o botão <strong>Montagem</strong> no topo para reabrir e
              editar.
            </p>

            <form
              v-if="canManage && !fechado"
              class="border-t pt-4 space-y-3"
              style="border-color: var(--color-line)"
              data-testid="montagem-add-evento"
              @submit.prevent="addEvento"
            >
              <h3 class="font-serif text-[16px] font-medium" style="color: var(--color-ink)">
                Adicionar evento
              </h3>
              <p
                v-if="!locais.length"
                class="text-sm"
                style="color: var(--color-danger)"
              >
                Cadastre um local em
                <router-link to="/calendario/locais" class="underline">Locais e horários</router-link>.
              </p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="fld" for="evt-local">Local</label>
                  <select
                    id="evt-local"
                    v-model="eventoForm.local_id"
                    class="input"
                    data-testid="montagem-evento-local"
                  >
                    <option value="">—</option>
                    <option v-for="loc in locais" :key="loc.id" :value="loc.id">{{ loc.nome }}</option>
                  </select>
                </div>
                <div>
                  <label class="fld" for="evt-tipo">Tipo</label>
                  <select
                    id="evt-tipo"
                    v-model="eventoForm.tipo_id"
                    class="input"
                    required
                    data-testid="montagem-evento-tipo"
                  >
                    <option disabled value="">Selecione…</option>
                    <option v-for="tp in tipos" :key="tp.id" :value="tp.id">{{ tp.nome }}</option>
                  </select>
                </div>
                <div v-if="exigeTitulo" class="md:col-span-2">
                  <label class="fld" for="evt-titulo">Nome do evento</label>
                  <input
                    id="evt-titulo"
                    v-model="eventoForm.titulo"
                    class="input"
                    placeholder="Descreva o evento"
                    required
                    data-testid="montagem-evento-titulo"
                  />
                </div>
                <div>
                  <label class="fld" for="evt-hora">Hora</label>
                  <input
                    id="evt-hora"
                    v-model="eventoForm.hora"
                    type="time"
                    step="3600"
                    class="input"
                    required
                    data-testid="montagem-evento-hora"
                    @change="onHoraInput"
                    @blur="onHoraInput"
                  />
                </div>
                <div>
                  <label class="fld" for="evt-cel">Celebrante</label>
                  <input
                    id="evt-cel"
                    v-model="eventoForm.celebrante_nome"
                    class="input"
                    placeholder="Nome do celebrante"
                    data-testid="montagem-evento-celebrante"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="fld" for="evt-notas">Notas</label>
                  <input
                    id="evt-notas"
                    v-model="eventoForm.notas"
                    class="input"
                    placeholder="Opcional"
                  />
                </div>
              </div>
              <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
                <button type="button" class="btn btn-ghost" @click="fecharDiaModal">
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  data-testid="montagem-evento-add"
                  :disabled="!eventoForm.tipo_id || !eventoForm.hora || (exigeTitulo && !eventoForm.titulo.trim())"
                >
                  Adicionar
                </button>
              </div>
            </form>

            <div v-else class="flex justify-end pt-1">
              <button type="button" class="btn btn-ghost" @click="fecharDiaModal">Fechar</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.montagem-dia {
  min-height: 4.5rem;
  background: var(--color-surface-2);
  border: 1px solid transparent;
  vertical-align: top;
}
@media (min-width: 640px) {
  .montagem-dia {
    min-height: 5.5rem;
  }
}
.montagem-dia:hover {
  border-color: var(--color-line);
  background: var(--color-surface);
}
.montagem-dia-grade {
  background: var(--color-surface);
  border-color: var(--color-line);
}
.montagem-dia-ind {
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-danger) 35%, transparent);
}
.montagem-dia-sel {
  border-color: var(--color-primary-soft) !important;
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-soft) 28%, transparent);
  background: var(--color-surface) !important;
}
button:focus-visible {
  outline-color: var(--color-primary-soft);
}
.dia-modal-fade-enter-active,
.dia-modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.dia-modal-fade-enter-from,
.dia-modal-fade-leave-to {
  opacity: 0;
}

/* Mobile: tabela vira cartões empilhados */
@media (max-width: 767px) {
  .montagem-grade-table,
  .montagem-grade-table tbody,
  .obs-fixas-table,
  .obs-fixas-table tbody {
    display: block;
    width: 100%;
  }
  .montagem-grade-head,
  .obs-fixas-head {
    display: none;
  }
  .montagem-grade-row,
  .obs-fixas-row {
    display: block;
    padding-top: 0.75rem;
    margin-top: 0.75rem;
  }
  .montagem-grade-row:first-child,
  .obs-fixas-row:first-child {
    margin-top: 0;
  }
  .montagem-grade-row > td,
  .obs-fixas-row > td {
    display: block;
    width: 100%;
    padding-left: 0;
    padding-right: 0;
    white-space: normal;
  }
  .montagem-grade-hide-sm {
    display: none !important;
  }
  .montagem-grade-meta {
    padding-bottom: 0.25rem;
  }
  .obs-fixas-row > td[data-label="Ações"] {
    text-align: right;
  }
}
</style>
