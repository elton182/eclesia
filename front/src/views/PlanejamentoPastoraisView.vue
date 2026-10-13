<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { userHasPermission } from '@/utils/userRoles'
import { innovConfirm } from '@/plugins/dialog'
import { innovToast } from '@/plugins/toast'

const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()
const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)
const can = (p) =>
  userHasPermission(authTenant.user, p, { isSuperAdmin: isPlatformAdmin.value })
const canManagePast = computed(
  () => can('site.pastorais.manage') || can('planejamento.gerir') || isPlatformAdmin.value,
)
const canGerirPlan = computed(() => can('planejamento.gerir') || isPlatformAdmin.value)

const pastorais = ref([])
const anos = ref([])
const anoId = ref('')
const form = ref({ id: null, nome: '', descricao_publica: '', ordem: 0, ativa: true, publicado_no_site: true })
const showForm = ref(false)
const lastToken = ref(null)

async function load() {
  const [p, a] = await Promise.all([
    api.get('/site/pastorais'),
    api.get('/planejamento/anos').catch(() => ({ data: { data: [] } })),
  ])
  pastorais.value = p.data.data || p.data || []
  anos.value = a.data.data || a.data || []
  if (!anoId.value && anos.value.length) {
    const civil = new Date().getFullYear()
    anoId.value = (anos.value.find((x) => x.ano === civil) || anos.value[0]).id
  }
}

function openNovo() {
  form.value = {
    id: null,
    nome: '',
    descricao_publica: '',
    ordem: pastorais.value.length,
    ativa: true,
    publicado_no_site: true,
  }
  showForm.value = true
}

function openEdit(item) {
  form.value = {
    id: item.id,
    nome: item.nome,
    descricao_publica: item.descricao_publica || '',
    ordem: item.ordem ?? 0,
    ativa: item.ativa !== false,
    publicado_no_site: item.publicado_no_site !== false,
  }
  showForm.value = true
}

async function save() {
  const body = {
    nome: form.value.nome,
    descricao_publica: form.value.descricao_publica || null,
    ordem: Number(form.value.ordem) || 0,
    ativa: !!form.value.ativa,
    publicado_no_site: !!form.value.publicado_no_site,
  }
  try {
    if (form.value.id) {
      await api.put(`/site/pastorais/${form.value.id}`, body)
      innovToast('success', 'Pastorais', 'Atualizada')
    } else {
      await api.post('/site/pastorais', body)
      innovToast('success', 'Pastorais', 'Criada')
    }
    showForm.value = false
    await load()
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao salvar')
  }
}

async function remove(item) {
  const ok = await innovConfirm({
    title: 'Remover pastoral',
    message: `Remover “${item.nome}”? O cadastro é compartilhado com o Site.`,
  })
  if (!ok) return
  try {
    await api.delete(`/site/pastorais/${item.id}`)
    innovToast('success', 'Pastorais', 'Removida')
    await load()
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao remover')
  }
}

async function gerarToken(pastoral) {
  if (!anoId.value) {
    innovToast('error', 'Link', 'Abra um ano de planejamento primeiro.')
    return
  }
  try {
    const { data } = await api.post(
      `/planejamento/anos/${anoId.value}/pastorais/${pastoral.id}/token`,
    )
    const token = data.token || data.data?.token
    const qs = tenantStore.slug ? `?tenant=${encodeURIComponent(tenantStore.slug)}` : ''
    lastToken.value = {
      pastoral: pastoral.nome,
      token,
      url: `${window.location.origin}/planejamento/publico/${token}${qs}`,
    }
    innovToast('success', 'Link', 'Token gerado — copie o link abaixo')
  } catch (e) {
    innovToast('error', 'Link', e.response?.data?.message || 'Falha ao gerar link')
  }
}

async function copiarLink() {
  if (!lastToken.value?.url) return
  await navigator.clipboard.writeText(lastToken.value.url)
  innovToast('success', 'Link', 'Copiado')
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="planejamento-pastorais">
    <header class="mb-8">
      <p class="page-eyebrow">Planejamento</p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">Pastorais</h1>
      <p class="text-[14px] mt-1 max-w-xl" style="color: var(--color-muted)">
        Mesmo cadastro do Site. Gere o link de cada pastoral para o ano em coleta.
      </p>
    </header>

    <div v-if="canGerirPlan && anos.length" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
      <div>
        <label class="fld" for="ano-token">Ano para links</label>
        <select id="ano-token" v-model="anoId" class="input">
          <option v-for="a in anos" :key="a.id" :value="a.id">{{ a.ano }} · {{ a.status }}</option>
        </select>
      </div>
      <div
        v-if="lastToken"
        class="flex-1 min-w-[220px] text-sm"
        data-testid="planejamento-last-token"
      >
        <div class="font-medium" style="color: var(--color-ink)">{{ lastToken.pastoral }}</div>
        <code class="text-xs break-all" style="color: var(--color-muted)">{{ lastToken.url }}</code>
        <button type="button" class="btn btn-ghost text-xs mt-1" @click="copiarLink">Copiar link</button>
      </div>
    </div>

    <div class="flex justify-end mb-4" v-if="canManagePast">
      <button type="button" class="btn btn-primary" data-testid="planejamento-pastoral-nova" @click="openNovo">
        Nova pastoral
      </button>
    </div>

    <div
      v-if="!pastorais.length"
      class="border border-dashed rounded-xl p-8 text-center"
      style="border-color: var(--color-line)"
    >
      <p class="font-serif text-lg" style="color: var(--color-ink)">Nenhuma pastoral</p>
      <p class="text-sm mt-1" style="color: var(--color-muted)">Cadastre para liberar propostas no calendário.</p>
    </div>

    <ul v-else class="space-y-3">
      <li v-for="p in pastorais" :key="p.id" class="card p-5 flex flex-wrap justify-between gap-3">
        <div>
          <div class="font-medium" style="color: var(--color-ink)">{{ p.nome }}</div>
          <div class="text-xs mt-1" style="color: var(--color-muted)">
            {{ p.ativa === false ? 'Inativa' : 'Ativa' }}
            · {{ p.publicado_no_site === false ? 'Fora do site' : 'No site' }}
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="canGerirPlan"
            type="button"
            class="btn btn-accent text-xs"
            @click="gerarToken(p)"
          >
            Gerar link
          </button>
          <button v-if="canManagePast" type="button" class="btn btn-ghost text-xs" @click="openEdit(p)">
            Editar
          </button>
          <button v-if="canManagePast" type="button" class="btn btn-danger text-xs" @click="remove(p)">
            Remover
          </button>
        </div>
      </li>
    </ul>

    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.4)"
      @click.self="showForm = false"
    >
      <form class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5 space-y-3" style="background: var(--color-surface)" @submit.prevent="save">
        <h3 class="font-serif text-lg" style="color: var(--color-ink)">
          {{ form.id ? 'Editar pastoral' : 'Nova pastoral' }}
        </h3>
        <div>
          <label class="fld" for="p-nome">Nome</label>
          <input id="p-nome" v-model="form.nome" class="input" required />
        </div>
        <div>
          <label class="fld" for="p-desc">Descrição pública</label>
          <textarea id="p-desc" v-model="form.descricao_publica" class="input" rows="2" />
        </div>
        <div>
          <label class="fld" for="p-ordem">Ordem</label>
          <input id="p-ordem" v-model.number="form.ordem" type="number" class="input" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.ativa" type="checkbox" /> Ativa
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.publicado_no_site" type="checkbox" /> Publicada no site
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</template>
