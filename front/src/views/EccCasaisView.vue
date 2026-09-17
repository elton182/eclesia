<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as XLSX from 'xlsx'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { casaisListQuery, casaisListQueryFromRoute, filterCasais } from '@/utils/eccFilters'
import { isoToBr } from '@/utils/dateBr'
import { userHasPermission } from '@/utils/userRoles'
import { casalEle, casalEla, formFieldsFromEleEla } from '@/utils/casalDisplay'
import DateInput from '@/components/form/DateInput.vue'
import PessoaFotoField from '@/components/ecc/PessoaFotoField.vue'
import { uploadPessoaFoto } from '@/utils/pessoaFoto'

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
const loading = ref(false)
/** @type {import('vue').Ref<'list'|'form'|'equipes'|'equipe-form'>} */
const mode = ref('list')
const saving = ref(false)
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
  nome_conjuge: '',
  email_conjuge: '',
  telefone_conjuge: '',
  data_nascimento_conjuge: '',
  endereco: '',
  bairro: '',
  cidade: '',
  uf: '',
  cep: '',
  data_casamento: '',
  filhos: '',
  observacoes: '',
  piloto: false,
  anos_casados: '',
  ecc_origem: '',
  experiencia_servico: '',
  preferencia_funcao: '',
  funcao_dirigente: '',
  foi_coordenador_geral: false,
  etapa_2: '',
  etapa_3: '',
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

const load = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    const [casaisRes] = await Promise.all([
      api.get('/ecc/casais'),
      loadEquipes(),
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
    ...rest
  } = form.value
  return {
    ...rest,
    equipe_id: form.value.equipe_id || null,
    anos_casados: form.value.anos_casados === '' || form.value.anos_casados === null
      ? null
      : Number(form.value.anos_casados),
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
  if (!confirm(`Remover o casal ${casalEle(item)} & ${casalEla(item)}?`)) return
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
  applyEquipeFromQuery()
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
    <div v-else class="card p-6">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar casal' : 'Novo casal' }}</h3>
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
        <div>
          <label class="fld">2ª etapa</label>
          <input v-model="form.etapa_2" class="input">
        </div>
        <div>
          <label class="fld">3ª etapa</label>
          <input v-model="form.etapa_3" class="input">
        </div>
        <div class="md:col-span-2">
          <label class="fld">Experiência no encontro</label>
          <textarea v-model="form.experiencia_servico" class="input min-h-20" />
        </div>
        <div class="md:col-span-2">
          <label class="fld">Preferência de função</label>
          <textarea v-model="form.preferencia_funcao" class="input min-h-20" />
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
