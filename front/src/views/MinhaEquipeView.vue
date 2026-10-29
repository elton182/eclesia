<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { innovToast } from '@/plugins/toast'
import { casalEle, casalEla } from '@/utils/casalDisplay'
import { equipesLideradasIds, userHasPermission } from '@/utils/userRoles'
import { formatMoney } from '@/utils/eccFinanceiro'
import {
  buildDepositoFormData,
  competenciaAtual,
  formatCompetencia,
  labelStatusDeposito,
} from '@/utils/eccFinanceiroDepositos'
import LiderEquipeTour from '@/components/ecc/LiderEquipeTour.vue'
import ComprovanteAnexoField from '@/components/ecc/ComprovanteAnexoField.vue'
import AppVersionBadge from '@/components/base/AppVersionBadge.vue'
import {
  beginLiderTour,
  liderEquipeTourSteps,
  markLiderTourSeen,
} from '@/utils/liderEquipeTour'
import { labelResumoPerguntas, temAtividadePerguntas } from '@/utils/eccPerguntas'

const router = useRouter()
const route = useRoute()
const tenantStore = useTenantStore()
const authStore = useAuthStore()

const loading = ref(true)
const equipes = ref([])
const casais = ref([])
const equipeId = ref('')
const tourOpen = ref(false)
const tourRun = ref(0)
const depositos = ref([])
const loadingDepositos = ref(false)
const savingDeposito = ref(false)
const formDeposito = ref({
  competencia: competenciaAtual(),
  valor: '',
  comprovante: null,
})
const perguntasResumo = ref({ pendentes: 0, com_resposta_nova: 0 })
const canVerPerguntas = computed(() => userHasPermission(authStore.user, 'ecc.perguntas.view'))
const perguntasLabel = computed(() => labelResumoPerguntas(perguntasResumo.value))
const perguntasAtivas = computed(() => temAtividadePerguntas(perguntasResumo.value))

const tourHelpRequest = inject('liderTourHelpRequest', ref(0))

const multipleEquipes = computed(() => equipes.value.length > 1)
const tourSteps = computed(() => liderEquipeTourSteps({ multipleEquipes: multipleEquipes.value }))

const equipeAtual = computed(() =>
  equipes.value.find((equipe) => String(equipe.id) === String(equipeId.value)) || null,
)

const casaisDaEquipe = computed(() =>
  casais.value.filter((casal) => String(casal.equipe_id) === String(equipeId.value)),
)

const depositosDaEquipe = computed(() =>
  depositos.value.filter((d) => String(d.equipe_id) === String(equipeId.value)),
)

function contato(casal) {
  const telefones = [casal.ele?.telefone || casal.telefone, casal.ela?.telefone || casal.telefone_conjuge]
    .filter(Boolean)
  const emails = [casal.ele?.email || casal.email, casal.ela?.email || casal.email_conjuge]
    .filter(Boolean)
  return {
    telefones: telefones.join(' · '),
    emails: emails.join(' · '),
  }
}

function escolherEquipeInicial(lista) {
  const vinculadas = new Set(equipesLideradasIds(authStore.user).map(String))
  const preferida = lista.find((equipe) => vinculadas.has(String(equipe.id)))
  return String((preferida || lista[0])?.id || '')
}

async function loadDepositos() {
  if (!equipeId.value) {
    depositos.value = []
    return
  }
  loadingDepositos.value = true
  try {
    const { data } = await api.get('/ecc/financeiro/depositos', {
      params: { equipe_id: equipeId.value },
    })
    depositos.value = data?.data || []
  } catch (e) {
    depositos.value = []
    innovToast('error', 'Depósitos', e.response?.data?.message || 'Falha ao carregar depósitos')
  } finally {
    loadingDepositos.value = false
  }
}

async function loadPerguntasResumo() {
  if (!canVerPerguntas.value) {
    perguntasResumo.value = { pendentes: 0, com_resposta_nova: 0 }
    return
  }
  try {
    const { data } = await api.get('/ecc/perguntas/resumo')
    perguntasResumo.value = data?.data || { pendentes: 0, com_resposta_nova: 0 }
  } catch {
    perguntasResumo.value = { pendentes: 0, com_resposta_nova: 0 }
  }
}

async function load() {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/entrar')
    return
  }
  loading.value = true
  try {
    const [equipesRes, casaisRes] = await Promise.all([
      api.get('/ecc/equipes'),
      api.get('/ecc/casais'),
    ])
    equipes.value = equipesRes.data?.data || equipesRes.data || []
    casais.value = casaisRes.data?.data || casaisRes.data || []
    if (!equipes.value.some((equipe) => String(equipe.id) === String(equipeId.value))) {
      equipeId.value = escolherEquipeInicial(equipes.value)
    }
    await Promise.all([loadDepositos(), loadPerguntasResumo()])
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar a equipe')
  } finally {
    loading.value = false
  }
}

async function enviarDeposito() {
  if (!equipeId.value) return
  if (!formDeposito.value.competencia || !formDeposito.value.valor || !formDeposito.value.comprovante) {
    innovToast('error', 'Depósito', 'Informe competência, valor e comprovante.')
    return
  }
  savingDeposito.value = true
  try {
    const fd = buildDepositoFormData({
      equipe_id: equipeId.value,
      competencia: formDeposito.value.competencia,
      valor: formDeposito.value.valor,
      comprovante: formDeposito.value.comprovante,
    })
    await api.post('/ecc/financeiro/depositos', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    innovToast('success', 'Depósito', 'Enviado para aprovação no financeiro.')
    formDeposito.value = {
      competencia: competenciaAtual(),
      valor: '',
      comprovante: null,
    }
    await loadDepositos()
  } catch (e) {
    const msg = e.response?.data?.errors?.competencia?.[0]
      || e.response?.data?.message
      || 'Não foi possível enviar o depósito.'
    innovToast('error', 'Depósito', msg)
  } finally {
    savingDeposito.value = false
  }
}

watch(equipeId, () => {
  if (!loading.value) loadDepositos()
})

watch(
  () => tourHelpRequest.value,
  (n, prev) => {
    if (n && n !== prev) abrirTour(true)
  },
)

watch(
  () => route.query.tour,
  (v) => {
    if (v === '1') {
      abrirTour(true)
      router.replace({ query: { ...route.query, tour: undefined } })
    }
  },
)

async function baixarComprovante(dep) {
  try {
    const { data } = await api.get(`/ecc/financeiro/depositos/${dep.id}/comprovante`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch (e) {
    innovToast('error', 'Comprovante', e.response?.data?.message || 'Não foi possível abrir o comprovante.')
  }
}

function atualizar(casal) {
  router.push({ name: 'ecc-casais', query: { edit: casal.id } })
}

function abrirTour(force) {
  const session = beginLiderTour({
    storage: localStorage,
    multipleEquipes: multipleEquipes.value,
    force,
  })
  if (!session.open) return
  tourRun.value += 1
  tourOpen.value = true
}

function fecharTour() {
  markLiderTourSeen(localStorage)
  tourOpen.value = false
}

onMounted(async () => {
  await load()
  if (route.query.tour === '1') {
    abrirTour(true)
    router.replace({ query: { ...route.query, tour: undefined } })
  } else {
    abrirTour(false)
  }
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 py-5 md:py-8 w-full max-w-2xl mx-auto" data-testid="minha-equipe-page">
    <header class="mb-6">
      <p class="page-eyebrow">ECC · Equipe</p>
      <h1
        class="font-serif text-[28px] leading-tight mt-1 font-normal"
        style="color: var(--color-ink)"
        data-tour="titulo"
      >
        {{ equipeAtual?.nome || 'Minha equipe' }}
      </h1>
      <p class="mt-1 m-0" data-testid="minha-equipe-app-version">
        <AppVersionBadge />
      </p>
      <p class="mt-2 text-[14px] leading-relaxed" style="color: var(--color-muted)">
        Revise e atualize os dados dos casais da sua equipe.
      </p>
    </header>

    <div v-if="multipleEquipes" class="mb-5 max-w-sm">
      <label class="fld" for="minha-equipe-seletor" data-tour="seletor">Equipe</label>
      <select
        id="minha-equipe-seletor"
        v-model="equipeId"
        class="input"
        data-testid="minha-equipe-seletor"
      >
        <option v-for="equipe in equipes" :key="equipe.id" :value="equipe.id">
          {{ equipe.nome }}
        </option>
      </select>
    </div>

    <div
      v-if="loading"
      class="text-[13.5px]"
      style="color: var(--color-muted)"
    >
      Carregando…
    </div>

    <template v-else>
      <button
        v-if="canVerPerguntas"
        type="button"
        class="card p-4 mb-5 w-full text-left cursor-pointer border-0"
        data-testid="minha-equipe-perguntas"
        @click="router.push({ name: 'ecc-perguntas' })"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-[15px] font-medium" style="color: var(--color-ink)">
              Caixa de perguntas
            </div>
            <p class="text-[13px] mt-0.5" style="color: var(--color-muted)">
              {{ perguntasLabel || 'Ver formulários disponíveis' }}
            </p>
          </div>
          <span
            v-if="perguntasAtivas"
            class="min-w-[22px] h-[22px] px-1.5 rounded-full text-[11px] font-semibold flex items-center justify-center"
            style="background: var(--color-accent-dark); color: #fff"
            data-testid="minha-equipe-perguntas-badge"
          >
            {{ (perguntasResumo.pendentes || perguntasResumo.com_resposta_nova) > 9
              ? '9+'
              : (perguntasResumo.pendentes || perguntasResumo.com_resposta_nova) }}
          </span>
          <span v-else class="text-[18px]" style="color: var(--color-muted)" aria-hidden="true">›</span>
        </div>
      </button>

      <div class="flex items-baseline justify-between gap-3 mb-3">
        <h2 class="text-[15px] font-medium" style="color: var(--color-ink)">
          Casais da equipe
        </h2>
        <span
          class="text-[12px] px-2.5 py-0.5 rounded-full"
          style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
          data-testid="minha-equipe-contador"
        >
          {{ casaisDaEquipe.length }}
          {{ casaisDaEquipe.length === 1 ? 'casal' : 'casais' }}
        </span>
      </div>

      <ul
        class="flex flex-col gap-3"
        data-tour="lista"
        data-testid="minha-equipe-lista"
      >
        <li
          v-if="!casaisDaEquipe.length"
          class="rounded-[12px] border border-dashed p-10 text-center text-[13.5px]"
          style="border-color: var(--color-line); color: var(--color-muted)"
          data-tour="atualizar"
        >
          Nenhum casal nesta equipe.
        </li>
        <li
          v-for="(casal, index) in casaisDaEquipe"
          :key="casal.id"
          class="card p-4"
          data-testid="minha-equipe-casal"
        >
          <div class="font-medium text-[16px]" style="color: var(--color-ink)">
            {{ casalEle(casal) }} & {{ casalEla(casal) }}
          </div>
          <div class="text-[13px] mt-1.5" style="color: var(--color-muted)">
            <span v-if="contato(casal).telefones">{{ contato(casal).telefones }}</span>
            <span v-if="contato(casal).telefones && contato(casal).emails"> · </span>
            <span v-if="contato(casal).emails">{{ contato(casal).emails }}</span>
            <span v-if="!contato(casal).telefones && !contato(casal).emails">Sem telefone ou e-mail</span>
          </div>
          <button
            type="button"
            class="btn btn-primary w-full mt-4"
            data-testid="minha-equipe-atualizar"
            :data-tour="index === 0 ? 'atualizar' : undefined"
            @click="atualizar(casal)"
          >
            Atualizar cadastro
          </button>
        </li>
      </ul>

      <p
        class="text-[12.5px] mt-5 leading-relaxed"
        style="color: var(--color-muted)"
        data-tour="limite"
        data-testid="minha-equipe-limite"
      >
        Incluir ou excluir casais da equipe fica com a secretaria.
      </p>
    </template>

    <section
      class="mt-10"
      data-testid="minha-equipe-depositos"
      data-tour="depositos"
    >
      <h3 class="font-serif text-[22px] leading-tight font-normal" style="color: var(--color-ink)">
        Depósito mensal
      </h3>
      <p class="mt-1 text-[13.5px] leading-relaxed mb-4" style="color: var(--color-muted)">
        Informe o valor depositado pela equipe e anexe o comprovante (arquivo ou foto na hora).
        O financeiro confirma após aprovação.
      </p>

      <form
        class="card p-5 mb-5"
        data-testid="minha-equipe-deposito-form"
        @submit.prevent="enviarDeposito"
      >
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="fld" for="deposito-competencia">Competência</label>
            <input
              id="deposito-competencia"
              v-model="formDeposito.competencia"
              type="month"
              class="input"
              required
              data-testid="minha-equipe-deposito-competencia"
            />
          </div>
          <div>
            <label class="fld" for="deposito-valor">Valor (R$)</label>
            <input
              id="deposito-valor"
              v-model="formDeposito.valor"
              type="number"
              min="0.01"
              step="0.01"
              class="input"
              required
              data-testid="minha-equipe-deposito-valor"
            />
          </div>
          <div class="sm:col-span-2">
            <ComprovanteAnexoField
              id="minha-equipe-deposito-comprovante"
              v-model="formDeposito.comprovante"
              :disabled="savingDeposito"
              required
            />
          </div>
        </div>
        <div class="mt-4">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="savingDeposito"
            data-testid="minha-equipe-deposito-enviar"
          >
            {{ savingDeposito ? 'Enviando…' : 'Enviar depósito' }}
          </button>
        </div>
      </form>

      <div
        v-if="loadingDepositos"
        class="text-[13.5px]"
        style="color: var(--color-muted)"
      >
        Carregando depósitos…
      </div>
      <div
        v-else-if="!depositosDaEquipe.length"
        class="text-[13.5px]"
        style="color: var(--color-muted)"
        data-testid="minha-equipe-depositos-vazio"
      >
        Nenhum depósito registrado ainda.
      </div>
      <ul
        v-else
        class="card overflow-hidden divide-y"
        style="border-color: var(--color-line)"
        data-testid="minha-equipe-depositos-lista"
      >
        <li
          v-for="dep in depositosDaEquipe"
          :key="dep.id"
          class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
          style="border-color: var(--color-line)"
          data-testid="minha-equipe-deposito-item"
        >
          <div>
            <span class="font-medium text-[15px]" style="color: var(--color-ink)">
              {{ formatCompetencia(dep.competencia) }}
            </span>
            <span class="text-[13px] ml-2" style="color: var(--color-muted)">
              {{ formatMoney(dep.valor) }} · {{ labelStatusDeposito(dep.status) }}
            </span>
            <p
              v-if="dep.status === 'rejeitado' && dep.motivo_rejeicao"
              class="text-[12.5px] mt-1"
              style="color: var(--color-danger)"
            >
              {{ dep.motivo_rejeicao }}
            </p>
          </div>
          <button
            type="button"
            class="btn btn-ghost text-sm w-full sm:w-auto"
            data-testid="minha-equipe-deposito-comprovante-link"
            @click="baixarComprovante(dep)"
          >
            Ver comprovante
          </button>
        </li>
      </ul>
    </section>

    <LiderEquipeTour
      :open="tourOpen"
      :steps="tourSteps"
      :run-id="tourRun"
      @close="fecharTour"
    />
  </div>
</template>
