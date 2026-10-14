<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { MES_NOMES, gradeMesCalendario } from '@/utils/calendario'
import {
  groupEventosByDay,
  labelEventoPlanejamentoPreview,
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  styleStatusSolicitacao,
} from '@/utils/planejamento'
import { innovToast } from '@/plugins/toast'
import {
  buildRecursosPayload,
  disponibilidadeParams,
  emptySelecaoRecursos,
  formatConflitosRecursos,
  isErroCapacidade,
  labelRecursosEvento,
  selecaoTemEspaco,
} from '@/utils/planejamentoRecursos'
import { getDisponibilidadePublica } from '@/services/planejamentoRecursos'
import PlanejamentoRecursosPicker from '@/components/planejamento/PlanejamentoRecursosPicker.vue'
import Hora24Input from '@/components/form/Hora24Input.vue'

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
const dispParams = computed(() => disponibilidadeParams(form.value))
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

function openDay(iso) {
  if (!iso) return
  diaSelecionado.value = iso
  form.value = { ...emptyForm(), data_inicio: iso }
  selecaoRecursos.value = emptySelecaoRecursos()
  capacidadeErro.value = ''
  showDayModal.value = true
}

async function carregarDisponibilidade() {
  const params = dispParams.value
  if (!showDayModal.value || !podePropor.value || !params) {
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
  () => [showDayModal.value, JSON.stringify(dispParams.value)],
  () => {
    clearTimeout(dispTimer)
    dispTimer = setTimeout(carregarDisponibilidade, 250)
  },
)

function closeDayModal() {
  showDayModal.value = false
}

async function load() {
  loading.value = true
  error.value = ''
  if (tenant.value && tenantStore.slug !== tenant.value) {
    tenantStore.select({ slug: tenant.value, name: tenantStore.name || '' })
  }
  try {
    const [m, e] = await Promise.all([
      api.get(`/public/planejamento/${token.value}`, { headers: headers.value }),
      api.get(`/public/planejamento/${token.value}/eventos`, { headers: headers.value }),
    ])
    meta.value = m.data.data || m.data
    eventos.value = e.data.data || e.data || []
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

async function criar() {
  if (!form.value.local_texto.trim() && !selecaoTemEspaco(selecaoRecursos.value)) {
    innovToast('error', 'Planejamento', 'Escolha um espaço ou informe o local')
    return
  }
  capacidadeErro.value = ''
  saving.value = true
  try {
    await api.post(
      `/public/planejamento/${token.value}/eventos`,
      {
        titulo: form.value.titulo,
        data_inicio: form.value.data_inicio,
        hora_inicio: form.value.hora_inicio || null,
        hora_fim: form.value.hora_fim || null,
        local_texto: form.value.local_texto.trim() || null,
        observacoes: form.value.observacoes || null,
        recursos: buildRecursosPayload(selecaoRecursos.value),
      },
      { headers: headers.value },
    )
    innovToast('success', 'Planejamento', 'Proposta enviada')
    form.value = { ...emptyForm(), data_inicio: diaSelecionado.value }
    selecaoRecursos.value = emptySelecaoRecursos()
    await load()
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
      'Não foi possível enviar'
    innovToast('error', 'Planejamento', msg)
  } finally {
    saving.value = false
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
          A coleta não está aberta neste momento — você pode consultar suas propostas, mas não enviar novas.
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
            {{ podePropor ? 'Clique em um dia para propor um evento' : 'Clique em um dia para ver detalhes' }}
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
              class="card p-4 flex flex-wrap items-center justify-between gap-2 cursor-pointer"
              @click="openDay(String(ev.data_inicio || '').slice(0, 10))"
            >
              <div class="min-w-0">
                <div class="font-medium text-sm" style="color: var(--color-ink)">{{ ev.titulo }}</div>
                <div class="text-xs mt-1" style="color: var(--color-muted)">
                  {{ String(ev.data_inicio || '').slice(0, 10) }}
                  <template v-if="ev.hora_inicio"> · {{ String(ev.hora_inicio).slice(0, 5) }}</template>
                  <template v-if="labelRecursosEvento(ev)"> · {{ labelRecursosEvento(ev) }}</template>
                  <template v-if="ev.local_texto"> · {{ ev.local_texto }}</template>
                </div>
              </div>
              <span
                class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                :style="{
                  background: styleStatusSolicitacao(ev.status_solicitacao).bg,
                  color: styleStatusSolicitacao(ev.status_solicitacao).fg,
                }"
              >
                {{ labelStatusSolicitacao(ev.status_solicitacao) }}
              </span>
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

        <ul v-if="diaEventos.length" class="list-none p-0 m-0 space-y-2 mb-4">
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
          </li>
        </ul>

        <form
          v-if="podePropor"
          class="space-y-3"
          data-testid="planejamento-publico-form"
          @submit.prevent="criar"
        >
          <h4 class="font-serif text-[16px]" style="color: var(--color-ink)">Nova proposta</h4>
          <div>
            <label class="fld" for="pub-titulo">Título</label>
            <input id="pub-titulo" v-model="form.titulo" class="input" required />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="fld" for="pub-data">Data</label>
              <input id="pub-data" v-model="form.data_inicio" type="date" class="input" required />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="fld" for="pub-hora">Início</label>
                <Hora24Input id="pub-hora" v-model="form.hora_inicio" />
              </div>
              <div>
                <label class="fld" for="pub-hora-fim">Fim</label>
                <Hora24Input id="pub-hora-fim" v-model="form.hora_fim" />
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
            {{ saving ? 'Enviando…' : 'Enviar proposta' }}
          </button>
        </form>
        <p v-else-if="!diaEventos.length" class="text-[13px]" style="color: var(--color-muted)">
          Nenhuma proposta neste dia.
        </p>
      </div>
    </div>
  </div>
</template>
