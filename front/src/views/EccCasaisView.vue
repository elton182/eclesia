<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as XLSX from 'xlsx'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { filterCasais } from '@/utils/eccFilters'
import { isoToBr } from '@/utils/dateBr'
import { userHasPermission } from '@/utils/userRoles'
import DateInput from '@/components/form/DateInput.vue'

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()

const canManageCasais = computed(() =>
  userHasPermission(authStore.user, 'ecc.casais.manage', {
    isSuperAdmin: authAdminStore.isAuthenticated,
  }),
)

const casais = ref([])
const equipes = ref([])
const loading = ref(false)
const mode = ref('list')
const saving = ref(false)
const importing = ref(false)
const filterEquipe = ref('')
const search = ref('')
const fileInput = ref(null)
const importErrors = ref([])

const applyEquipeFromQuery = () => {
  const q = route.query.equipe
  filterEquipe.value = q ? String(q) : ''
}

watch(() => route.query.equipe, applyEquipeFromQuery)

watch(filterEquipe, (id) => {
  const current = route.query.equipe ? String(route.query.equipe) : ''
  if (id === current) return
  const query = { ...route.query }
  if (id) query.equipe = id
  else delete query.equipe
  router.replace({ name: 'ecc-casais', query })
})

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
  ficha_com_foto: false,
  etapa_2: '',
  etapa_3: '',
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

const load = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    const [casaisRes, equipesRes] = await Promise.all([
      api.get('/ecc/casais'),
      api.get('/ecc/equipes'),
    ])
    casais.value = casaisRes.data.data || casaisRes.data
    equipes.value = equipesRes.data.data || equipesRes.data
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
  form.value = {
    ...emptyForm(),
    ...item,
    equipe_id: item.equipe_id || '',
    data_nascimento: isoToBr(item.data_nascimento),
    data_nascimento_conjuge: isoToBr(item.data_nascimento_conjuge),
    data_casamento: isoToBr(item.data_casamento),
  }
  mode.value = 'form'
}

const payload = () => ({
  ...form.value,
  equipe_id: form.value.equipe_id || null,
  anos_casados: form.value.anos_casados === '' || form.value.anos_casados === null
    ? null
    : Number(form.value.anos_casados),
})

const save = async () => {
  saving.value = true
  try {
    if (form.value.id) {
      await api.put(`/ecc/casais/${form.value.id}`, payload())
    } else {
      await api.post('/ecc/casais', payload())
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
  if (!confirm(`Remover o casal ${item.nome} & ${item.nome_conjuge}?`)) return
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

onMounted(() => {
  applyEquipeFromQuery()
  load()
})
</script>

<template>
  <div data-testid="ecc-casais-page">
    <div class="mb-6">
      <p class="page-eyebrow">ECC</p>
      <h2 class="text-3xl" style="color: var(--color-primary)">Casais</h2>
      <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
        Cadastro de casais e vínculo com equipe. Importe a planilha Excel do modelo atual.
      </p>
    </div>

    <div v-if="mode === 'list'" class="space-y-4">
      <div class="toolbar flex flex-wrap gap-3 items-center">
        <button
          v-if="canManageCasais"
          class="btn btn-primary"
          data-testid="casais-novo"
          @click="openCreate"
        >
          Novo casal
        </button>
        <button
          v-if="canManageCasais"
          class="btn btn-accent"
          :disabled="importing"
          data-testid="casais-import-btn"
          @click="fileInput?.click()"
        >
          {{ importing ? 'Importando…' : 'Importar Excel' }}
        </button>
        <input
          v-if="canManageCasais"
          ref="fileInput"
          type="file"
          accept=".xlsx,.xls,.csv"
          class="hidden"
          data-testid="casais-import"
          @change="onImportFile"
        />
        <input
          v-model="search"
          type="search"
          class="input max-w-xs flex-1 min-w-[180px]"
          placeholder="Pesquisar nome, equipe, cidade…"
          data-testid="casais-search"
          aria-label="Pesquisar casais"
        />
        <select
          v-model="filterEquipe"
          class="input max-w-xs"
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

      <div class="card overflow-hidden">
        <div v-if="loading" class="p-8 text-center" style="color: var(--color-muted)">Carregando…</div>
        <div v-else-if="!filtered.length" class="p-8 text-center" style="color: var(--color-muted)">
          {{ search || filterEquipe ? 'Nenhum casal encontrado.' : 'Nenhum casal. Cadastre ou importe a planilha.' }}
        </div>
        <div v-else class="divide-y" style="border-color: var(--color-line)">
          <div
            v-for="casal in filtered"
            :key="casal.id"
            class="flex items-center gap-4 px-5 py-4"
          >
            <div class="flex -space-x-2">
              <div
                class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white border-2 border-white"
                style="background: var(--color-primary)"
              >
                {{ (casal.nome || '?').charAt(0) }}
              </div>
              <div
                class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white border-2 border-white"
                style="background: var(--color-accent)"
              >
                {{ (casal.nome_conjuge || '?').charAt(0) }}
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold truncate" style="color: var(--color-ink)">
                {{ casal.nome }} & {{ casal.nome_conjuge }}
              </div>
              <div class="text-sm" style="color: var(--color-muted)">
                {{ casal.equipe_nome || 'Sem equipe' }}
                <span v-if="casal.cidade"> · {{ casal.cidade }}/{{ casal.uf }}</span>
                <span v-if="casal.piloto" class="badge badge-warning ml-2">Piloto</span>
                <span v-if="casal.ecc_origem" class="ml-2">· ECC {{ casal.ecc_origem }}</span>
              </div>
            </div>
            <div v-if="canManageCasais" class="flex gap-2">
              <button class="btn btn-ghost" data-testid="casais-editar" @click="openEdit(casal)">Editar</button>
              <button class="btn btn-ghost text-red-700" data-testid="casais-excluir" @click="remove(casal)">Excluir</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-6">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar casal' : 'Novo casal' }}</h3>
      <form class="grid md:grid-cols-2 gap-4" @submit.prevent="save">
        <div class="md:col-span-2">
          <label class="fld">Equipe</label>
          <select v-model="form.equipe_id" class="input">
            <option value="">Sem equipe</option>
            <option v-for="eq in equipes" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
          </select>
        </div>

        <div>
          <label class="fld">Nome</label>
          <input v-model="form.nome" class="input" required />
        </div>
        <div>
          <label class="fld">Nome cônjuge</label>
          <input v-model="form.nome_conjuge" class="input" required />
        </div>
        <div>
          <label class="fld">E-mail</label>
          <input v-model="form.email" type="email" class="input" />
        </div>
        <div>
          <label class="fld">E-mail cônjuge</label>
          <input v-model="form.email_conjuge" type="email" class="input" />
        </div>
        <div>
          <label class="fld">Telefone</label>
          <input v-model="form.telefone" class="input" />
        </div>
        <div>
          <label class="fld">Telefone cônjuge</label>
          <input v-model="form.telefone_conjuge" class="input" />
        </div>
        <DateInput
          v-model="form.data_nascimento"
          name="data_nascimento"
          label="Nascimento"
        />
        <DateInput
          v-model="form.data_nascimento_conjuge"
          name="data_nascimento_conjuge"
          label="Nascimento cônjuge"
        />
        <div class="md:col-span-2">
          <label class="fld">Endereço</label>
          <input v-model="form.endereco" class="input" />
        </div>
        <div>
          <label class="fld">Bairro</label>
          <input v-model="form.bairro" class="input" />
        </div>
        <div>
          <label class="fld">Cidade</label>
          <input v-model="form.cidade" class="input" />
        </div>
        <div>
          <label class="fld">UF</label>
          <input v-model="form.uf" class="input" maxlength="2" />
        </div>
        <div>
          <label class="fld">CEP</label>
          <input v-model="form.cep" class="input" />
        </div>
        <DateInput
          v-model="form.data_casamento"
          name="data_casamento"
          label="Data casamento"
        />
        <div>
          <label class="fld">Anos de casados</label>
          <input v-model="form.anos_casados" type="number" min="0" max="120" class="input" />
        </div>
        <div class="flex items-center gap-6 md:col-span-2">
          <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
            <input v-model="form.piloto" type="checkbox" class="rounded" />
            Piloto
          </label>
          <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
            <input v-model="form.foi_coordenador_geral" type="checkbox" class="rounded" />
            Foi coordenador geral
          </label>
          <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
            <input v-model="form.ficha_com_foto" type="checkbox" class="rounded" />
            Ficha com foto
          </label>
        </div>
        <div>
          <label class="fld">ECC de origem</label>
          <input v-model="form.ecc_origem" class="input" placeholder="Ex.: 8º" />
        </div>
        <div>
          <label class="fld">Função dirigente</label>
          <input v-model="form.funcao_dirigente" class="input" />
        </div>
        <div>
          <label class="fld">2ª etapa</label>
          <input v-model="form.etapa_2" class="input" />
        </div>
        <div>
          <label class="fld">3ª etapa</label>
          <input v-model="form.etapa_3" class="input" />
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
          <input v-model="form.filhos" class="input" />
        </div>
        <div class="md:col-span-2">
          <label class="fld">Observações</label>
          <textarea v-model="form.observacoes" class="input min-h-24" />
        </div>

        <div class="md:col-span-2 flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">Salvar</button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>
