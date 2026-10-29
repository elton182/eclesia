<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { MES_NOMES, formatHoraCalendario, gradeMesCalendario } from '@/utils/calendario'
import {
  canEditarEventoPlanejamentoPublico,
  groupEventosByDay,
  labelEventoPlanejamentoPreview,
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  styleStatusSolicitacao,
} from '@/utils/planejamento'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import {
  buildRecursosPayload,
  disponibilidadeParams,
  emptySelecaoRecursos,
  formatConflitosRecursos,
  isErroCapacidade,
  labelRecursosEvento,
  selecaoFromEvento,
  selecaoTemEspaco,
} from '@/utils/planejamentoRecursos'
import { getDisponibilidadePublica } from '@/services/planejamentoRecursos'
import {
  createEventoPublico,
  deleteEventoPublico,
  getPublicoMeta,
  listEventosPublicos,
  updateEventoPublico,
} from '@/services/planejamento'
import PlanejamentoRecursosPicker from '@/components/planejamento/PlanejamentoRecursosPicker.vue'
import DateInput from '@/components/form/DateInput.vue'
import { brToIso, isoToBr } from '@/utils/dateBr'

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const route = useRoute()
const tenantStore = useTenantStore()
const token = computed(() => String(route.params.token || ''))
const tenant = computed(() => String(route.query.tenant || ''))

const meta = ref(null)
const eventos = ref([])
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const mesView = ref(new Date().getMonth() + 1)
const diaSelecionado = ref('')
const showDayModal = ref(false)
const editingEvento = ref(null)

const emptyForm = () => ({
  titulo: '',
  data_inicio: '',
  hora_inicio: '',
  hora_fim: '',
  local_texto: '',
  observacoes: '',
})
const form = ref(emptyForm())
const selecaoRecursos = ref(emptySelecaoRecursos())
const disponibilidade = ref([])
const dispLoading = ref(false)
const capacidadeErro = ref('')
let dispTimer = null
let dispSeq = 0

const headers = computed(() => ({
  'X-Tenant': tenant.value || tenantStore.slug || '',
}))

const podePropor = computed(() => meta.value?.status === 'coleta')
const podeMutarForm = computed(() => {
  if (editingEvento.value) {
    return canEditarEventoPlanejamentoPublico(meta.value?.status, editingEvento.value)
  }
  return podePropor.value
})
const formTitulo = computed(() => (editingEvento.value ? 'Editar proposta' : 'Nova proposta'))
const submitLabel = computed(() => {
  if (saving.value) return editingEvento.value ? 'Salvando…' : 'Enviando…'
  return editingEvento.value ? 'Salvar alterações' : 'Enviar proposta'
})

const dispParams = computed(() =>
  disponibilidadeParams(
    {
      ...form.value,
      data_inicio: brToIso(form.value.data_inicio) || '',
    },
    editingEvento.value?.id,
  ),
)
const temEspacos = computed(() => disponibilidade.value.some((d) => d.tipo === 'espaco'))
const todayIso = new Date().toISOString().slice(0, 10)

const cells = computed(() => (meta.value?.ano ? gradeMesCalendario(meta.value.ano, mesView.value) : []))
const byDay = computed(() => groupEventosByDay(eventos.value))
const mesTitulo = computed(() => `${MES_NOMES[mesView.value] || ''} ${meta.value?.ano || ''}`)
const diaEventos = computed(() => byDay.value.get(diaSelecionado.value) || [])
const totalMes = computed(() => {
  const pad = String(mesView.value).padStart(2, '0')
  return eventos.value.filter((e) => String(e.data_inicio || '').slice(5, 7) === pad).length
})

function podeEditar(ev) {
  return canEditarEventoPlanejamentoPublico(meta.value?.status, ev)
}

function prevMes() {
  if (mesView.value > 1) mesView.value -= 1
}
function nextMes() {
  if (mesView.value < 12) mesView.value += 1
}

function formatDiaLongo(iso) {
  if (!iso) return ''
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}

function resetFormForDay(iso) {
  editingEvento.value = null
  form.value = { ...emptyForm(), data_inicio: isoToBr(iso) }
  selecaoRecursos.value = emptySelecaoRecursos()
  capacidadeErro.value = ''
}

function openDay(iso) {
  if (!iso) return
  diaSelecionado.value = iso
  resetFormForDay(iso)
  showDayModal.value = true
}

function openEdit(ev) {
  if (!podeEditar(ev)) return
  editingEvento.value = ev
  diaSelecionado.value = String(ev.data_inicio || '').slice(0, 10)
  form.value = {
    titulo: ev.titulo || '',
    data_inicio: isoToBr((ev.data_inicio || '').slice(0, 10)),
    hora_inicio: formatHoraCalendario(ev.hora_inicio) || '',
    hora_fim: formatHoraCalendario(ev.hora_fim) || '',
    local_texto: ev.local_texto || '',
    observacoes: ev.observacoes || '',
  }
  selecaoRecursos.value = selecaoFromEvento(ev)
  capacidadeErro.value = ''
  showDayModal.value = true
}

function cancelEdit() {
  resetFormForDay(diaSelecionado.value)
}

async function carregarDisponibilidade() {
  const params = dispParams.value
  if (!showDayModal.value || !podeMutarForm.value || !params) {
    disponibilidade.value = []
    return
  }
  const seq = ++dispSeq
  dispLoading.value = true
  try {
    const data = await getDisponibilidadePublica(token.value, params, headers.value)
    if (seq === dispSeq) disponibilidade.value = data
  } catch {
    if (seq === dispSeq) disponibilidade.value = []
  } finally {
    if (seq === dispSeq) dispLoading.value = false
  }
}

watch(
  () => [showDayModal.value, JSON.stringify(dispParams.value), editingEvento.value?.id],
  () => {
    clearTimeout(dispTimer)
    dispTimer = setTimeout(carregarDisponibilidade, 250)
  },
)

function closeDayModal() {
  showDayModal.value = false
  editingEvento.value = null
}

async function load() {
  loading.value = true
  error.value = ''
  if (tenant.value && tenantStore.slug !== tenant.value) {
    tenantStore.select({ slug: tenant.value, name: tenantStore.name || '' })
  }
  try {
    const [m, e] = await Promise.all([
      getPublicoMeta(token.value, headers.value),
      listEventosPublicos(token.value, headers.value),
    ])
    meta.value = m
    eventos.value = e
    if (meta.value?.ano && meta.value.ano !== new Date().getFullYear()) {
      mesView.value = 1
    }
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      err.response?.data?.errors?.token?.[0] ||
      'Link inválido ou expirado.'
  } finally {
    loading.value = false
  }
}

function buildPayload() {
  const dataInicio = brToIso(form.value.data_inicio)
  return {
    titulo: form.value.titulo.trim(),
    data_inicio: dataInicio,
    hora_inicio: form.value.hora_inicio || null,
    hora_fim: form.value.hora_fim || null,
    local_texto: form.value.local_texto.trim() || null,
    observacoes: form.value.observacoes || null,
    recursos: buildRecursosPayload(selecaoRecursos.value),
  }
}

async function salvar() {
  if (!form.value.local_texto.trim() && !selecaoTemEspaco(selecaoRecursos.value)) {
    innovToast('error', 'Planejamento', 'Escolha um espaço ou informe o local')
    return
  }
  const payload = buildPayload()
  if (!payload.data_inicio) {
    innovToast('error', 'Planejamento', 'Informe a data no formato dd/mm/aaaa')
    return
  }
  capacidadeErro.value = ''
  saving.value = true
  try {
    if (editingEvento.value?.id) {
      await updateEventoPublico(token.value, editingEvento.value.id, payload, headers.value)
      innovToast('success', 'Planejamento', 'Proposta atualizada')
    } else {
      await createEventoPublico(token.value, payload, headers.value)
      innovToast('success', 'Planejamento', 'Proposta enviada')
    }
    const dia = brToIso(form.value.data_inicio) || diaSelecionado.value
    await load()
    diaSelecionado.value = dia
    resetFormForDay(dia)
  } catch (err) {
    if (isErroCapacidade(err)) {
      capacidadeErro.value = formatConflitosRecursos(err.response.data)
      innovToast('error', 'Indisponível', capacidadeErro.value)
      carregarDisponibilidade()
      return
    }
    const msg =
      err.response?.data?.message ||
      err.response?.data?.errors?.status?.[0] ||
      'Não foi possível salvar'
    innovToast('error', 'Planejamento', msg)
  } finally {
    saving.value = false
  }
}

async function remover(ev) {
  if (!podeEditar(ev)) return
  const ok = await innovConfirm({
    title: 'Remover proposta',
    message: `Remover “${ev.titulo}”? Esta ação não pode ser desfeita.`,
    confirmText: 'Remover',
    danger: true,
  })
  if (!ok) return
  try {
    await deleteEventoPublico(token.value, ev.id, headers.value)
    innovToast('success', 'Planejamento', 'Proposta removida')
    if (editingEvento.value?.id === ev.id) {
      resetFormForDay(diaSelecionado.value)
    }
    await load()
    if (showDayModal.value && diaSelecionado.value) {
      const still = (byDay.value.get(diaSelecionado.value) || []).length
      if (!still && !podePropor.value) closeDayModal()
    }
  } catch (err) {
    innovToast(
      'error',
      'Planejamento',
      err.response?.data?.message || err.response?.data?.errors?.status?.[0] || 'Falha ao remover',
    )
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen px-4 py-8" style="background: var(--color-bg, #F7F4EF)">
    <div class="max-w-4xl mx-auto">
      <p class="page-eyebrow">Planejamento Anual</p>
      <h1 class="font-serif text-[28px] font-normal mb-1" style="color: var(--color-ink)">
        {{ meta?.pastoral_nome || 'Pastoral' }}
      </h1>
      <p v-if="meta" class="text-sm mb-6" style="color: var(--color-muted)">
        Ano {{ meta.ano }} · {{ labelStatusPlanejamento(meta.status) }}
      </p>

      <p v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</p>
      <p v-else-if="error" class="text-sm" style="color: #8a2424" data-testid="planejamento-publico-erro">
        {{ error }}
      </p>

      <template v-else>
        <p
          v-if="!podePropor"
          class="card p-4 mb-4 text-sm"
          style="color: var(--color-muted)"
        >
          A coleta não está aberta — você pode consultar as propostas
          <template v-if="meta?.status === 'revisao'">
            e ajustar as que pedirem correção
          </template>.
        </p>

        <section class="card p-4 md:p-5 mb-6" data-testid="planejamento-publico-calendario">
          <div class="flex items-center justify-between gap-2 mb-1">
            <button
              type="button"
              class="btn btn-ghost px-3"
              :disabled="mesView <= 1"
              aria-label="Mês anterior"
              @click="prevMes"
            >
              ‹
            </button>
            <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
              {{ mesTitulo }}
            </h2>
            <button
              type="button"
              class="btn btn-ghost px-3"
              :disabled="mesView >= 12"
              aria-label="Próximo mês"
              @click="nextMes"
            >
              ›
            </button>
          </div>
          <p class="text-[12px] text-center mb-3" style="color: var(--color-muted)">
            {{
              podePropor
                ? 'Clique em um dia para propor ou editar'
                : 'Clique em um dia para ver detalhes'
            }}
            · {{ totalMes }} proposta(s) neste mês
          </p>

          <div class="grid grid-cols-7 gap-1 text-center text-[11px] mb-1" style="color: var(--color-muted)">
            <div v-for="d in WEEKDAYS" :key="d">{{ d }}</div>
          </div>
          <div class="grid grid-cols-7 gap-1">
            <button
              v-for="(cell, idx) in cells"
              :key="idx"
              type="button"
              class="min-h-[64px] md:min-h-[88px] rounded-lg p-1.5 text-left"
              :disabled="!cell.inMonth"
              :style="
                cell.inMonth
                  ? {
                      background: 'var(--color-surface)',
                      border: '1px solid var(--color-line)',
                      color: 'var(--color-ink)',
                      cursor: 'pointer',
                    }
                  : { background: 'transparent', border: 'none', cursor: 'default' }
              "
              :data-testid="cell.iso ? `planejamento-publico-dia-${cell.iso}` : undefined"
              @click="cell.inMonth && openDay(cell.iso)"
            >
              <template v-if="cell.inMonth">
                <div
                  class="text-[12px] font-medium mb-0.5 w-5 h-5 grid place-items-center rounded-full"
                  :style="
                    cell.iso === todayIso
                      ? { background: 'var(--color-primary)', color: 'var(--color-on-primary)' }
                      : {}
                  "
                >
                  {{ cell.dia }}
                </div>
                <div
                  v-for="ev in (byDay.get(cell.iso) || []).slice(0, 2)"
                  :key="ev.id"
                  class="text-[10px] truncate leading-tight px-1 py-0.5 rounded mt-0.5 font-medium"
                  :style="{
                    background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                    color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                  }"
                >
                  {{ labelEventoPlanejamentoPreview(ev) }}
                </div>
                <div
                  v-if="(byDay.get(cell.iso) || []).length > 2"
                  class="text-[10px] mt-0.5"
                  style="color: var(--color-muted)"
                >
                  +{{ (byDay.get(cell.iso) || []).length - 2 }}
                </div>
              </template>
            </button>
          </div>
        </section>

        <section>
          <h2
            class="text-[11px] font-semibold uppercase tracking-wider mb-3"
            style="color: var(--color-accent-dark)"
          >
            Todas as suas propostas
          </h2>
          <ul class="space-y-2 list-none p-0 m-0">
            <li
              v-for="ev in eventos"
              :key="ev.id"
              class="card p-4 flex flex-wrap items-center justify-between gap-2"
              data-testid="planejamento-publico-lista-item"
            >
              <button
                type="button"
                class="min-w-0 text-left flex-1 bg-transparent border-0 p-0 cursor-pointer"
                @click="podeEditar(ev) ? openEdit(ev) : openDay(String(ev.data_inicio || '').slice(0, 10))"
              >
                <div class="font-medium text-sm" style="color: var(--color-ink)">{{ ev.titulo }}</div>
                <div class="text-xs mt-1" style="color: var(--color-muted)">
                  {{ String(ev.data_inicio || '').slice(0, 10) }}
                  <template v-if="ev.hora_inicio"> · {{ String(ev.hora_inicio).slice(0, 5) }}</template>
                  <template v-if="labelRecursosEvento(ev)"> · {{ labelRecursosEvento(ev) }}</template>
                  <template v-if="ev.local_texto"> · {{ ev.local_texto }}</template>
                </div>
              </button>
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                  :style="{
                    background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                    color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                  }"
                >
                  {{ labelStatusSolicitacao(ev.status_solicitacao) }}
                </span>
                <button
                  v-if="podeEditar(ev)"
                  type="button"
                  class="btn btn-ghost text-xs"
                  data-testid="planejamento-publico-editar"
                  @click="openEdit(ev)"
                >
                  Editar
                </button>
                <button
                  v-if="podeEditar(ev)"
                  type="button"
                  class="btn btn-ghost text-xs"
                  style="color: var(--color-danger)"
                  data-testid="planejamento-publico-remover"
                  @click="remover(ev)"
                >
                  Remover
                </button>
              </div>
            </li>
          </ul>
          <p v-if="!eventos.length" class="text-sm" style="color: var(--color-muted)">
            Nenhuma proposta ainda.
          </p>
        </section>
      </template>
    </div>

    <div
      v-if="showDayModal && diaSelecionado"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      data-testid="planejamento-publico-modal"
      @click.self="closeDayModal"
    >
      <div
        class="w-full max-w-lg rounded-t-2xl md:rounded-2xl p-5 max-h-[90vh] overflow-y-auto"
        style="background: var(--color-surface)"
      >
        <div class="flex items-start justify-between gap-3 mb-4">
          <div>
            <p class="page-eyebrow">Dia selecionado</p>
            <h3 class="font-serif text-lg capitalize" style="color: var(--color-ink)">
              {{ formatDiaLongo(diaSelecionado) }}
            </h3>
          </div>
          <button type="button" class="btn btn-ghost text-xs" @click="closeDayModal">Fechar</button>
        </div>

        <ul v-if="diaEventos.length && !editingEvento" class="list-none p-0 m-0 space-y-2 mb-4">
          <li
            v-for="ev in diaEventos"
            :key="ev.id"
            class="rounded-lg px-3 py-2.5"
            style="border: 1px solid var(--color-line)"
          >
            <div class="font-medium text-[14px]" style="color: var(--color-ink)">{{ ev.titulo }}</div>
            <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">
              <template v-if="ev.hora_inicio">{{ String(ev.hora_inicio).slice(0, 5) }} · </template>
              {{ [labelRecursosEvento(ev), ev.local_texto].filter(Boolean).join(' · ') || 'Local a definir' }}
            </div>
            <span
              class="inline-block mt-1 text-[11px] font-medium px-2 py-0.5 rounded-full"
              :style="{
                background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                color: styleStatusSolicitacao(ev.status_solicitacao).fg,
              }"
            >
              {{ labelStatusSolicitacao(ev.status_solicitacao) }}
            </span>
            <p v-if="ev.motivo_ajuste" class="text-[12px] mt-1" style="color: var(--color-muted)">
              Ajuste pedido: {{ ev.motivo_ajuste }}
            </p>
            <div v-if="podeEditar(ev)" class="flex gap-2 mt-2">
              <button
                type="button"
                class="btn btn-ghost text-xs"
                data-testid="planejamento-publico-editar-dia"
                @click="openEdit(ev)"
              >
                Editar
              </button>
              <button
                type="button"
                class="btn btn-ghost text-xs"
                style="color: var(--color-danger)"
                data-testid="planejamento-publico-remover-dia"
                @click="remover(ev)"
              >
                Remover
              </button>
            </div>
          </li>
        </ul>

        <form
          v-if="podeMutarForm"
          class="space-y-3"
          data-testid="planejamento-publico-form"
          @submit.prevent="salvar"
        >
          <div class="flex items-center justify-between gap-2">
            <h4 class="font-serif text-[16px]" style="color: var(--color-ink)">{{ formTitulo }}</h4>
            <button
              v-if="editingEvento"
              type="button"
              class="btn btn-ghost text-xs"
              @click="cancelEdit"
            >
              Nova proposta
            </button>
          </div>
          <div>
            <label class="fld" for="pub-titulo">Título</label>
            <input id="pub-titulo" v-model="form.titulo" class="input" required />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <DateInput
              v-model="form.data_inicio"
              name="pub-data"
              label="Data"
              required
            />
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="fld" for="pub-hora">Início</label>
                <input
                  id="pub-hora"
                  v-model="form.hora_inicio"
                  type="time"
                  class="input"
                  data-testid="planejamento-publico-hora-inicio"
                />
              </div>
              <div>
                <label class="fld" for="pub-hora-fim">Fim</label>
                <input
                  id="pub-hora-fim"
                  v-model="form.hora_fim"
                  type="time"
                  class="input"
                  data-testid="planejamento-publico-hora-fim"
                />
              </div>
            </div>
          </div>
          <PlanejamentoRecursosPicker
            v-if="temEspacos || disponibilidade.length || dispLoading"
            v-model="selecaoRecursos"
            :disponibilidade="disponibilidade"
            :loading="dispLoading"
            :sem-data="!dispParams"
          />
          <p
            v-if="capacidadeErro"
            class="rounded-lg px-3 py-2 text-[13px]"
            style="background: var(--color-accent-soft); color: var(--color-danger)"
            data-testid="planejamento-publico-capacidade-erro"
          >
            {{ capacidadeErro }}
          </p>
          <div>
            <label class="fld" for="pub-local">
              {{ temEspacos ? 'Outro local (se não for um dos espaços)' : 'Local' }}
            </label>
            <input
              id="pub-local"
              v-model="form.local_texto"
              class="input"
              :required="!temEspacos"
              placeholder="Ex.: Casa de retiro"
            />
          </div>
          <div>
            <label class="fld" for="pub-obs">Observações</label>
            <textarea id="pub-obs" v-model="form.observacoes" class="input" rows="2" />
          </div>
          <button type="submit" class="btn btn-primary w-full" :disabled="saving">
            {{ submitLabel }}
          </button>
        </form>
        <p v-else-if="!diaEventos.length" class="text-[13px]" style="color: var(--color-muted)">
          Nenhuma proposta neste dia.
        </p>
      </div>
    </div>
  </div>
</template>
