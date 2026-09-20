<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { parseTenantAliases, extractApiError } from '@/utils/tenantAuth'

const router = useRouter()
const tenantStore = useTenantStore()

const tenants = ref([])
const loading = ref(false)
const mode = ref('list')
const form = ref({ id: null, name: '', slug: '', aliasesText: '' })
const saving = ref(false)
const formError = ref('')

const parseAliases = parseTenantAliases

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/admin/tenants')
    tenants.value = data.data || data
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao listar tenants')
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = { id: null, name: '', slug: '', aliasesText: '' }
  formError.value = ''
  mode.value = 'form'
}

const openEdit = (tenant) => {
  form.value = {
    id: tenant.id,
    name: tenant.name,
    slug: tenant.slug,
    aliasesText: (tenant.aliases || []).join(', '),
  }
  formError.value = ''
  mode.value = 'form'
}

const slugify = (value) =>
  value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')

const onNameInput = () => {
  if (!form.value.id) {
    form.value.slug = slugify(form.value.name)
  }
}

const save = async () => {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      name: form.value.name,
      slug: form.value.slug,
      aliases: parseAliases(form.value.aliasesText),
    }
    if (form.value.id) {
      await api.put(`/admin/tenants/${form.value.id}`, payload)
      innovToast('success', 'OK', 'Tenant atualizado')
    } else {
      await api.post('/admin/tenants', payload)
      innovToast('success', 'OK', 'Tenant criado e banco provisionado')
    }
    mode.value = 'list'
    await load()
  } catch (e) {
    const msg = extractApiError(e)
    formError.value = msg
    innovToast('error', 'Erro', msg)
  } finally {
    saving.value = false
  }
}

const remove = async (tenant) => {
  const ok = await innovConfirm({
    title: 'Remover',
    message: `Remover o tenant "${tenant.name}" e o banco associado?`,
    confirmText: 'Remover',
    danger: true,
  })
  if (!ok) return
  try {
    await api.delete(`/admin/tenants/${tenant.id}`)
    if (tenantStore.slug === tenant.slug) tenantStore.clear()
    innovToast('success', 'OK', 'Tenant removido')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

const enterTenant = (tenant) => {
  tenantStore.select(tenant)
  router.push('/inicio')
}

const enterUsers = (tenant) => {
  tenantStore.select(tenant)
  router.push('/usuarios')
}

onMounted(load)
</script>

<template>
  <div data-testid="tenants-page">
    <div class="mb-6">
      <p class="page-eyebrow">Plataforma</p>
      <h2 class="text-3xl" style="color: var(--color-primary)">Tenants</h2>
      <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
        Crie organizações assinantes. Cada tenant recebe um banco próprio.
      </p>
    </div>

    <div v-if="mode === 'list'" class="space-y-4">
      <div class="flex justify-between items-center gap-3 flex-wrap">
        <button class="btn btn-primary" data-testid="tenant-new" @click="openCreate">
          Novo tenant
        </button>
      </div>

      <div class="card overflow-hidden">
        <div v-if="loading" class="p-8 text-center" style="color: var(--color-muted)">Carregando…</div>
        <div v-else-if="!tenants.length" class="p-8 text-center" style="color: var(--color-muted)">
          Nenhum tenant ainda. Crie o primeiro para começar o ECC.
        </div>
        <div v-else class="divide-y" style="border-color: var(--color-line)">
          <div
            v-for="tenant in tenants"
            :key="tenant.id"
            class="flex items-center gap-4 px-5 py-4"
          >
            <div class="flex-1 min-w-0">
              <div class="font-semibold">{{ tenant.name }}</div>
              <div class="text-sm" style="color: var(--color-muted)">
                slug: <code>{{ tenant.slug }}</code>
                <span v-if="tenant.aliases?.length">
                  · apelidos: {{ tenant.aliases.join(', ') }}
                </span>
              </div>
            </div>
            <div class="flex gap-2 flex-wrap">
              <button class="btn btn-accent btn-sm" @click="enterTenant(tenant)">
                Abrir ECC
              </button>
              <button
                class="btn btn-ghost btn-sm"
                data-testid="tenant-users"
                @click="enterUsers(tenant)"
              >
                Usuários
              </button>
              <button class="btn btn-ghost" @click="openEdit(tenant)">Editar</button>
              <button class="btn btn-ghost text-red-700" @click="remove(tenant)">Excluir</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-6 max-w-lg">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar tenant' : 'Novo tenant' }}</h3>
      <form class="space-y-4" @submit.prevent="save">
        <div
          v-if="formError"
          class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
          role="alert"
          data-testid="tenant-form-error"
        >
          {{ formError }}
        </div>
        <div>
          <label class="fld" for="tenant-name">Nome</label>
          <input
            id="tenant-name"
            v-model="form.name"
            class="input"
            required
            data-testid="tenant-name"
            @input="onNameInput"
          />
        </div>
        <div>
          <label class="fld" for="tenant-slug">Slug (header X-Tenant)</label>
          <input
            id="tenant-slug"
            v-model="form.slug"
            class="input"
            required
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            data-testid="tenant-slug"
          />
        </div>
        <div>
          <label class="fld" for="tenant-aliases">Apelidos (separados por vírgula)</label>
          <input
            id="tenant-aliases"
            v-model="form.aliasesText"
            class="input"
            placeholder="ex.: psj, são josé"
            data-testid="tenant-aliases"
          />
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="tenant-save">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.btn-sm {
  padding: 7px 12px;
  font-size: 13px;
}
</style>
