<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import { BLOCK_TYPES } from '@/utils/siteBlocks'

const router = useRouter()
const tenantStore = useTenantStore()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()

const tab = ref('settings')
const loading = ref(false)
const saving = ref(false)

const settings = ref({
  publicado: false,
  titulo: '',
  subtitulo: '',
  menu: [],
  seo: {},
  cores: {},
  contato: {},
})

const pages = ref([])
const pageForm = ref(null)
const comunicados = ref([])
const comForm = ref(null)
const pastorais = ref([])
const pastForm = ref(null)
const forms = ref([])
const formForm = ref(null)
const submissions = ref([])
const igrejas = ref([])

const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)
const can = (perm) =>
  userHasPermission(authTenant.user, perm, { isSuperAdmin: isPlatformAdmin.value })

const canSettings = computed(() => can('site.settings.update') || can('site.settings.view'))
const canPages = computed(() => can('site.pages.view') || can('site.pages.manage'))
const canCom = computed(() => can('site.comunicados.view') || can('site.comunicados.manage'))
const canPast = computed(() => can('site.pastorais.view') || can('site.pastorais.manage'))
const canForms = computed(() => can('site.forms.view') || can('site.forms.manage'))

const publicUrl = computed(() =>
  tenantStore.slug ? `/site/${tenantStore.slug}` : '#',
)

const ensureTenant = () => {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/inicio')
    return false
  }
  return true
}

const loadSettings = async () => {
  const { data } = await api.get('/site/settings')
  settings.value = { ...settings.value, ...(data.data || data) }
}

const loadPages = async () => {
  const { data } = await api.get('/site/pages')
  pages.value = data.data || data || []
}

const loadComunicados = async () => {
  const { data } = await api.get('/site/comunicados')
  comunicados.value = data.data || data || []
}

const loadPastorais = async () => {
  const { data } = await api.get('/site/pastorais')
  pastorais.value = data.data || data || []
}

const loadForms = async () => {
  const { data } = await api.get('/site/forms')
  forms.value = data.data || data || []
}

const loadIgrejas = async () => {
  const { data } = await api.get('/igrejas')
  igrejas.value = data.data || data || []
}

const loadTab = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    if (tab.value === 'settings' && canSettings.value) await loadSettings()
    if (tab.value === 'pages' && canPages.value) await loadPages()
    if (tab.value === 'comunicados' && canCom.value) await loadComunicados()
    if (tab.value === 'pastorais' && canPast.value) {
      await Promise.all([loadPastorais(), loadIgrejas()])
    }
    if (tab.value === 'forms' && canForms.value) await loadForms()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    const { data } = await api.put('/site/settings', {
      publicado: settings.value.publicado,
      titulo: settings.value.titulo,
      subtitulo: settings.value.subtitulo,
      menu: settings.value.menu,
      seo: settings.value.seo,
      cores: settings.value.cores,
      contato: settings.value.contato,
    })
    settings.value = { ...settings.value, ...(data.data || data) }
    innovToast('success', 'OK', 'Configurações salvas')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const openNewPage = () => {
  pageForm.value = {
    id: null,
    slug: '',
    titulo: '',
    status: 'rascunho',
    is_home: false,
    mostrar_no_menu: true,
    blocks: [{ tipo: 'hero', ordem: 0, visivel: true, payload: { headline: '', texto: '' } }],
  }
}

const openEditPage = (p) => {
  pageForm.value = {
    ...p,
    blocks: (p.blocks || []).map((b) => ({
      tipo: b.tipo,
      ordem: b.ordem,
      visivel: b.visivel !== false,
      payload: { ...(b.payload || {}) },
    })),
  }
}

const savePage = async () => {
  saving.value = true
  try {
    const body = {
      slug: pageForm.value.slug,
      titulo: pageForm.value.titulo,
      status: pageForm.value.status,
      is_home: pageForm.value.is_home,
      mostrar_no_menu: pageForm.value.mostrar_no_menu,
      blocks: pageForm.value.blocks,
    }
    if (pageForm.value.id) {
      await api.put(`/site/pages/${pageForm.value.id}`, body)
    } else {
      await api.post('/site/pages', body)
    }
    innovToast('success', 'OK', 'Página salva')
    pageForm.value = null
    await loadPages()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar página')
  } finally {
    saving.value = false
  }
}

const removePage = async (p) => {
  if (!confirm(`Remover a página "${p.titulo}"?`)) return
  await api.delete(`/site/pages/${p.id}`)
  await loadPages()
}

const addBlock = () => {
  pageForm.value.blocks.push({
    tipo: 'richtext',
    ordem: pageForm.value.blocks.length,
    visivel: true,
    payload: { html: '' },
  })
}

const openNewCom = () => {
  comForm.value = {
    id: null,
    titulo: '',
    resumo: '',
    corpo: '',
    status: 'rascunho',
    destaque: false,
    igreja_id: null,
  }
}

const saveCom = async () => {
  saving.value = true
  try {
    const body = { ...comForm.value }
    delete body.id
    if (comForm.value.id) {
      await api.put(`/site/comunicados/${comForm.value.id}`, body)
    } else {
      await api.post('/site/comunicados', body)
    }
    innovToast('success', 'OK', 'Comunicado salvo')
    comForm.value = null
    await loadComunicados()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const removeCom = async (item) => {
  if (!confirm(`Remover "${item.titulo}"?`)) return
  await api.delete(`/site/comunicados/${item.id}`)
  await loadComunicados()
}

const openNewPast = () => {
  pastForm.value = {
    id: null,
    igreja_id: igrejas.value[0]?.id || '',
    nome: '',
    descricao_publica: '',
    contato_publico: '',
    publicado_no_site: false,
    ativa: true,
    ordem: 0,
  }
}

const savePast = async () => {
  saving.value = true
  try {
    const body = { ...pastForm.value }
    delete body.id
    if (pastForm.value.id) {
      await api.put(`/site/pastorais/${pastForm.value.id}`, body)
    } else {
      await api.post('/site/pastorais', body)
    }
    innovToast('success', 'OK', 'Pastoral salva')
    pastForm.value = null
    await loadPastorais()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const removePast = async (item) => {
  if (!confirm(`Remover "${item.nome}"?`)) return
  await api.delete(`/site/pastorais/${item.id}`)
  await loadPastorais()
}

const openNewForm = () => {
  formForm.value = {
    id: null,
    nome: '',
    slug: '',
    descricao: '',
    ativo: true,
    sucesso_mensagem: 'Obrigado pelo contato!',
    fields: [
      { nome: 'nome', label: 'Nome', tipo: 'text', obrigatorio: true },
      { nome: 'email', label: 'E-mail', tipo: 'email', obrigatorio: true },
      { nome: 'mensagem', label: 'Mensagem', tipo: 'textarea', obrigatorio: true },
    ],
  }
}

const saveFormDef = async () => {
  saving.value = true
  try {
    const body = {
      nome: formForm.value.nome,
      slug: formForm.value.slug,
      descricao: formForm.value.descricao,
      ativo: formForm.value.ativo,
      sucesso_mensagem: formForm.value.sucesso_mensagem,
      fields: formForm.value.fields,
    }
    if (formForm.value.id) {
      await api.put(`/site/forms/${formForm.value.id}`, body)
    } else {
      await api.post('/site/forms', body)
    }
    innovToast('success', 'OK', 'Formulário salvo')
    formForm.value = null
    await loadForms()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const loadSubmissions = async (formId) => {
  const { data } = await api.get(`/site/forms/${formId}/submissions`)
  submissions.value = data.data || data || []
}

const menuJson = computed({
  get: () => JSON.stringify(settings.value.menu || [], null, 2),
  set: (v) => {
    try {
      settings.value.menu = JSON.parse(v)
    } catch {
      /* ignore while typing */
    }
  },
})

onMounted(async () => {
  if (canSettings.value) tab.value = 'settings'
  else if (canPages.value) tab.value = 'pages'
  else if (canCom.value) tab.value = 'comunicados'
  else if (canPast.value) tab.value = 'pastorais'
  else if (canForms.value) tab.value = 'forms'
  await loadTab()
})

const setTab = async (t) => {
  tab.value = t
  pageForm.value = null
  comForm.value = null
  pastForm.value = null
  formForm.value = null
  submissions.value = []
  await loadTab()
}
</script>

<template>
  <div class="space-y-6" data-testid="site-admin">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold" style="font-family: Fraunces, serif">Site público</h1>
        <p class="text-sm text-black/60 mt-1">
          Configure a vitrine da organização.
          <a :href="publicUrl" target="_blank" class="underline ml-1">Abrir site</a>
        </p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-black/10 pb-2">
      <button
        v-if="canSettings"
        type="button"
        class="px-3 py-1.5 text-sm rounded"
        :class="tab === 'settings' ? 'bg-[var(--color-primary)] text-white' : 'bg-black/5'"
        @click="setTab('settings')"
      >
        Configurações
      </button>
      <button
        v-if="canPages"
        type="button"
        class="px-3 py-1.5 text-sm rounded"
        :class="tab === 'pages' ? 'bg-[var(--color-primary)] text-white' : 'bg-black/5'"
        @click="setTab('pages')"
      >
        Páginas
      </button>
      <button
        v-if="canCom"
        type="button"
        class="px-3 py-1.5 text-sm rounded"
        :class="tab === 'comunicados' ? 'bg-[var(--color-primary)] text-white' : 'bg-black/5'"
        @click="setTab('comunicados')"
      >
        Comunicados
      </button>
      <button
        v-if="canPast"
        type="button"
        class="px-3 py-1.5 text-sm rounded"
        :class="tab === 'pastorais' ? 'bg-[var(--color-primary)] text-white' : 'bg-black/5'"
        @click="setTab('pastorais')"
      >
        Pastorais
      </button>
      <button
        v-if="canForms"
        type="button"
        class="px-3 py-1.5 text-sm rounded"
        :class="tab === 'forms' ? 'bg-[var(--color-primary)] text-white' : 'bg-black/5'"
        @click="setTab('forms')"
      >
        Formulários
      </button>
    </div>

    <p v-if="loading" class="text-black/50">Carregando…</p>

    <!-- Settings -->
    <div v-else-if="tab === 'settings'" class="space-y-4 max-w-2xl">
      <label class="flex items-center gap-2 text-sm">
        <input v-model="settings.publicado" type="checkbox" />
        Site publicado
      </label>
      <div>
        <label class="block text-sm mb-1">Título</label>
        <input v-model="settings.titulo" class="w-full border px-3 py-2 rounded" />
      </div>
      <div>
        <label class="block text-sm mb-1">Subtítulo</label>
        <input v-model="settings.subtitulo" class="w-full border px-3 py-2 rounded" />
      </div>
      <div>
        <label class="block text-sm mb-1">Menu (JSON)</label>
        <textarea v-model="menuJson" class="w-full border px-3 py-2 rounded font-mono text-xs" rows="5" />
        <p class="text-xs text-black/50 mt-1">Ex.: [{"label":"Início","slug":"home"},{"label":"Sobre","slug":"sobre"}]</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 bg-[var(--color-primary)] text-white rounded disabled:opacity-50"
        :disabled="saving || !can('site.settings.update')"
        @click="saveSettings"
      >
        Salvar
      </button>
    </div>

    <!-- Pages -->
    <div v-else-if="tab === 'pages'" class="space-y-4">
      <div v-if="!pageForm" class="space-y-3">
        <button
          v-if="can('site.pages.manage')"
          type="button"
          class="px-3 py-1.5 text-sm bg-[var(--color-primary)] text-white rounded"
          @click="openNewPage"
        >
          Nova página
        </button>
        <ul class="divide-y border rounded bg-white">
          <li v-for="p in pages" :key="p.id" class="px-4 py-3 flex justify-between gap-3 items-center">
            <div>
              <div class="font-medium">{{ p.titulo }} <span class="text-xs text-black/50">/{{ p.slug }}</span></div>
              <div class="text-xs text-black/50">{{ p.status }} <span v-if="p.is_home">· home</span></div>
            </div>
            <div class="flex gap-2 text-sm">
              <button type="button" class="underline" @click="openEditPage(p)">Editar</button>
              <button
                v-if="can('site.pages.manage')"
                type="button"
                class="text-red-700 underline"
                @click="removePage(p)"
              >
                Remover
              </button>
            </div>
          </li>
        </ul>
      </div>
      <div v-else class="space-y-3 max-w-3xl">
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm mb-1">Título</label>
            <input v-model="pageForm.titulo" class="w-full border px-3 py-2 rounded" />
          </div>
          <div>
            <label class="block text-sm mb-1">Slug</label>
            <input v-model="pageForm.slug" class="w-full border px-3 py-2 rounded" />
          </div>
          <div>
            <label class="block text-sm mb-1">Status</label>
            <select v-model="pageForm.status" class="w-full border px-3 py-2 rounded">
              <option value="rascunho">Rascunho</option>
              <option value="publicado">Publicado</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm mt-6">
            <input v-model="pageForm.is_home" type="checkbox" />
            Página inicial
          </label>
        </div>
        <div class="space-y-3">
          <div class="flex justify-between items-center">
            <h3 class="font-medium">Blocos</h3>
            <button type="button" class="text-sm underline" @click="addBlock">+ bloco</button>
          </div>
          <div
            v-for="(b, idx) in pageForm.blocks"
            :key="idx"
            class="border rounded p-3 space-y-2 bg-white"
          >
            <div class="flex gap-2 items-center">
              <select v-model="b.tipo" class="border px-2 py-1 rounded text-sm">
                <option v-for="t in BLOCK_TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
              <button type="button" class="text-xs text-red-700" @click="pageForm.blocks.splice(idx, 1)">
                remover
              </button>
            </div>
            <template v-if="b.tipo === 'hero'">
              <input v-model="b.payload.headline" placeholder="Headline" class="w-full border px-2 py-1 rounded text-sm" />
              <input v-model="b.payload.texto" placeholder="Texto" class="w-full border px-2 py-1 rounded text-sm" />
              <input v-model="b.payload.banner_url" placeholder="URL do banner" class="w-full border px-2 py-1 rounded text-sm" />
              <input v-model="b.payload.cta_label" placeholder="CTA label" class="w-full border px-2 py-1 rounded text-sm" />
              <input v-model="b.payload.cta_href" placeholder="CTA href" class="w-full border px-2 py-1 rounded text-sm" />
            </template>
            <template v-else-if="b.tipo === 'banner'">
              <input v-model="b.payload.image_url" placeholder="URL da imagem" class="w-full border px-2 py-1 rounded text-sm" />
            </template>
            <template v-else-if="b.tipo === 'richtext' || b.tipo === 'html'">
              <textarea v-model="b.payload.html" rows="4" class="w-full border px-2 py-1 rounded text-sm font-mono" />
            </template>
            <template v-else-if="b.tipo === 'form'">
              <input v-model="b.payload.form_slug" placeholder="Slug do formulário" class="w-full border px-2 py-1 rounded text-sm" />
              <input v-model="b.payload.titulo" placeholder="Título" class="w-full border px-2 py-1 rounded text-sm" />
            </template>
            <template v-else>
              <input v-model="b.payload.titulo" placeholder="Título da seção" class="w-full border px-2 py-1 rounded text-sm" />
            </template>
          </div>
        </div>
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 bg-[var(--color-primary)] text-white rounded" :disabled="saving" @click="savePage">
            Salvar página
          </button>
          <button type="button" class="px-4 py-2 border rounded" @click="pageForm = null">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Comunicados -->
    <div v-else-if="tab === 'comunicados'" class="space-y-4">
      <div v-if="!comForm">
        <button
          v-if="can('site.comunicados.manage')"
          type="button"
          class="px-3 py-1.5 text-sm bg-[var(--color-primary)] text-white rounded mb-3"
          @click="openNewCom"
        >
          Novo comunicado
        </button>
        <ul class="divide-y border rounded bg-white">
          <li v-for="c in comunicados" :key="c.id" class="px-4 py-3 flex justify-between">
            <div>
              <div class="font-medium">{{ c.titulo }}</div>
              <div class="text-xs text-black/50">{{ c.status }}</div>
            </div>
            <div class="flex gap-2 text-sm">
              <button type="button" class="underline" @click="comForm = { ...c }">Editar</button>
              <button type="button" class="text-red-700 underline" @click="removeCom(c)">Remover</button>
            </div>
          </li>
        </ul>
      </div>
      <div v-else class="space-y-3 max-w-2xl">
        <input v-model="comForm.titulo" placeholder="Título" class="w-full border px-3 py-2 rounded" />
        <input v-model="comForm.resumo" placeholder="Resumo" class="w-full border px-3 py-2 rounded" />
        <textarea v-model="comForm.corpo" rows="6" placeholder="Corpo" class="w-full border px-3 py-2 rounded" />
        <select v-model="comForm.status" class="border px-3 py-2 rounded">
          <option value="rascunho">Rascunho</option>
          <option value="publicado">Publicado</option>
        </select>
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 bg-[var(--color-primary)] text-white rounded" @click="saveCom">Salvar</button>
          <button type="button" class="px-4 py-2 border rounded" @click="comForm = null">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Pastorais -->
    <div v-else-if="tab === 'pastorais'" class="space-y-4">
      <div v-if="!pastForm">
        <button
          v-if="can('site.pastorais.manage')"
          type="button"
          class="px-3 py-1.5 text-sm bg-[var(--color-primary)] text-white rounded mb-3"
          @click="openNewPast"
        >
          Nova pastoral
        </button>
        <ul class="divide-y border rounded bg-white">
          <li v-for="p in pastorais" :key="p.id" class="px-4 py-3 flex justify-between">
            <div>
              <div class="font-medium">{{ p.nome }}</div>
              <div class="text-xs text-black/50">{{ p.igreja_nome || p.igreja_id }}</div>
            </div>
            <div class="flex gap-2 text-sm">
              <button type="button" class="underline" @click="pastForm = { ...p }">Editar</button>
              <button type="button" class="text-red-700 underline" @click="removePast(p)">Remover</button>
            </div>
          </li>
        </ul>
      </div>
      <div v-else class="space-y-3 max-w-2xl">
        <select v-model="pastForm.igreja_id" class="w-full border px-3 py-2 rounded">
          <option v-for="i in igrejas" :key="i.id" :value="i.id">{{ i.nome }}</option>
        </select>
        <input v-model="pastForm.nome" placeholder="Nome" class="w-full border px-3 py-2 rounded" />
        <textarea v-model="pastForm.descricao_publica" rows="3" placeholder="Descrição pública" class="w-full border px-3 py-2 rounded" />
        <input v-model="pastForm.contato_publico" placeholder="Contato público" class="w-full border px-3 py-2 rounded" />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="pastForm.publicado_no_site" type="checkbox" />
          Publicar no site
        </label>
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 bg-[var(--color-primary)] text-white rounded" @click="savePast">Salvar</button>
          <button type="button" class="px-4 py-2 border rounded" @click="pastForm = null">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Forms -->
    <div v-else-if="tab === 'forms'" class="space-y-4">
      <div v-if="!formForm">
        <button
          v-if="can('site.forms.manage')"
          type="button"
          class="px-3 py-1.5 text-sm bg-[var(--color-primary)] text-white rounded mb-3"
          @click="openNewForm"
        >
          Novo formulário
        </button>
        <ul class="divide-y border rounded bg-white">
          <li v-for="f in forms" :key="f.id" class="px-4 py-3 flex justify-between">
            <div>
              <div class="font-medium">{{ f.nome }} <span class="text-xs text-black/50">/{{ f.slug }}</span></div>
            </div>
            <div class="flex gap-2 text-sm">
              <button type="button" class="underline" @click="formForm = { ...f, fields: f.fields || [] }">Editar</button>
              <button
                v-if="can('site.forms.submissions.view')"
                type="button"
                class="underline"
                @click="loadSubmissions(f.id)"
              >
                Respostas
              </button>
            </div>
          </li>
        </ul>
        <div v-if="submissions.length" class="mt-4 border rounded bg-white p-4">
          <h3 class="font-medium mb-2">Respostas</h3>
          <pre class="text-xs overflow-auto">{{ submissions }}</pre>
        </div>
      </div>
      <div v-else class="space-y-3 max-w-2xl">
        <input v-model="formForm.nome" placeholder="Nome" class="w-full border px-3 py-2 rounded" />
        <input v-model="formForm.slug" placeholder="Slug" class="w-full border px-3 py-2 rounded" />
        <textarea v-model="formForm.descricao" rows="2" placeholder="Descrição" class="w-full border px-3 py-2 rounded" />
        <input v-model="formForm.sucesso_mensagem" placeholder="Mensagem de sucesso" class="w-full border px-3 py-2 rounded" />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="formForm.ativo" type="checkbox" />
          Ativo
        </label>
        <p class="text-sm text-black/60">Campos: {{ formForm.fields?.length || 0 }} (edição avançada via JSON na API nesta versão)</p>
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 bg-[var(--color-primary)] text-white rounded" @click="saveFormDef">Salvar</button>
          <button type="button" class="px-4 py-2 border rounded" @click="formForm = null">Cancelar</button>
        </div>
      </div>
    </div>
  </div>
</template>
