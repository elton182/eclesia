<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import { formatMoney, nomeMes } from '@/utils/eccFinanceiro'

const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()
const authOpts = () => ({ isSuperAdmin: authAdminStore.isAuthenticated })

const canManage = computed(() =>
  userHasPermission(authStore.user, 'ecc.financeiro.manage', authOpts()),
)

const ano = ref(new Date().getFullYear())
const livro = ref(null)
const loading = ref(false)
const saving = ref(false)

const showLancamento = ref(false)
const showTransferencia = ref(false)
const showConta = ref(false)

const formLanc = ref({
  conta_id: '',
  data: '',
  historico: '',
  tipo: 'entrada',
  valor: '',
})

const formTransf = ref({
  conta_origem_id: '',
  conta_destino_id: '',
  data: '',
  historico: '',
  valor: '',
})

const formConta = ref({
  nome: '',
  tipo: 'especie',
})

const contas = computed(() => livro.value?.contas || [])
const meses = computed(() => livro.value?.meses || [])
const saldoFinal = computed(() => livro.value?.saldo_final ?? 0)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ecc/financeiro', { params: { ano: ano.value } })
    livro.value = data.data
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Não foi possível carregar o financeiro.')
    livro.value = null
  } finally {
    loading.value = false
  }
}

function openLancamento(tipo = 'entrada') {
  const hoje = new Date().toISOString().slice(0, 10)
  formLanc.value = {
    conta_id: contas.value.find((c) => c.ativa !== false)?.id || '',
    data: hoje,
    historico: '',
    tipo,
    valor: '',
  }
  showLancamento.value = true
}

function openTransferencia() {
  const ativas = contas.value.filter((c) => c.ativa !== false)
  formTransf.value = {
    conta_origem_id: ativas[0]?.id || '',
    conta_destino_id: ativas[1]?.id || ativas[0]?.id || '',
    data: new Date().toISOString().slice(0, 10),
    historico: 'Transferência entre contas',
    valor: '',
  }
  showTransferencia.value = true
}

function openConta() {
  formConta.value = { nome: '', tipo: 'especie' }
  showConta.value = true
}

async function salvarLancamento() {
  if (!formLanc.value.historico || !formLanc.value.valor || !formLanc.value.conta_id) {
    innovToast('error', 'Validação', 'Preencha conta, histórico e valor.')
    return
  }
  saving.value = true
  try {
    await api.post('/ecc/financeiro/lancamentos', {
      ...formLanc.value,
      valor: Number(formLanc.value.valor),
    })
    showLancamento.value = false
    innovToast('success', 'OK', 'Lançamento registrado.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Falha ao lançar.')
  } finally {
    saving.value = false
  }
}

async function salvarTransferencia() {
  if (!formTransf.value.valor || !formTransf.value.historico) {
    innovToast('error', 'Validação', 'Preencha histórico e valor.')
    return
  }
  saving.value = true
  try {
    await api.post('/ecc/financeiro/transferencias', {
      ...formTransf.value,
      valor: Number(formTransf.value.valor),
    })
    showTransferencia.value = false
    innovToast('success', 'OK', 'Transferência registrada.')
    await load()
  } catch (e) {
    const msg =
      e?.response?.data?.errors?.conta_destino_id?.[0] ||
      e?.response?.data?.message ||
      'Falha na transferência.'
    innovToast('error', 'Erro', msg)
  } finally {
    saving.value = false
  }
}

async function salvarConta() {
  if (!formConta.value.nome) {
    innovToast('error', 'Validação', 'Informe o nome da conta.')
    return
  }
  saving.value = true
  try {
    await api.post('/ecc/financeiro/contas', formConta.value)
    showConta.value = false
    innovToast('success', 'OK', 'Conta criada.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Falha ao criar conta.')
  } finally {
    saving.value = false
  }
}

async function transportar() {
  const ok = await innovConfirm({
    title: 'Transportar saldo',
    message: `Criar abertura de ${ano.value} com o saldo final de ${ano.value - 1}?`,
    confirmText: 'Transportar',
  })
  if (!ok) return
  saving.value = true
  try {
    await api.post('/ecc/financeiro/transportar', { ano: ano.value })
    innovToast('success', 'OK', 'Saldo transportado.')
    await load()
  } catch (e) {
    const msg =
      e?.response?.data?.errors?.ano?.[0] ||
      e?.response?.data?.message ||
      'Não foi possível transportar.'
    innovToast('error', 'Erro', msg)
  } finally {
    saving.value = false
  }
}

async function excluirLancamento(id) {
  const ok = await innovConfirm({
    title: 'Excluir lançamento',
    message: 'Se for transferência, o par também será removido.',
    confirmText: 'Excluir',
  })
  if (!ok) return
  try {
    await api.delete(`/ecc/financeiro/lancamentos/${id}`)
    innovToast('success', 'OK', 'Lançamento removido.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Falha ao excluir.')
  }
}

function saldoClass(v) {
  if (v < 0) return 'text-red-700'
  if (v > 0) return ''
  return ''
}

watch(ano, () => load())
onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="ecc-financeiro-page">
    <header class="mb-8">
      <p class="page-eyebrow">ECC</p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Financeiro
      </h1>
      <p class="text-[14px] mt-1" style="color: var(--color-muted)">
        Livro-caixa anual da comunidade — contas, lançamentos e fechamento mensal.
      </p>
    </header>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <label class="text-sm font-medium" style="color: var(--color-ink)">
        Ano
        <input
          v-model.number="ano"
          type="number"
          min="2000"
          max="2100"
          class="ml-2 border rounded-lg px-3 py-1.5 text-sm w-24"
          style="border-color: var(--color-line); background: var(--color-surface)"
          data-testid="ecc-financeiro-ano"
        />
      </label>
      <span
        class="text-sm font-semibold ml-auto"
        :class="saldoClass(saldoFinal)"
        style="color: var(--color-ink)"
        data-testid="ecc-financeiro-saldo-final"
      >
        Saldo final: {{ formatMoney(saldoFinal) }}
      </span>
    </div>

    <div v-if="canManage" class="flex flex-wrap gap-2 mb-6">
      <button type="button" class="btn btn-primary" data-testid="ecc-financeiro-nova-entrada" @click="openLancamento('entrada')">
        Entrada
      </button>
      <button type="button" class="btn btn-ghost" data-testid="ecc-financeiro-nova-saida" @click="openLancamento('saida')">
        Saída
      </button>
      <button type="button" class="btn btn-ghost" data-testid="ecc-financeiro-transferir" @click="openTransferencia">
        Transferir
      </button>
      <button type="button" class="btn btn-ghost" data-testid="ecc-financeiro-transportar" @click="transportar">
        Transportar saldo
      </button>
      <button type="button" class="btn btn-ghost" data-testid="ecc-financeiro-nova-conta" @click="openConta">
        Nova conta
      </button>
    </div>

    <div v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</div>

    <template v-else-if="livro">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-8" data-testid="ecc-financeiro-contas">
        <div
          v-for="c in contas"
          :key="c.id"
          class="card p-4"
        >
          <p class="text-xs uppercase tracking-wide" style="color: var(--color-accent-dark)">
            {{ c.tipo === 'banco' ? 'Banco' : 'Espécie' }}
          </p>
          <p class="font-semibold mt-1" style="color: var(--color-ink)">{{ c.nome }}</p>
          <p class="text-sm mt-2" style="color: var(--color-muted)">
            Abertura {{ formatMoney(c.saldo_abertura) }}
          </p>
          <p class="text-lg font-semibold mt-1" :class="saldoClass(c.saldo_final)" style="color: var(--color-ink)">
            {{ formatMoney(c.saldo_final) }}
          </p>
        </div>
      </div>

      <section
        v-for="bloco in meses"
        :key="bloco.mes"
        class="mb-8"
        :data-testid="`ecc-financeiro-mes-${bloco.mes}`"
      >
        <div class="flex flex-wrap items-baseline gap-3 mb-3 border-b pb-2" style="border-color: var(--color-line)">
          <h2 class="font-serif text-xl font-normal" style="color: var(--color-ink)">
            {{ nomeMes(bloco.mes) }}
          </h2>
          <span class="text-sm" style="color: var(--color-muted)">
            Mês {{ formatMoney(bloco.total_mensal) }} · Acumulado {{ formatMoney(bloco.acumulado) }}
          </span>
        </div>

        <div v-if="!bloco.lancamentos.length" class="text-sm py-2" style="color: var(--color-muted)">
          Sem lançamentos.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm" style="color: var(--color-ink)">
            <thead>
              <tr class="text-left" style="color: var(--color-muted)">
                <th class="py-2 pr-3 font-medium">Data</th>
                <th class="py-2 pr-3 font-medium">Histórico</th>
                <th class="py-2 pr-3 font-medium">Conta</th>
                <th class="py-2 pr-3 font-medium text-right">Entrada</th>
                <th class="py-2 pr-3 font-medium text-right">Saída</th>
                <th v-if="canManage" class="py-2 font-medium" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="l in bloco.lancamentos"
                :key="l.id"
                class="border-t"
                style="border-color: var(--color-line)"
              >
                <td class="py-2 pr-3 whitespace-nowrap">{{ l.data }}</td>
                <td class="py-2 pr-3">
                  {{ l.historico }}
                  <span v-if="l.abertura" class="text-xs ml-1" style="color: var(--color-accent-dark)">(abertura)</span>
                  <span v-if="l.transferencia_id" class="text-xs ml-1" style="color: var(--color-muted)">(transf.)</span>
                </td>
                <td class="py-2 pr-3">{{ contas.find((c) => c.id === l.conta_id)?.nome || '—' }}</td>
                <td class="py-2 pr-3 text-right whitespace-nowrap">
                  {{ l.tipo === 'entrada' ? formatMoney(l.valor) : '—' }}
                </td>
                <td class="py-2 pr-3 text-right whitespace-nowrap">
                  {{ l.tipo === 'saida' ? formatMoney(l.valor) : '—' }}
                </td>
                <td v-if="canManage" class="py-2 text-right">
                  <button
                    type="button"
                    class="btn btn-ghost text-xs"
                    :data-testid="`ecc-financeiro-excluir-${l.id}`"
                    @click="excluirLancamento(l.id)"
                  >
                    Excluir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- Modal lançamento -->
    <div
      v-if="showLancamento"
      class="fixed inset-0 z-40 flex items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.45)"
      data-testid="ecc-financeiro-modal-lancamento"
      @click.self="showLancamento = false"
    >
      <div class="card p-5 w-full max-w-md" style="background: var(--color-surface)">
        <h3 class="font-serif text-xl mb-4" style="color: var(--color-ink)">
          {{ formLanc.tipo === 'entrada' ? 'Nova entrada' : 'Nova saída' }}
        </h3>
        <div class="space-y-3">
          <label class="block text-sm">
            Conta
            <select v-model="formLanc.conta_id" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)">
              <option v-for="c in contas.filter((x) => x.ativa !== false)" :key="c.id" :value="c.id">{{ c.nome }}</option>
            </select>
          </label>
          <label class="block text-sm">
            Data
            <input v-model="formLanc.data" type="date" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" />
          </label>
          <label class="block text-sm">
            Histórico
            <input v-model="formLanc.historico" type="text" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" data-testid="ecc-financeiro-historico" />
          </label>
          <label class="block text-sm">
            Valor
            <input v-model="formLanc.valor" type="number" min="0.01" step="0.01" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" data-testid="ecc-financeiro-valor" />
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="btn btn-ghost" @click="showLancamento = false">Cancelar</button>
          <button type="button" class="btn btn-primary" :disabled="saving" data-testid="ecc-financeiro-salvar-lancamento" @click="salvarLancamento">
            Salvar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal transferência -->
    <div
      v-if="showTransferencia"
      class="fixed inset-0 z-40 flex items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.45)"
      data-testid="ecc-financeiro-modal-transferencia"
      @click.self="showTransferencia = false"
    >
      <div class="card p-5 w-full max-w-md" style="background: var(--color-surface)">
        <h3 class="font-serif text-xl mb-4" style="color: var(--color-ink)">Transferência</h3>
        <div class="space-y-3">
          <label class="block text-sm">
            Origem
            <select v-model="formTransf.conta_origem_id" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)">
              <option v-for="c in contas.filter((x) => x.ativa !== false)" :key="c.id" :value="c.id">{{ c.nome }}</option>
            </select>
          </label>
          <label class="block text-sm">
            Destino
            <select v-model="formTransf.conta_destino_id" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)">
              <option v-for="c in contas.filter((x) => x.ativa !== false)" :key="c.id" :value="c.id">{{ c.nome }}</option>
            </select>
          </label>
          <label class="block text-sm">
            Data
            <input v-model="formTransf.data" type="date" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" />
          </label>
          <label class="block text-sm">
            Histórico
            <input v-model="formTransf.historico" type="text" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" />
          </label>
          <label class="block text-sm">
            Valor
            <input v-model="formTransf.valor" type="number" min="0.01" step="0.01" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" />
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="btn btn-ghost" @click="showTransferencia = false">Cancelar</button>
          <button type="button" class="btn btn-primary" :disabled="saving" data-testid="ecc-financeiro-salvar-transferencia" @click="salvarTransferencia">
            Transferir
          </button>
        </div>
      </div>
    </div>

    <!-- Modal conta -->
    <div
      v-if="showConta"
      class="fixed inset-0 z-40 flex items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.45)"
      @click.self="showConta = false"
    >
      <div class="card p-5 w-full max-w-md" style="background: var(--color-surface)">
        <h3 class="font-serif text-xl mb-4" style="color: var(--color-ink)">Nova conta</h3>
        <div class="space-y-3">
          <label class="block text-sm">
            Nome
            <input v-model="formConta.nome" type="text" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)" data-testid="ecc-financeiro-conta-nome" />
          </label>
          <label class="block text-sm">
            Tipo
            <select v-model="formConta.tipo" class="mt-1 w-full border rounded-lg px-3 py-2" style="border-color: var(--color-line)">
              <option value="banco">Banco</option>
              <option value="especie">Espécie</option>
            </select>
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button type="button" class="btn btn-ghost" @click="showConta = false">Cancelar</button>
          <button type="button" class="btn btn-primary" :disabled="saving" data-testid="ecc-financeiro-salvar-conta" @click="salvarConta">
            Criar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
