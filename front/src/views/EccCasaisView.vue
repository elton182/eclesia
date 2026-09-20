<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as XLSX from 'xlsx'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { casaisListQuery, casaisListQueryFromRoute, filterCasais } from '@/utils/eccFilters'
import { isoToBr } from '@/utils/dateBr'
import { userHasPermission } from '@/utils/userRoles'
import { casalEle, casalEla, formFieldsFromEleEla, swapEleElaFormFields } from '@/utils/casalDisplay'
import DateInput from '@/components/form/DateInput.vue'
import PessoaFotoField from '@/components/ecc/PessoaFotoField.vue'
import { uploadPessoaFoto } from '@/utils/pessoaFoto'
import {
  ATIVIDADE_STATUS_CODES,
  atividadeStatusLabel,
  emptyAtividadeRow,
  emptyPreferenciaRow,
  etapasPayload,
  normalizeEtapasForm,
} from '@/utils/eccFicha'

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()

const authOpts = () => ({
  isSuperAdmin: authAdminStore.isAuthenticated,
})

const canManageCasais = computed(() =>
  userHasPermission(authStore.user, 'ecc.casais.manage', authOpts()),
)

const canManageEquipes = computed(() =>
  userHasPermission(authStore.user, 'ecc.equipes.manage', authOpts()),
)

const casais = ref([])
const equipes = ref([])
const equipesServico = ref([])
const loading = ref(false)
/** @type {import('vue').Ref<'list'|'form'|'equipes'|'equipe-form'>} */
const mode = ref('list')
const saving = ref(false)
const swapping = ref(false)
const savingEquipe = ref(false)
const importing = ref(false)
const filterEquipe = ref('')
const search = ref('')
const fileInput = ref(null)
const importErrors = ref([])
const equipeForm = ref({ id: null, nome: '', cor: '#6B1C2B' })

const applyFiltersFromQuery = () => {
  filterEquipe.value = route.query.equipe ? String(route.query.equipe) : ''
  search.value = route.query.q ? String(route.query.q) : ''
}

const syncFiltersToQuery = () => {
  const next = casaisListQuery({
    equipeId: filterEquipe.value,
    search: search.value,
  })
  const current = casaisListQueryFromRoute(route.query)
  if (next.equipe === current.equipe && next.q === current.q) return
  const query = { ...route.query }
  if (next.equipe) query.equipe = next.equipe
  else delete query.equipe
  if (next.q) query.q = next.q
  else delete query.q
  router.replace({ name: 'ecc-casais', query })
}

watch(() => [route.query.equipe, route.query.q], applyFiltersFromQuery, { immediate: true })
watch([filterEquipe, search], syncFiltersToQuery)

const openCasalDetail = (casal) => {
  router.push({
    name: 'ecc-casal-detail',
    params: { id: casal.id },
    query: casaisListQueryFromRoute(route.query),
  })
}

const emptyForm = () => ({
  id: null,
  equipe_id: '',
  nome: '',
  email: '',
  telefone: '',
  data_nascimento: '',
  nome_usual_ele: '',
  profissao_ele: '',
  religiao_ele: '',
  endereco_profissional_ele: '',
  telefone_profissional_ele: '',
  nome_conjuge: '',
  email_conjuge: '',
  telefone_conjuge: '',
  data_nascimento_conjuge: '',
  nome_usual_ela: '',
  profissao_ela: '',
  religiao_ela: '',
  endereco_profissional_ela: '',
  telefone_profissional_ela: '',
  endereco: '',
  bairro: '',
  cidade: '',
  uf: '',
  cep: '',
  data_casamento: '',
  filhos: '',
  observacoes: '',
  engajamento_paroquial: '',
  habilidades: '',
  piloto: false,
  anos_casados: '',
  ecc_origem: '',
  funcao_dirigente: '',
  foi_coordenador_geral: false,
  etapas: normalizeEtapasForm([]),
  atividades: [],
  preferencias: [],
  pessoa_a_id: null,
  pessoa_b_id: null,
  foto_url_ele: null,
  foto_url_ela: null,
  pending_foto_ele: null,
  pending_foto_ela: null,
})

const form = ref(emptyForm())

const filtered = computed(() =>
  filterCasais({
    casais: casais.value,
    search: search.value,
    equipeId: filterEquipe.value,
  }),
)

const ensureTenant = () => {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/inicio')
    return false
  }
  return true
}

const loadEquipes = async () => {
  const { data } = await api.get('/ecc/equipes')
  equipes.value = data.data || data
  if (filterEquipe.value && !equipes.value.some((e) => String(e.id) === String(filterEquipe.value))) {
    filterEquipe.value = ''
  }
}

const loadEquipesServico = async () => {
  const { data } = await api.get('/ecc/equipes-servico')
  equipesServico.value = data.data || data
}

const load = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    const [casaisRes] = await Promise.all([
      api.get('/ecc/casais'),
      loadEquipes(),
      loadEquipesServico(),
    ])
    casais.value = casaisRes.data.data || casaisRes.data
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = emptyForm()
  mode.value = 'form'
}

const openEdit = (item) => {
  const pessoas = formFieldsFromEleEla(item)
  form.value = {
    ...emptyForm(),
    ...item,
    ...pessoas,
    equipe_id: item.equipe_id || '',
    data_nascimento: isoToBr(pessoas.data_nascimento),
    data_nascimento_conjuge: isoToBr(pessoas.data_nascimento_conjuge),
    data_casamento: isoToBr(item.data_casamento),
    etapas: normalizeEtapasForm(item.etapas).map((e) => ({
      ...e,
      data: isoToBr(e.data) || e.data || '',
    })),
    atividades: (item.atividades || []).map((a) => ({
      ecc_numero: a.ecc_numero || '',
      equipe_servico_id: a.equipe_servico_id || '',
      status: a.status || 'A',
      observacao: a.observacao || '',
    })),
    preferencias: (item.preferencias || []).map((p, i) => ({
      equipe_servico_id: p.equipe_servico_id || '',
      ordem: p.ordem ?? i + 1,
    })),
    engajamento_paroquial: item.engajamento_paroquial || '',
    habilidades: item.habilidades || '',
  }
  mode.value = 'form'
}

const openEquipesPanel = () => {
  mode.value = 'equipes'
}

const closeEquipesPanel = () => {
  mode.value = 'list'
}

const openEquipeCreate = () => {
  equipeForm.value = { id: null, nome: '', cor: '#6B1C2B' }
  mode.value = 'equipe-form'
}

const openEquipeEdit = (item) => {
  equipeForm.value = {
    id: item.id,
    nome: item.nome,
    cor: item.cor || '#6B1C2B',
  }
  mode.value = 'equipe-form'
}

const saveEquipe = async () => {
  savingEquipe.value = true
  try {
    if (equipeForm.value.id) {
      await api.put(`/ecc/equipes/${equipeForm.value.id}`, {
        nome: equipeForm.value.nome,
        cor: equipeForm.value.cor,
      })
    } else {
      await api.post('/ecc/equipes', {
        nome: equipeForm.value.nome,
        cor: equipeForm.value.cor,
      })
    }
    innovToast('success', 'OK', 'Equipe salva')
    mode.value = 'equipes'
    await loadEquipes()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar equipe')
  } finally {
    savingEquipe.value = false
  }
}

const applyEditFromQuery = () => {
  const editId = route.query.edit ? String(route.query.edit) : ''
  if (!editId || !casais.value.length) return false
  const item = casais.value.find((c) => String(c.id) === editId)
  if (!item) return false
  openEdit(item)
  const query = { ...route.query }
  delete query.edit
  router.replace({ query })
  return true
}

watch(
  () => [route.query.edit, casais.value.length],
  () => {
    applyEditFromQuery()
  },
)

const payload = () => {
  const {
    pessoa_a_id: _a,
    pessoa_b_id: _b,
    foto_url_ele: _fe,
    foto_url_ela: _fa,
    pending_foto_ele: _pe,
    pending_foto_ela: _pa,
    ficha_com_foto: _f,
    nome_usual_ele,
    profissao_ele,
    religiao_ele,
    endereco_profissional_ele,
    telefone_profissional_ele,
    nome_usual_ela,
    profissao_ela,
    religiao_ela,
    endereco_profissional_ela,
    telefone_profissional_ela,
    etapas,
    atividades,
    preferencias,
    ele: _ele,
    ela: _ela,
    ...rest
  } = form.value
  return {
    ...rest,
    equipe_id: form.value.equipe_id || null,
    anos_casados: form.value.anos_casados === '' || form.value.anos_casados === null
      ? null
      : Number(form.value.anos_casados),
    ele: {
      nome_usual: nome_usual_ele || null,
      profissao: profissao_ele || null,
      religiao: religiao_ele || null,
      endereco_profissional: endereco_profissional_ele || null,
      telefone_profissional: telefone_profissional_ele || null,
    },
    ela: {
      nome_usual: nome_usual_ela || null,
      profissao: profissao_ela || null,
      religiao: religiao_ela || null,
      endereco_profissional: endereco_profissional_ela || null,
      telefone_profissional: telefone_profissional_ela || null,
    },
    etapas: etapasPayload(etapas),
    atividades: (atividades || [])
      .filter((a) => a.ecc_numero && a.equipe_servico_id && a.status)
      .map((a) => ({
        ecc_numero: a.ecc_numero,
        equipe_servico_id: a.equipe_servico_id,
        status: a.status,
        observacao: a.observacao || null,
      })),
    preferencias: (preferencias || [])
      .filter((p) => p.equipe_servico_id)
      .map((p, i) => ({
        equipe_servico_id: p.equipe_servico_id,
        ordem: p.ordem ? Number(p.ordem) : i + 1,
      })),
  }
}

const addAtividade = () => {
  form.value.atividades.push(emptyAtividadeRow())
}

const removeAtividade = (index) => {
  form.value.atividades.splice(index, 1)
}

const addPreferencia = () => {
  form.value.preferencias.push(emptyPreferenciaRow(form.value.preferencias.length + 1))
}

const removePreferencia = (index) => {
  form.value.preferencias.splice(index, 1)
}

const swapEleEla = async () => {
  if (swapping.value) return
  const ok = await innovConfirm({
    title: 'Trocar',
    message: 'Trocar Ele e Ela neste casal?',
    confirmText: 'Trocar',
    danger: true,
  })
  if (!ok) return

  if (!form.value.id) {
    form.value = swapEleElaFormFields(form.value)
    innovToast('success', 'OK', 'Ele e Ela invertidos no formulário')
    return
  }

  swapping.value = true
  try {
    const { data } = await api.post(`/ecc/casais/${form.value.id}/swap`)
    const saved = data.data || data
    // Preserva pendências de foto locais após o swap persistido
    const pendingEle = form.value.pending_foto_ele
    const pendingEla = form.value.pending_foto_ela
    openEdit(saved)
    form.value.pending_foto_ele = pendingEla
    form.value.pending_foto_ela = pendingEle
    innovToast('success', 'OK', 'Ele e Ela corrigidos')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao trocar')
  } finally {
    swapping.value = false
  }
}

const save = async () => {
  saving.value = true
  try {
    let saved
    if (form.value.id) {
      const { data } = await api.put(`/ecc/casais/${form.value.id}`, payload())
      saved = data.data || data
    } else {
      const { data } = await api.post('/ecc/casais', payload())
      saved = data.data || data
    }

    const eleId = saved?.ele?.id
    const elaId = saved?.ela?.id
    if (form.value.pending_foto_ele && eleId) {
      await uploadPessoaFoto(api, eleId, form.value.pending_foto_ele)
    }
    if (form.value.pending_foto_ela && elaId) {
      await uploadPessoaFoto(api, elaId, form.value.pending_foto_ela)
    }

    innovToast('success', 'OK', 'Casal salvo')
    mode.value = 'list'
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const remove = async (item) => {
  const ok = await innovConfirm({
    title: 'Remover',
    message: `Remover o casal ${casalEle(item)} & ${casalEla(item)}?`,
    confirmText: 'Remover',
    danger: true,
  })
  if (!ok) return
  try {
    await api.delete(`/ecc/casais/${item.id}`)
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

const onImportFile = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  importing.value = true
  importErrors.value = []
  try {
    const buffer = await file.arrayBuffer()
    const workbook = XLSX.read(buffer, { type: 'array', cellDates: true })
    const sheet = workbook.Sheets[workbook.SheetNames[0]]
    const rows = XLSX.utils.sheet_to_json(sheet, { defval: '', raw: false })

    if (!rows.length) {
      innovToast('error', 'Importação', 'Planilha vazia')
      return
    }

    const { data } = await api.post('/ecc/casais/import', { rows })
    importErrors.value = data.errors || []

    const msg =
      `${data.imported} casais importados` +
      (data.skipped ? ` · ${data.skipped} linha(s) vazia(s)` : '') +
      (data.errors?.length ? ` · ${data.errors.length} com erro` : '')

    innovToast(data.imported > 0 ? 'success' : 'error', 'Importação', msg)
    await load()
  } catch (e) {
    innovToast('error', 'Importação', e.response?.data?.message || e.message || 'Falha')
  } finally {
    importing.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

onMounted(async () => {
  applyFiltersFromQuery()
  await load()
  applyEditFromQuery()
})
</script>

<template>
  <div class="p-6 md:p-[30px] w-full" data-testid="ecc-casais-page">
    <div class="mb-6 md:mb-8">
      <p class="page-eyebrow">ECC</p>
      <h2 class="font-serif text-[27px] leading-tight mt-1" style="color: #2A1418">Casais</h2>
      <p class="mt-2 text-[13.5px] leading-relaxed" style="color: rgba(42, 20, 24, 0.62)">
        Cadastro de casais e vínculo com equipe. Importe a planilha Excel do modelo atual.
      </p>
    </div>

    <!-- Lista de casais -->
    <div v-if="mode === 'list'" class="space-y-5 w-full">
      <div class="flex flex-col gap-3 w-full md:flex-row md:flex-wrap md:items-center">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <button
            v-if="canManageCasais"
            class="btn btn-primary w-full sm:w-auto"
            data-testid="casais-novo"
            @click="openCreate"
          >
            Novo casal
          </button>
          <button
            v-if="canManageCasais"
            class="btn btn-accent w-full sm:w-auto"
            :disabled="importing"
            data-testid="casais-import-btn"
            @click="fileInput?.click()"
          >
            {{ importing ? 'Importando…' : 'Importar Excel' }}
          </button>
          <button
            v-if="canManageEquipes"
            type="button"
            class="btn btn-ghost w-full sm:w-auto"
            data-testid="casais-gerenciar-equipes"
            @click="openEquipesPanel"
          >
            Gerenciar equipes
          </button>
          <input
            v-if="canManageCasais"
            ref="fileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="hidden"
            data-testid="casais-import"
            @change="onImportFile"
          >
        </div>
        <input
          v-model="search"
          type="search"
          class="input w-full md:flex-1 md:min-w-[200px]"
          placeholder="Pesquisar nome, equipe, cidade…"
          data-testid="casais-search"
          aria-label="Pesquisar casais"
        >
        <select
          v-model="filterEquipe"
          class="input w-full md:w-auto md:min-w-[220px]"
          data-testid="casais-filtro-equipe"
          aria-label="Filtrar por equipe"
        >
          <option value="">Todas as equipes</option>
          <option v-for="eq in equipes" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
        </select>
      </div>

      <div
        v-if="importErrors.length"
        class="card p-4 text-sm"
        style="border-color: #f5c2c7; background: #fff5f5"
      >
        <p class="font-semibold mb-2 text-red-800">Erros na importação ({{ importErrors.length }})</p>
        <ul class="max-h-40 overflow-auto space-y-1 text-red-700">
          <li v-for="err in importErrors.slice(0, 30)" :key="err.line + err.message">
            Linha {{ err.line }}: {{ err.message }}
          </li>
        </ul>
      </div>

      <div
        class="w-full overflow-hidden rounded-[11px]"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
      >
        <div v-if="loading" class="p-10 text-center" style="color: rgba(42, 20, 24, 0.5)">Carregando…</div>
        <div v-else-if="!filtered.length" class="p-10 text-center" style="color: rgba(42, 20, 24, 0.5)">
          {{ search || filterEquipe ? 'Nenhum casal encontrado.' : 'Nenhum casal. Cadastre ou importe a planilha.' }}
        </div>
        <div v-else>
          <div
            v-for="casal in filtered"
            :key="casal.id"
            class="flex flex-col gap-3 px-5 md:px-6 py-5 w-full sm:flex-row sm:items-center sm:gap-5"
            style="border-top: 1px solid rgba(42, 20, 24, 0.07)"
          >
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="flex -space-x-2 shrink-0">
                <div
                  class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center text-xs font-bold text-white border-2 border-white"
                  style="background: #4E1220"
                  data-testid="casais-avatar-ele"
                >
                  <img
                    v-if="casal.ele?.foto_url"
                    :src="casal.ele.foto_url"
                    :alt="casalEle(casal)"
                    class="w-full h-full object-cover"
                  >
                  <template v-else>{{ (casalEle(casal) || '?').charAt(0) }}</template>
                </div>
                <div
                  class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center text-xs font-bold border-2 border-white"
                  style="background: #C88A5E; color: #4E1220"
                  data-testid="casais-avatar-ela"
                >
                  <img
                    v-if="casal.ela?.foto_url"
                    :src="casal.ela.foto_url"
                    :alt="casalEla(casal)"
                    class="w-full h-full object-cover"
                  >
                  <template v-else>{{ (casalEla(casal) || '?').charAt(0) }}</template>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-[15px] truncate" style="color: #2A1418">
                  {{ casalEle(casal) }} & {{ casalEla(casal) }}
                </div>
                <div class="text-[13px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
                  {{ casal.equipe_nome || 'Sem equipe' }}
                  <span v-if="casal.cidade"> · {{ casal.cidade }}/{{ casal.uf }}</span>
                  <span v-if="casal.piloto" class="badge badge-warning ml-2">Piloto</span>
                  <span v-if="casal.ecc_origem" class="ml-1">· ECC {{ casal.ecc_origem }}</span>
                </div>
              </div>
            </div>
            <div
              v-if="canManageCasais"
              class="flex flex-wrap gap-2 shrink-0 sm:justify-end"
            >
              <button
                class="btn btn-ghost"
                data-testid="casais-abrir"
                @click="openCasalDetail(casal)"
              >
                Abrir
              </button>
              <button class="btn btn-ghost" data-testid="casais-editar" @click="openEdit(casal)">
                Editar
              </button>
              <button
                class="btn btn-ghost text-red-700"
                data-testid="casais-excluir"
                @click="remove(casal)"
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Painel gerenciar equipes -->
    <div
      v-else-if="mode === 'equipes'"
      class="w-full space-y-5"
      data-testid="casais-equipes-panel"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h3 class="font-serif text-xl" style="color: #2A1418">Equipes</h3>
          <p class="text-[13px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
            Grupos aos quais os casais pertencem.
          </p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            class="btn btn-primary w-full sm:w-auto"
            data-testid="equipes-nova"
            @click="openEquipeCreate"
          >
            Nova equipe
          </button>
          <button
            type="button"
            class="btn btn-ghost w-full sm:w-auto"
            data-testid="equipes-fechar"
            @click="closeEquipesPanel"
          >
            Voltar aos casais
          </button>
        </div>
      </div>

      <div
        class="w-full overflow-hidden rounded-[11px]"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
      >
        <div
          v-if="!equipes.length"
          class="p-10 text-center"
          style="color: rgba(42, 20, 24, 0.5)"
        >
          Nenhuma equipe cadastrada.
        </div>
        <div
          v-for="equipe in equipes"
          :key="equipe.id"
          class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
          style="border-top: 1px solid rgba(42, 20, 24, 0.07)"
          data-testid="equipe-row"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <div
              class="w-[30px] h-[30px] rounded-md flex items-center justify-center font-serif text-[13px] font-medium shrink-0"
              :style="{ background: '#F3EDE6', color: equipe.cor || '#6B1C2B' }"
            >
              {{ (equipe.nome || '?')[0] }}
            </div>
            <div class="min-w-0">
              <div class="text-[13.5px] font-medium truncate" style="color: #2A1418">
                {{ equipe.nome }}
              </div>
              <div class="text-[12.5px]" style="color: rgba(42, 20, 24, 0.62)">
                {{ equipe.casais_count ?? 0 }} casais
              </div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-filtrar-casais"
              @click="filterEquipe = String(equipe.id); closeEquipesPanel()"
            >
              Ver casais
            </button>
            <button
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-editar"
              @click="openEquipeEdit(equipe)"
            >
              Editar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form equipe -->
    <div
      v-else-if="mode === 'equipe-form'"
      class="rounded-[11px] p-6 w-full max-w-md"
      style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
      data-testid="equipe-form"
    >
      <h3 class="font-serif text-xl mb-4">
        {{ equipeForm.id ? 'Editar equipe' : 'Nova equipe' }}
      </h3>
      <form class="space-y-4" @submit.prevent="saveEquipe">
        <div>
          <label class="fld" for="eq-nome">Nome</label>
          <input id="eq-nome" v-model="equipeForm.nome" class="input" required data-testid="equipe-nome">
        </div>
        <div>
          <label class="fld" for="eq-cor">Cor</label>
          <input
            id="eq-cor"
            v-model="equipeForm.cor"
            type="color"
            class="h-10 w-20"
            data-testid="equipe-cor"
          >
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
          <button
            type="submit"
            class="btn btn-primary w-full sm:w-auto"
            :disabled="savingEquipe"
            data-testid="equipe-salvar"
          >
            Salvar
          </button>
          <button
            type="button"
            class="btn btn-ghost w-full sm:w-auto"
            @click="mode = 'equipes'"
          >
            Cancelar
          </button>
        </div>
      </form>
    </div>

    <!-- Form casal -->
    <div v-else class="card p-6" data-testid="casal-form">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-xl mb-0">{{ form.id ? 'Editar casal' : 'Novo casal' }}</h3>
        <button
          type="button"
          class="btn btn-ghost"
          :disabled="swapping"
          data-testid="casal-form-swap-ele-ela"
          @click="swapEleEla"
        >
          Trocar Ele/Ela
        </button>
      </div>
      <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="save">
        <div class="md:col-span-2">
          <label class="fld">Equipe</label>
          <select v-model="form.equipe_id" class="input">
            <option value="">Sem equipe</option>
            <option v-for="eq in equipes" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
          </select>
        </div>

        <div>
          <label class="fld">Ele (nome)</label>
          <input v-model="form.nome" class="input" required>
        </div>
        <div>
          <label class="fld">Ela (nome)</label>
          <input v-model="form.nome_conjuge" class="input" required>
        </div>
        <div>
          <label class="fld">E-mail (Ele)</label>
          <input v-model="form.email" type="email" class="input">
        </div>
        <div>
          <label class="fld">E-mail (Ela)</label>
          <input v-model="form.email_conjuge" type="email" class="input">
        </div>
        <div>
          <label class="fld">Telefone (Ele)</label>
          <input v-model="form.telefone" class="input">
        </div>
        <div>
          <label class="fld">Telefone (Ela)</label>
          <input v-model="form.telefone_conjuge" class="input">
        </div>
        <DateInput
          v-model="form.data_nascimento"
          name="data_nascimento"
          label="Nascimento (Ele)"
        />
        <DateInput
          v-model="form.data_nascimento_conjuge"
          name="data_nascimento_conjuge"
          label="Nascimento (Ela)"
        />
        <div class="md:col-span-2 grid md:grid-cols-2 gap-4">
          <PessoaFotoField
            :pessoa-id="form.pessoa_a_id"
            :foto-url="form.foto_url_ele"
            label="Foto (Ele)"
            :pending-file="form.pending_foto_ele"
            @update:foto-url="form.foto_url_ele = $event"
            @update:pending-file="form.pending_foto_ele = $event"
          />
          <PessoaFotoField
            :pessoa-id="form.pessoa_b_id"
            :foto-url="form.foto_url_ela"
            label="Foto (Ela)"
            :pending-file="form.pending_foto_ela"
            @update:foto-url="form.foto_url_ela = $event"
            @update:pending-file="form.pending_foto_ela = $event"
          />
        </div>
        <div>
          <label class="fld">Nome usual (Ele)</label>
          <input v-model="form.nome_usual_ele" class="input" data-testid="form-nome-usual-ele">
        </div>
        <div>
          <label class="fld">Nome usual (Ela)</label>
          <input v-model="form.nome_usual_ela" class="input" data-testid="form-nome-usual-ela">
        </div>
        <div>
          <label class="fld">Profissão (Ele)</label>
          <input v-model="form.profissao_ele" class="input">
        </div>
        <div>
          <label class="fld">Profissão (Ela)</label>
          <input v-model="form.profissao_ela" class="input">
        </div>
        <div>
          <label class="fld">Religião (Ele)</label>
          <input v-model="form.religiao_ele" class="input">
        </div>
        <div>
          <label class="fld">Religião (Ela)</label>
          <input v-model="form.religiao_ela" class="input">
        </div>
        <div>
          <label class="fld">Endereço profissional (Ele)</label>
          <input v-model="form.endereco_profissional_ele" class="input">
        </div>
        <div>
          <label class="fld">Endereço profissional (Ela)</label>
          <input v-model="form.endereco_profissional_ela" class="input">
        </div>
        <div>
          <label class="fld">Telefone profissional (Ele)</label>
          <input v-model="form.telefone_profissional_ele" class="input">
        </div>
        <div>
          <label class="fld">Telefone profissional (Ela)</label>
          <input v-model="form.telefone_profissional_ela" class="input">
        </div>
        <div class="md:col-span-2">
          <label class="fld">Endereço</label>
          <input v-model="form.endereco" class="input">
        </div>
        <div>
          <label class="fld">Bairro</label>
          <input v-model="form.bairro" class="input">
        </div>
        <div>
          <label class="fld">Cidade</label>
          <input v-model="form.cidade" class="input">
        </div>
        <div>
          <label class="fld">UF</label>
          <input v-model="form.uf" class="input" maxlength="2">
        </div>
        <div>
          <label class="fld">CEP</label>
          <input v-model="form.cep" class="input">
        </div>
        <DateInput
          v-model="form.data_casamento"
          name="data_casamento"
          label="Data casamento"
        />
        <div>
          <label class="fld">Anos de casados</label>
          <input v-model="form.anos_casados" type="number" min="0" max="120" class="input">
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6 md:col-span-2">
          <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
            <input v-model="form.piloto" type="checkbox" class="rounded">
            Piloto
          </label>
          <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
            <input v-model="form.foi_coordenador_geral" type="checkbox" class="rounded">
            Foi coordenador geral
          </label>
          <span
            v-if="form.foto_url_ele || form.foto_url_ela || form.pending_foto_ele || form.pending_foto_ela"
            class="text-sm"
            style="color: rgba(42,20,24,0.62)"
            data-testid="ficha-com-foto-indicator"
          >
            Ficha com foto
          </span>
        </div>
        <div>
          <label class="fld">ECC de origem</label>
          <input v-model="form.ecc_origem" class="input" placeholder="Ex.: 8º">
        </div>
        <div>
          <label class="fld">Função dirigente</label>
          <input v-model="form.funcao_dirigente" class="input">
        </div>
        <div class="md:col-span-2" data-testid="form-etapas">
          <div class="fld mb-2">Etapas (nº do ECC, data e local)</div>
          <div
            v-for="(et, idx) in form.etapas"
            :key="et.etapa"
            class="grid md:grid-cols-4 gap-3 mb-3"
          >
            <div class="text-[13px] font-medium self-center" style="color: var(--color-ink)">
              {{ et.etapa }}ª etapa
            </div>
            <div>
              <label class="fld">Nº ECC</label>
              <input v-model="form.etapas[idx].ecc_numero" class="input" :data-testid="`etapa-${et.etapa}-numero`">
            </div>
            <DateInput
              v-model="form.etapas[idx].data"
              :name="`etapa_${et.etapa}_data`"
              label="Data"
            />
            <div>
              <label class="fld">Local</label>
              <input v-model="form.etapas[idx].local" class="input">
            </div>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="fld">Engajamento paroquial</label>
          <textarea v-model="form.engajamento_paroquial" class="input min-h-20" data-testid="form-engajamento" />
        </div>
        <div class="md:col-span-2">
          <label class="fld">Habilidades</label>
          <textarea v-model="form.habilidades" class="input min-h-20" data-testid="form-habilidades" />
        </div>
        <div class="md:col-span-2" data-testid="form-atividades">
          <div class="flex items-center justify-between mb-2">
            <div class="fld mb-0">Histórico de atividades (equipes de trabalho)</div>
            <button type="button" class="btn btn-ghost text-sm" @click="addAtividade">+ Atividade</button>
          </div>
          <div
            v-for="(at, idx) in form.atividades"
            :key="idx"
            class="grid md:grid-cols-5 gap-2 mb-2 items-end"
          >
            <div>
              <label class="fld">Nº ECC</label>
              <input v-model="at.ecc_numero" class="input">
            </div>
            <div>
              <label class="fld">Equipe</label>
              <select v-model="at.equipe_servico_id" class="input">
                <option value="">—</option>
                <option v-for="eq in equipesServico" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
              </select>
            </div>
            <div>
              <label class="fld">Status</label>
              <select v-model="at.status" class="input">
                <option v-for="code in ATIVIDADE_STATUS_CODES" :key="code" :value="code">
                  {{ code }} — {{ atividadeStatusLabel(code) }}
                </option>
              </select>
            </div>
            <div>
              <label class="fld">Obs.</label>
              <input v-model="at.observacao" class="input">
            </div>
            <button type="button" class="btn btn-ghost" @click="removeAtividade(idx)">Remover</button>
          </div>
          <p v-if="!form.atividades.length" class="text-[12.5px]" style="color: rgba(42,20,24,0.55)">
            Nenhum serviço registrado. Ex.: ECC 34 na Cozinha.
          </p>
        </div>
        <div class="md:col-span-2" data-testid="form-preferencias">
          <div class="flex items-center justify-between mb-2">
            <div class="fld mb-0">Preferências de equipe</div>
            <button type="button" class="btn btn-ghost text-sm" @click="addPreferencia">+ Preferência</button>
          </div>
          <div
            v-for="(pref, idx) in form.preferencias"
            :key="idx"
            class="grid md:grid-cols-3 gap-2 mb-2 items-end"
          >
            <div>
              <label class="fld">Ordem</label>
              <input v-model="pref.ordem" type="number" min="1" class="input">
            </div>
            <div>
              <label class="fld">Equipe</label>
              <select v-model="pref.equipe_servico_id" class="input">
                <option value="">—</option>
                <option v-for="eq in equipesServico" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
              </select>
            </div>
            <button type="button" class="btn btn-ghost" @click="removePreferencia(idx)">Remover</button>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="fld">Filhos</label>
          <input v-model="form.filhos" class="input">
        </div>
        <div class="md:col-span-2">
          <label class="fld">Observações</label>
          <textarea v-model="form.observacoes" class="input min-h-24" />
        </div>

        <div class="md:col-span-2 flex flex-col gap-2 sm:flex-row">
          <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="saving">
            Salvar
          </button>
          <button type="button" class="btn btn-ghost w-full sm:w-auto" @click="mode = 'list'">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
