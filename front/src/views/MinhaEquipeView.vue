<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { innovToast } from '@/plugins/toast'
import { casalEle, casalEla } from '@/utils/casalDisplay'
import { equipesLideradasIds } from '@/utils/userRoles'
import { formatMoney } from '@/utils/eccFinanceiro'
import {
  buildDepositoFormData,
  competenciaAtual,
  formatCompetencia,
  labelStatusDeposito,
} from '@/utils/eccFinanceiroDepositos'
import LiderEquipeTour from '@/components/ecc/LiderEquipeTour.vue'
import ComprovanteAnexoField from '@/components/ecc/ComprovanteAnexoField.vue'
import {
  beginLiderTour,
  liderEquipeTourSteps,
  markLiderTourSeen,
} from '@/utils/liderEquipeTour'

const router = useRouter()
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
    await loadDepositos()
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
  abrirTour(false)
})
</script>

<template>
  <div class="p-6 md:p-[30px] w-full" data-testid="minha-equipe-page">
    <div class="mb-6 md:mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-eyebrow">ECC</p>
        <h2
          class="font-serif text-[27px] leading-tight mt-1"
          style="color: var(--color-ink)"
          data-tour="titulo"
        >
          {{ equipeAtual?.nome || 'Minha equipe' }}
        </h2>
        <p class="mt-2 text-[13.5px] leading-relaxed" style="color: var(--color-muted)">
          Atualize o cadastro dos casais que já estão na sua equipe.
        </p>
      </div>
      <button
        type="button"
        class="btn btn-ghost w-full sm:w-auto"
        data-testid="minha-equipe-ver-tour"
        data-tour="rever"
        @click="abrirTour(true)"
      >
        Ver tour
      </button>
    </div>

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

    <p class="text-[13px] mb-4" style="color: var(--color-muted)" data-tour="limite">
      Incluir, excluir ou trocar de equipe fica com a secretaria.
    </p>

    <div
      v-if="loading"
      class="text-[13.5px]"
      style="color: var(--color-muted)"
    >
      Carregando…
    </div>

    <div
      v-else
      class="w-full overflow-hidden rounded-[11px]"
      style="background: var(--color-surface); border: 1px solid var(--color-line)"
      data-tour="lista"
      data-testid="minha-equipe-lista"
    >
      <div
        v-if="!casaisDaEquipe.length"
        class="p-10 text-center text-[13.5px]"
        style="color: var(--color-muted)"
        data-tour="atualizar"
      >
        Nenhum casal nesta equipe.
      </div>
      <div
        v-for="(casal, index) in casaisDaEquipe"
        :key="casal.id"
        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        style="border-top: 1px solid var(--color-line)"
        data-testid="minha-equipe-casal"
      >
        <div class="min-w-0">
          <div class="font-medium text-[15px]" style="color: var(--color-ink)">
            {{ casalEle(casal) }} & {{ casalEla(casal) }}
          </div>
          <div class="text-[13px] mt-1" style="color: var(--color-muted)">
            <span v-if="contato(casal).telefones">{{ contato(casal).telefones }}</span>
            <span v-if="contato(casal).telefones && contato(casal).emails"> · </span>
            <span v-if="contato(casal).emails">{{ contato(casal).emails }}</span>
            <span v-if="!contato(casal).telefones && !contato(casal).emails">Sem telefone ou e-mail</span>
          </div>
        </div>
        <button
          type="button"
          class="btn btn-primary w-full sm:w-auto"
          data-testid="minha-equipe-atualizar"
          :data-tour="index === 0 ? 'atualizar' : undefined"
          @click="atualizar(casal)"
        >
          Atualizar cadastro
        </button>
      </div>
    </div>

    <section
      class="mt-10"
      data-testid="minha-equipe-depositos"
      data-tour="depositos"
    >
      <h3 class="font-serif text-[22px] leading-tight" style="color: var(--color-ink)">
        Depósito mensal
      </h3>
      <p class="mt-1 text-[13.5px] leading-relaxed mb-4" style="color: var(--color-muted)">
        Informe o valor depositado pela equipe e anexe o comprovante (arquivo ou foto na hora).
        O financeiro confirma após aprovação.
      </p>

      <form
        class="rounded-[11px] p-5 mb-5"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
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
        class="rounded-[11px] overflow-hidden"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
        data-testid="minha-equipe-depositos-lista"
      >
        <li
          v-for="dep in depositosDaEquipe"
          :key="dep.id"
          class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
          style="border-top: 1px solid var(--color-line)"
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
