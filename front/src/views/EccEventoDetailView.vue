<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import { tipoCor, tipoLabel, statusCompraLabel, filterCasaisBusca, canConfirmDeleteEvento, buildEventoRelatorioHtml, RELATORIO_TIPOS } from '@/utils/eccEventos'

const props = defineProps({
  apiBase: { type: String, default: '/ecc/eventos' },
  listRouteName: { type: String, default: 'ecc-eventos' },
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()

const authOpts = () => ({ isSuperAdmin: authAdminStore.isAuthenticated })
const canManage = computed(() =>
  userHasPermission(authStore.user, 'ecc.eventos.manage', authOpts()),
)

const evento = ref(null)
const tipos = ref([])
const casais = ref([])
const equipes = ref([])
const caixa = ref(null)
const loading = ref(false)
const addingPart = ref(false)
const buscaCasal = ref('')
const convidadosNovo = ref(0)
const novoItem = ref({ nome: '', qtd: '1', unidade: 'un' })
const doarItemId = ref(null)
const doarCasalId = ref('')
const comprarItemId = ref(null)
const comprarValor = ref('')
const showDoacaoDinheiro = ref(false)
const doacaoForm = ref({
  tipo: 'casal',
  casal_id: '',
  ecc_equipe_id: '',
  doador_nome: '',
  valor: '',
  descricao: '',
})
const buscaDoacao = ref('')
const showExcluir = ref(false)
const confirmaExcluir = ref('')
const excluindo = ref(false)
const showRelatorios = ref(false)

const podeExcluir = computed(() => canConfirmDeleteEvento(confirmaExcluir.value))

const showCompras = computed(() => !!evento.value?.permite_compras)

const relatoriosDisponiveis = computed(() =>
  RELATORIO_TIPOS.filter((t) => {
    if (t.id === 'compras' || t.id === 'extrato') return showCompras.value
    return true
  }).map((t) => {
    if (t.id === 'completo' && !showCompras.value) {
      return { ...t, descricao: 'Participantes e convidados' }
    }
    return t
  }),
)

const participantesIds = computed(() =>
  new Set((evento.value?.participantes || []).map((p) => p.casal_id)),
)

const casaisFora = computed(() => {
  const fora = casais.value.filter((c) => !participantesIds.value.has(c.id))
  return filterCasaisBusca(fora, buscaCasal.value)
})

const casaisDoacao = computed(() => filterCasaisBusca(casais.value, buscaDoacao.value))

function money(v) {
  return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function casalRotulo(c) {
  if (!c) return ''
  if (c.ele?.nome || c.ela?.nome) {
    return [c.ele?.nome, c.ela?.nome].filter(Boolean).join(' e ')
  }
  return [c.nome, c.nome_conjuge].filter(Boolean).join(' e ')
}

function formatWhen(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'long', timeStyle: 'short' })
}

async function loadCaixa() {
  if (!evento.value?.id || !showCompras.value) {
    caixa.value = null
    return
  }
  try {
    const { data } = await api.get(`${props.apiBase}/${evento.value.id}/caixa`)
    caixa.value = data?.data || data
  } catch {
    caixa.value = null
  }
}

async function load() {
  loading.value = true
  try {
    const [{ data: ev }, { data: cs }, { data: ts }, { data: eq }] = await Promise.all([
      api.get(`${props.apiBase}/${route.params.id}`),
      api.get('/ecc/casais'),
      api.get('/eventos/tipos'),
      api.get('/ecc/equipes'),
    ])
    evento.value = ev?.data || ev
    casais.value = cs?.data || cs || []
    tipos.value = ts?.data || ts || []
    equipes.value = eq?.data || eq || []
    await loadCaixa()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao carregar evento.')
    router.push({ name: props.listRouteName })
  } finally {
    loading.value = false
  }
}

async function addParticipante(casalId) {
  try {
    const { data } = await api.post(`${props.apiBase}/${evento.value.id}/participantes`, {
      casal_id: casalId,
      convidados: Number(convidadosNovo.value) || 0,
    })
    evento.value = data?.data || data
    addingPart.value = false
    buscaCasal.value = ''
    convidadosNovo.value = 0
    innovToast('success', 'OK', 'Casal adicionado.')
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Não foi possível adicionar.')
  }
}

async function atualizarConvidados(casalId, value) {
  try {
    const { data } = await api.put(`${props.apiBase}/${evento.value.id}/participantes`, {
      casal_id: casalId,
      convidados: Math.max(0, Number(value) || 0),
    })
    evento.value = data?.data || data
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Não foi possível atualizar convidados.')
  }
}

async function removeParticipante(casalId) {
  try {
    await api.delete(`${props.apiBase}/${evento.value.id}/participantes`, {
      data: { casal_id: casalId },
    })
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Não foi possível remover.')
  }
}

async function addItem() {
  if (!novoItem.value.nome) return
  try {
    await api.post(`${props.apiBase}/${evento.value.id}/itens-compra`, {
      nome: novoItem.value.nome,
      qtd: Number(novoItem.value.qtd) || 1,
      unidade: novoItem.value.unidade || 'un',
    })
    novoItem.value = { nome: '', qtd: '1', unidade: 'un' }
    innovToast('success', 'OK', 'Item adicionado.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao adicionar item.')
  }
}

async function confirmarDoar() {
  try {
    await api.post(
      `${props.apiBase}/${evento.value.id}/itens-compra/${doarItemId.value}/doar`,
      { casal_id: doarCasalId.value },
    )
    doarItemId.value = null
    doarCasalId.value = ''
    innovToast('success', 'OK', 'Item doado (sem movimento de caixa).')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao doar.')
  }
}

async function confirmarComprar() {
  try {
    await api.post(
      `${props.apiBase}/${evento.value.id}/itens-compra/${comprarItemId.value}/comprar`,
      { valor_gasto: Number(comprarValor.value) || 0 },
    )
    comprarItemId.value = null
    comprarValor.value = ''
    innovToast('success', 'OK', 'Compra debitada do caixa.')
    await load()
  } catch (e) {
    const msg =
      e?.response?.data?.errors?.valor_gasto?.[0] ||
      e?.response?.data?.message ||
      'Erro ao comprar.'
    innovToast('error', 'Erro', msg)
  }
}

async function desfazer(itemId) {
  try {
    await api.post(`${props.apiBase}/${evento.value.id}/itens-compra/${itemId}/desfazer`)
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao desfazer.')
  }
}

async function confirmarDoacaoDinheiro() {
  const payload = {
    valor: Number(doacaoForm.value.valor),
    descricao: doacaoForm.value.descricao || undefined,
  }
  if (doacaoForm.value.tipo === 'casal') payload.casal_id = doacaoForm.value.casal_id
  else if (doacaoForm.value.tipo === 'equipe') payload.ecc_equipe_id = doacaoForm.value.ecc_equipe_id
  else payload.doador_nome = (doacaoForm.value.doador_nome || '').trim()

  const temDoador = payload.casal_id || payload.ecc_equipe_id || payload.doador_nome
  if (!payload.valor || !temDoador) {
    innovToast('error', 'Validação', 'Informe valor e doador (casal, equipe ou nome).')
    return
  }
  try {
    await api.post(`${props.apiBase}/${evento.value.id}/caixa/doacoes`, payload)
    showDoacaoDinheiro.value = false
    doacaoForm.value = {
      tipo: 'casal',
      casal_id: '',
      ecc_equipe_id: '',
      doador_nome: '',
      valor: '',
      descricao: '',
    }
    buscaDoacao.value = ''
    innovToast('success', 'OK', 'Doação registrada no caixa.')
    await loadCaixa()
  } catch (e) {
    const msg =
      e?.response?.data?.errors?.doador_nome?.[0] ||
      e?.response?.data?.message ||
      'Erro ao registrar doação.'
    innovToast('error', 'Erro', msg)
  }
}

function imprimirRelatorio(tipoRelatorio = 'completo') {
  if (!evento.value) return
  const incluiCaixa =
    showCompras.value && (tipoRelatorio === 'completo' || tipoRelatorio === 'extrato')
  const html = buildEventoRelatorioHtml({
    evento: evento.value,
    caixa: incluiCaixa ? caixa.value : null,
    tipoNome: tipoLabel(tipos.value, evento.value.evento_tipo_id || evento.value.tipo),
    tipoRelatorio,
  })
  const w = window.open('', '_blank')
  if (!w) {
    innovToast('error', 'Bloqueado', 'Permita pop-ups para imprimir o relatório.')
    return
  }
  showRelatorios.value = false
  w.document.open()
  w.document.write(html)
  w.document.close()
  w.focus()
  setTimeout(() => w.print(), 250)
}

function abrirExcluir() {
  confirmaExcluir.value = ''
  showExcluir.value = true
}

async function confirmarExclusao() {
  if (!podeExcluir.value || !evento.value) return
  excluindo.value = true
  try {
    await api.delete(`${props.apiBase}/${evento.value.id}`)
    showExcluir.value = false
    innovToast('success', 'OK', 'Evento excluído.')
    router.push({ name: props.listRouteName })
  } catch (e) {
    innovToast('error', 'Erro', e?.response?.data?.message || 'Erro ao excluir evento.')
  } finally {
    excluindo.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="ecc-evento-detail">
    <button
      type="button"
      class="mb-4 text-sm font-semibold"
      style="color: #6B1C2B"
      @click="router.push({ name: listRouteName })"
    >
      ← Voltar aos eventos
    </button>

    <p v-if="loading" class="text-sm" style="color: #6B4A50">Carregando…</p>

    <template v-else-if="evento">
      <!-- Hero -->
      <div class="rounded-xl border p-5 mb-5" style="border-color: #E8DFD6; background: #FFFDFA">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-xs font-semibold" :style="{ color: tipoCor(tipos, evento.evento_tipo_id || evento.tipo) }">
              {{ tipoLabel(tipos, evento.evento_tipo_id || evento.tipo) }}
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold mt-1" style="font-family: Newsreader, Georgia, serif; color: #2A1418">
              {{ evento.titulo }}
            </h1>
            <p class="text-sm mt-2" style="color: #6B4A50">
              {{ formatWhen(evento.inicia_em) }}
              <template v-if="evento.local"> · {{ evento.local }}</template>
            </p>
          </div>
          <div class="flex flex-col items-end gap-3 shrink-0">
            <div class="flex flex-wrap gap-2 text-xs justify-end">
              <span class="px-2.5 py-1 rounded-full font-semibold" style="background: #F6EDE4; color: #B4703F">
                {{ evento.participantes_count || 0 }} casais
              </span>
              <span class="px-2.5 py-1 rounded-full font-semibold" style="background: #F6EDE4; color: #B4703F">
                {{ evento.convidados_total || 0 }} convidados
              </span>
              <span
                v-if="showCompras"
                class="px-2.5 py-1 rounded-full font-semibold"
                style="background: #E4F0EA; color: #2A6B4A"
              >
                Caixa {{ money(caixa?.saldo) }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2 justify-end">
              <button
                type="button"
                class="px-3 py-2 text-sm font-semibold rounded-lg border"
                style="border-color: #E8DFD6; color: #2A1418"
                data-testid="ecc-evento-imprimir"
                @click="showRelatorios = true"
              >
                Imprimir relatório
              </button>
              <button
                v-if="canManage"
                type="button"
                class="px-3 py-2 text-sm font-semibold rounded-lg border"
                style="border-color: #F3E1E1; color: #8A2436"
                data-testid="ecc-evento-excluir"
                @click="abrirExcluir"
              >
                Excluir evento
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Grid: participantes | caixa+compras -->
      <div
        class="grid grid-cols-1 gap-5"
        :class="showCompras ? 'lg:grid-cols-12' : ''"
      >
        <!-- Coluna esquerda: participantes -->
        <div :class="showCompras ? 'lg:col-span-4 xl:col-span-4' : ''">
          <div class="rounded-xl border p-5 lg:sticky lg:top-4" style="border-color: #E8DFD6; background: #FFFDFA">
            <div class="flex items-baseline justify-between gap-2 mb-1">
              <h2 class="font-semibold" style="color: #2A1418">
                Participantes · {{ evento.participantes_count || 0 }}
              </h2>
              <span class="text-xs" style="color: #6B4A50">{{ evento.convidados_total || 0 }} convidados</span>
            </div>
            <div
              v-for="p in evento.participantes || []"
              :key="p.casal_id"
              class="flex flex-wrap items-center gap-3 py-2.5 border-b last:border-b-0"
              style="border-color: #E8DFD6"
            >
              <span class="text-sm font-medium grow min-w-[120px]">{{ p.casal_rotulo }}</span>
              <label v-if="canManage" class="flex items-center gap-1 text-xs" style="color: #6B4A50">
                Convidados
                <input
                  type="number"
                  min="0"
                  class="w-14 border rounded-lg px-2 py-1 text-sm"
                  style="border-color: #E8DFD6"
                  :value="p.convidados"
                  @change="atualizarConvidados(p.casal_id, $event.target.value)"
                />
              </label>
              <button
                v-if="canManage"
                type="button"
                class="text-xs font-semibold"
                style="color: #8A2436"
                @click="removeParticipante(p.casal_id)"
              >
                Remover
              </button>
            </div>
            <p v-if="!(evento.participantes || []).length" class="text-sm py-2" style="color: #6B4A50">Nenhum casal ainda</p>
            <button
              v-if="canManage && !addingPart"
              type="button"
              class="mt-3 text-sm font-semibold"
              style="color: #6B1C2B"
              @click="addingPart = true"
            >
              + Adicionar casal
            </button>
            <div v-if="addingPart" class="mt-3">
              <input
                v-model="buscaCasal"
                type="search"
                placeholder="Pesquisar casal…"
                class="w-full border rounded-lg px-3 py-2 text-sm mb-2"
                style="border-color: #E8DFD6"
              />
              <label class="flex items-center gap-2 text-xs mb-2" style="color: #6B4A50">
                Convidados externos
                <input v-model.number="convidadosNovo" type="number" min="0" class="w-16 border rounded-lg px-2 py-1 text-sm" style="border-color: #E8DFD6" />
              </label>
              <div class="max-h-56 overflow-auto">
                <button
                  v-for="c in casaisFora"
                  :key="c.id"
                  type="button"
                  class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-[#F7F4EF]"
                  @click="addParticipante(c.id)"
                >
                  {{ casalRotulo(c) }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Coluna direita: caixa + compras -->
        <div v-if="showCompras" class="lg:col-span-8 xl:col-span-8 flex flex-col gap-5">
          <div
            class="rounded-xl border p-5"
            style="border-color: #E8DFD6; background: #FFFDFA"
            data-testid="ecc-evento-caixa"
          >
            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
              <div>
                <h2 class="font-semibold" style="color: #2A1418">Conta corrente</h2>
                <p class="text-xs mt-0.5" style="color: #6B4A50">
                  Doações entram; comprar item debita o saldo.
                </p>
              </div>
              <button
                v-if="canManage"
                type="button"
                class="px-3 py-2 text-sm font-semibold rounded-lg text-white shrink-0"
                style="background: #6B1C2B"
                data-testid="ecc-caixa-doar"
                @click="showDoacaoDinheiro = true"
              >
                Registrar doação
              </button>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-3">
              <div class="rounded-lg p-3" style="background: #F7F4EF">
                <div class="text-base sm:text-lg font-semibold" style="color: #6B1C2B">{{ money(caixa?.saldo) }}</div>
                <div class="text-[11px]" style="color: #6B4A50">Saldo</div>
              </div>
              <div class="rounded-lg p-3" style="background: #E4F0EA">
                <div class="text-base sm:text-lg font-semibold" style="color: #2A6B4A">{{ money(caixa?.total_entradas) }}</div>
                <div class="text-[11px]" style="color: #6B4A50">Arrecadado · {{ caixa?.qtd_doacoes || 0 }}</div>
              </div>
              <div class="rounded-lg p-3" style="background: #F3E1E1">
                <div class="text-base sm:text-lg font-semibold" style="color: #8A2436">{{ money(caixa?.total_saidas) }}</div>
                <div class="text-[11px]" style="color: #6B4A50">Gasto · {{ caixa?.qtd_compras_caixa || 0 }}</div>
              </div>
            </div>
            <div class="text-xs mb-2" style="color: #6B4A50">
              Itens {{ caixa?.itens?.total || 0 }} ·
              pendentes {{ caixa?.itens?.pendentes || 0 }} ·
              doados {{ caixa?.itens?.doados || 0 }} ·
              comprados {{ caixa?.itens?.comprados || 0 }}
            </div>
            <div v-if="caixa?.extrato?.length" class="mt-2 max-h-72 lg:max-h-96 overflow-auto">
              <div class="text-xs font-semibold uppercase mb-1 sticky top-0 py-1" style="color: #B4703F; background: #FFFDFA">
                Extrato
              </div>
              <div
                v-for="mov in caixa.extrato"
                :key="mov.id"
                class="flex items-center gap-2 py-2 border-b text-sm"
                style="border-color: #E8DFD6"
              >
                <span
                  class="w-6 h-6 rounded grid place-items-center text-xs font-bold shrink-0"
                  :style="mov.tipo === 'entrada' ? { background: '#E4F0EA', color: '#2A6B4A' } : { background: '#F3E1E1', color: '#8A2436' }"
                >
                  {{ mov.tipo === 'entrada' ? '+' : '−' }}
                </span>
                <div class="grow min-w-0">
                  <div class="font-medium truncate">{{ mov.descricao }}</div>
                  <div class="text-xs truncate" style="color: #6B4A50">
                    <template v-if="mov.doador">{{ mov.doador.rotulo }} · </template>
                    {{ mov.created_at ? new Date(mov.created_at).toLocaleString('pt-BR') : '' }}
                  </div>
                </div>
                <div class="font-semibold shrink-0" :style="{ color: mov.tipo === 'entrada' ? '#2A6B4A' : '#8A2436' }">
                  {{ mov.tipo === 'entrada' ? '+' : '−' }} {{ money(mov.valor) }}
                </div>
              </div>
            </div>
          </div>

          <div
            class="rounded-xl border overflow-hidden"
            style="border-color: #E8DFD6; background: #FFFDFA"
            data-testid="ecc-evento-compras"
          >
            <div class="px-5 pt-4 pb-2 text-xs font-semibold uppercase" style="color: #B4703F">Lista de compras</div>
            <div
              v-for="it in evento.itens_compra || []"
              :key="it.id"
              class="flex flex-wrap items-center gap-3 px-5 py-3 border-t"
              style="border-color: #E8DFD6"
            >
              <div class="grow min-w-[140px]">
                <div class="font-semibold text-sm">{{ it.nome }}</div>
                <div class="text-xs" style="color: #6B4A50">
                  {{ it.qtd }} {{ it.unidade }}
                  <template v-if="it.status === 'doado'"> · {{ it.doador_rotulo }} doou</template>
                  <template v-if="it.status === 'comprado'"> · comprado por {{ money(it.valor_gasto) }} do caixa</template>
                </div>
              </div>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: #F5E9DA; color: #8a6414">
                {{ statusCompraLabel(it.status) }}
              </span>
              <div v-if="canManage" class="flex gap-2 flex-wrap">
                <template v-if="it.status === 'pendente'">
                  <button type="button" class="text-xs font-semibold" style="color: #6B1C2B" @click="doarItemId = it.id">Doar item</button>
                  <button type="button" class="text-xs font-semibold" style="color: #6B1C2B" @click="comprarItemId = it.id; comprarValor = ''">Comprar</button>
                </template>
                <button v-else type="button" class="text-xs font-semibold" style="color: #6B4A50" @click="desfazer(it.id)">Desfazer</button>
              </div>
            </div>
            <div v-if="!(evento.itens_compra || []).length" class="px-5 py-6 text-sm text-center border-t" style="border-color: #E8DFD6; color: #6B4A50">
              Nenhum item ainda.
            </div>
            <div v-if="canManage" class="px-5 py-3 border-t flex flex-wrap gap-2" style="border-color: #E8DFD6">
              <input v-model="novoItem.nome" placeholder="Novo item" class="border rounded-lg px-3 py-2 text-sm flex-[2] min-w-[120px]" style="border-color: #E8DFD6" />
              <input v-model="novoItem.qtd" placeholder="Qtd" class="border rounded-lg px-3 py-2 text-sm w-20" style="border-color: #E8DFD6" />
              <input v-model="novoItem.unidade" placeholder="un" class="border rounded-lg px-3 py-2 text-sm w-20" style="border-color: #E8DFD6" />
              <button type="button" class="px-3 py-2 text-sm font-semibold rounded-lg text-white" style="background: #6B1C2B" @click="addItem">Adicionar</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Modais: doar item, comprar, doação dinheiro -->
    <div v-if="doarItemId" class="fixed inset-0 z-50 flex items-end md:items-center justify-center" style="background: rgba(42, 20, 24, 0.4)" @click.self="doarItemId = null">
      <div class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5" style="background: #FFFDFA">
        <h3 class="font-semibold mb-3">Quem vai doar o item</h3>
        <input v-model="buscaCasal" type="search" placeholder="Pesquisar…" class="w-full border rounded-lg px-3 py-2 text-sm mb-2" style="border-color: #E8DFD6" />
        <button
          v-for="c in filterCasaisBusca(casais, buscaCasal)"
          :key="c.id"
          type="button"
          class="w-full text-left px-3 py-2 rounded-lg text-sm mb-1"
          :style="doarCasalId === c.id ? { background: '#F5E9DA' } : {}"
          @click="doarCasalId = c.id"
        >
          {{ casalRotulo(c) }}
        </button>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="doarItemId = null">Cancelar</button>
          <button type="button" class="px-4 py-2 text-sm font-semibold rounded-lg text-white" style="background: #6B1C2B" :disabled="!doarCasalId" @click="confirmarDoar">Confirmar</button>
        </div>
      </div>
    </div>

    <div v-if="comprarItemId" class="fixed inset-0 z-50 flex items-end md:items-center justify-center" style="background: rgba(42, 20, 24, 0.4)" @click.self="comprarItemId = null">
      <div class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5" style="background: #FFFDFA">
        <h3 class="font-semibold mb-1">Comprar com o caixa</h3>
        <p class="text-xs mb-3" style="color: #6B4A50">Saldo disponível: {{ money(caixa?.saldo) }}</p>
        <input v-model="comprarValor" type="number" min="0" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm" style="border-color: #E8DFD6" placeholder="Valor gasto" />
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" @click="comprarItemId = null">Cancelar</button>
          <button type="button" class="px-4 py-2 text-sm font-semibold rounded-lg text-white" style="background: #6B1C2B" @click="confirmarComprar">Debitar e confirmar</button>
        </div>
      </div>
    </div>

    <div v-if="showDoacaoDinheiro" class="fixed inset-0 z-50 flex items-end md:items-center justify-center" style="background: rgba(42, 20, 24, 0.4)" @click.self="showDoacaoDinheiro = false">
      <div class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5 max-h-[90vh] overflow-auto" style="background: #FFFDFA" data-testid="ecc-caixa-doacao-modal">
        <h3 class="font-semibold mb-3">Doação em dinheiro</h3>
        <div class="flex flex-wrap gap-2 mb-3">
          <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border" :style="doacaoForm.tipo === 'casal' ? { background: '#6B1C2B', color: '#fff' } : {}" @click="doacaoForm.tipo = 'casal'">Casal</button>
          <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border" :style="doacaoForm.tipo === 'equipe' ? { background: '#6B1C2B', color: '#fff' } : {}" @click="doacaoForm.tipo = 'equipe'">Equipe</button>
          <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border" :style="doacaoForm.tipo === 'outro' ? { background: '#6B1C2B', color: '#fff' } : {}" @click="doacaoForm.tipo = 'outro'">Outro</button>
        </div>
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Valor</label>
        <input v-model="doacaoForm.valor" type="number" min="0.01" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm mb-3" style="border-color: #E8DFD6" />
        <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Descrição</label>
        <input v-model="doacaoForm.descricao" class="w-full border rounded-lg px-3 py-2 text-sm mb-3" style="border-color: #E8DFD6" placeholder="Opcional" />
        <template v-if="doacaoForm.tipo === 'casal'">
          <input v-model="buscaDoacao" type="search" placeholder="Pesquisar casal…" class="w-full border rounded-lg px-3 py-2 text-sm mb-2" style="border-color: #E8DFD6" />
          <div class="max-h-40 overflow-auto mb-3">
            <button
              v-for="c in casaisDoacao"
              :key="c.id"
              type="button"
              class="w-full text-left px-3 py-2 rounded-lg text-sm"
              :style="doacaoForm.casal_id === c.id ? { background: '#F5E9DA' } : {}"
              @click="doacaoForm.casal_id = c.id"
            >
              {{ casalRotulo(c) }}
            </button>
          </div>
        </template>
        <template v-else-if="doacaoForm.tipo === 'equipe'">
          <div class="max-h-40 overflow-auto mb-3">
            <button
              v-for="e in equipes"
              :key="e.id"
              type="button"
              class="w-full text-left px-3 py-2 rounded-lg text-sm"
              :style="doacaoForm.ecc_equipe_id === e.id ? { background: '#F5E9DA' } : {}"
              @click="doacaoForm.ecc_equipe_id = e.id"
            >
              {{ e.nome }}
            </button>
          </div>
        </template>
        <template v-else>
          <label class="block text-xs font-semibold mb-1" style="color: #6B4A50">Doador</label>
          <input
            v-model="doacaoForm.doador_nome"
            class="w-full border rounded-lg px-3 py-2 text-sm mb-3"
            style="border-color: #E8DFD6"
            placeholder="Ex.: Padre João, Paróquia, doador voluntário…"
            data-testid="ecc-caixa-doador-nome"
          />
        </template>
        <div class="flex justify-end gap-2">
          <button type="button" @click="showDoacaoDinheiro = false">Cancelar</button>
          <button type="button" class="px-4 py-2 text-sm font-semibold rounded-lg text-white" style="background: #6B1C2B" @click="confirmarDoacaoDinheiro">Registrar</button>
        </div>
      </div>
    </div>

    <div
      v-if="showRelatorios"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      @click.self="showRelatorios = false"
    >
      <div
        class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5"
        style="background: #FFFDFA"
        data-testid="ecc-evento-relatorios-modal"
      >
        <h3 class="font-semibold mb-1" style="color: #2A1418">Imprimir relatório</h3>
        <p class="text-sm mb-4" style="color: #6B4A50">
          Escolha o relatório completo ou uma seção específica.
        </p>
        <div class="flex flex-col gap-2">
          <button
            v-for="r in relatoriosDisponiveis"
            :key="r.id"
            type="button"
            class="w-full text-left rounded-xl border px-4 py-3 transition hover:bg-[#F7F4EF]"
            style="border-color: #E8DFD6"
            :data-testid="`ecc-relatorio-${r.id}`"
            @click="imprimirRelatorio(r.id)"
          >
            <div class="text-sm font-semibold" style="color: #2A1418">{{ r.label }}</div>
            <div class="text-xs mt-0.5" style="color: #6B4A50">{{ r.descricao }}</div>
          </button>
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" class="text-sm font-semibold" style="color: #6B4A50" @click="showRelatorios = false">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showExcluir"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      @click.self="showExcluir = false"
    >
      <div
        class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5"
        style="background: #FFFDFA"
        data-testid="ecc-evento-excluir-modal"
      >
        <h3 class="font-semibold mb-1" style="color: #8A2436">Excluir evento</h3>
        <p class="text-sm mb-3" style="color: #6B4A50">
          Esta ação remove o evento, participantes, compras e lançamentos do caixa. Digite
          <strong>deletar</strong> para confirmar.
        </p>
        <input
          v-model="confirmaExcluir"
          type="text"
          autocomplete="off"
          class="w-full border rounded-lg px-3 py-2 text-sm mb-4"
          style="border-color: #E8DFD6"
          placeholder="digite deletar"
          data-testid="ecc-evento-excluir-confirma"
          @keyup.enter="confirmarExclusao"
        />
        <div class="flex justify-end gap-2">
          <button type="button" @click="showExcluir = false">Cancelar</button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold rounded-lg text-white disabled:opacity-40"
            style="background: #8A2436"
            :disabled="!podeExcluir || excluindo"
            data-testid="ecc-evento-excluir-confirm-btn"
            @click="confirmarExclusao"
          >
            {{ excluindo ? 'Excluindo…' : 'Excluir definitivamente' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
