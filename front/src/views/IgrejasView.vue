<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useIgrejaStore } from '@/stores/igreja'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import { igrejaTipoLabel } from '@/utils/igrejaContext'

const router = useRouter()
const tenantStore = useTenantStore()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const igrejaStore = useIgrejaStore()

const mode = ref('list')
const form = ref(emptyForm())
const saving = ref(false)
const search = ref('')

function emptyForm() {
  return {
    id: null,
    nome: '',
    tipo: 'paroquia',
    endereco: '',
    bairro: '',
    cidade: '',
    uf: '',
    cep: '',
    telefone: '',
    email: '',
  }
}

const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)

const can = (perm) =>
  userHasPermission(authTenant.user, perm, { isSuperAdmin: isPlatformAdmin.value })

const canCreate = computed(() => can('igrejas.create'))
const canUpdate = computed(() => can('igrejas.update'))
const canDelete = computed(() => can('igrejas.delete'))

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return igrejaStore.igrejas
  return igrejaStore.igrejas.filter(
    (i) =>
      (i.nome || '').toLowerCase().includes(q) ||
      (i.cidade || '').toLowerCase().includes(q) ||
      (i.tipo || '').toLowerCase().includes(q),
  )
})

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
  try {
    await igrejaStore.load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao listar igrejas')
  }
}

const openCreate = () => {
  form.value = emptyForm()
  mode.value = 'form'
}

const openEdit = (item) => {
  form.value = {
    id: item.id,
    nome: item.nome || '',
    tipo: item.tipo || 'paroquia',
    endereco: item.endereco || '',
    bairro: item.bairro || '',
    cidade: item.cidade || '',
    uf: item.uf || '',
    cep: item.cep || '',
    telefone: item.telefone || '',
    email: item.email || '',
  }
  mode.value = 'form'
}

const canEditItem = (item) => {
  if (!canUpdate.value) return false
  if (canCreate.value || isPlatformAdmin.value) return true
  // admin-igreja: só a igreja atual / acessível (lista já filtrada)
  return item.id === igrejaStore.currentId || igrejaStore.igrejas.some((i) => i.id === item.id)
}

const save = async () => {
  saving.value = true
  try {
    const payload = {
      nome: form.value.nome,
      tipo: form.value.tipo,
      endereco: form.value.endereco || null,
      bairro: form.value.bairro || null,
      cidade: form.value.cidade || null,
      uf: form.value.uf || null,
      cep: form.value.cep || null,
      telefone: form.value.telefone || null,
      email: form.value.email || null,
    }
    if (form.value.id) {
      await api.put(`/igrejas/${form.value.id}`, payload)
    } else {
      await api.post('/igrejas', payload)
    }
    innovToast('success', 'OK', 'Igreja salva')
    mode.value = 'list'
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const remove = async (item) => {
  if (!confirm(`Remover "${item.nome}"?`)) return
  try {
    await api.delete(`/igrejas/${item.id}`)
    if (igrejaStore.currentId === item.id) {
      igrejaStore.select('')
    }
    await load()
  } catch (e) {
    innovToast(
      'error',
      'Erro',
      e.response?.data?.message || 'Falha ao remover (verifique vínculos)',
    )
  }
}

onMounted(load)
</script>

<template>
  <div data-testid="igrejas-page">
    <div class="mb-6">
      <p class="page-eyebrow">Organização</p>
      <h2 class="text-3xl" style="color: var(--color-primary)">Igrejas</h2>
      <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
        Paróquias e comunidades administradas nesta organização.
      </p>
    </div>

    <div v-if="mode === 'list'" class="space-y-4">
      <div class="flex flex-wrap gap-3 items-center">
        <button
          v-if="canCreate"
          class="btn btn-primary"
          data-testid="igreja-nova"
          @click="openCreate"
        >
          Nova igreja
        </button>
        <input
          v-model="search"
          type="search"
          class="input max-w-sm"
          placeholder="Pesquisar…"
          data-testid="igrejas-search"
          aria-label="Pesquisar igreja"
        />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div v-if="igrejaStore.loading" class="card p-6" style="color: var(--color-muted)">
          Carregando…
        </div>
        <div
          v-else-if="!filtered.length"
          class="card p-6 sm:col-span-2 text-center"
          style="color: var(--color-muted)"
        >
          {{ search ? 'Nenhuma igreja encontrada.' : 'Nenhuma igreja cadastrada.' }}
        </div>
        <div v-for="item in filtered" :key="item.id" class="card p-5">
          <p class="text-xs uppercase tracking-wider font-bold" style="color: var(--color-muted)">
            {{ igrejaTipoLabel(item.tipo) }}
          </p>
          <h3 class="text-xl mt-1" style="color: var(--color-ink)">{{ item.nome }}</h3>
          <p v-if="item.cidade || item.uf" class="mt-2 text-sm" style="color: var(--color-muted)">
            {{ [item.cidade, item.uf].filter(Boolean).join(' / ') }}
          </p>
          <p v-if="item.telefone || item.email" class="mt-1 text-sm" style="color: var(--color-muted)">
            {{ [item.telefone, item.email].filter(Boolean).join(' · ') }}
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-if="canEditItem(item)"
              class="btn btn-ghost"
              data-testid="igreja-editar"
              @click="openEdit(item)"
            >
              Editar
            </button>
            <button
              v-if="canDelete"
              class="btn btn-ghost text-red-700"
              data-testid="igreja-excluir"
              @click="remove(item)"
            >
              Excluir
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-6 max-w-xl">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar igreja' : 'Nova igreja' }}</h3>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="ig-nome">Nome</label>
          <input id="ig-nome" v-model="form.nome" class="input" required data-testid="igreja-nome" />
        </div>
        <div>
          <label class="fld" for="ig-tipo">Tipo</label>
          <select id="ig-tipo" v-model="form.tipo" class="input" data-testid="igreja-tipo">
            <option value="paroquia">Paróquia</option>
            <option value="comunidade">Comunidade</option>
            <option value="outro">Outro</option>
          </select>
        </div>
        <div>
          <label class="fld" for="ig-endereco">Endereço</label>
          <input id="ig-endereco" v-model="form.endereco" class="input" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="fld" for="ig-bairro">Bairro</label>
            <input id="ig-bairro" v-model="form.bairro" class="input" />
          </div>
          <div>
            <label class="fld" for="ig-cidade">Cidade</label>
            <input id="ig-cidade" v-model="form.cidade" class="input" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="fld" for="ig-uf">UF</label>
            <input id="ig-uf" v-model="form.uf" class="input" maxlength="2" />
          </div>
          <div>
            <label class="fld" for="ig-cep">CEP</label>
            <input id="ig-cep" v-model="form.cep" class="input" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="fld" for="ig-telefone">Telefone</label>
            <input id="ig-telefone" v-model="form.telefone" class="input" />
          </div>
          <div>
            <label class="fld" for="ig-email">E-mail</label>
            <input id="ig-email" v-model="form.email" type="email" class="input" />
          </div>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="igreja-salvar">
            Salvar
          </button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>
