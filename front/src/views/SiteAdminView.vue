<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faArrowUpRightFromSquare,
  faCircleInfo,
  faPlus,
  faTrash,
} from '@fortawesome/free-solid-svg-icons'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import {
  BLOCK_LIBRARY,
  blockMeta,
  blockSummary,
  createBlock,
  menuItemsToPayload,
  normalizeMenuItems,
  reindexBlocks,
} from '@/utils/siteBlocks'
import { normalizeFields, validateFields } from '@/utils/siteForms'
import SiteBlockEditor from '@/components/site/admin/SiteBlockEditor.vue'
import SiteBlockIcon from '@/components/site/admin/SiteBlockIcon.vue'
import SiteFormFieldsEditor from '@/components/site/admin/SiteFormFieldsEditor.vue'
import SiteMenuEditor from '@/components/site/admin/SiteMenuEditor.vue'
import SitePagePreview from '@/components/site/admin/SitePagePreview.vue'

library.add(faArrowUpRightFromSquare, faCircleInfo, faPlus, faTrash)

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
const menuItems = ref([])

const pages = ref([])
const pageForm = ref(null)
const selectedBlock = ref(0)
const newBlockType = ref('richtext')
const pagePane = ref('editor')

const comunicados = ref([])
const comForm = ref(null)
const pastorais = ref([])
const pastForm = ref(null)
const forms = ref([])
const formForm = ref(null)
const formErrors = ref([])
const submissions = ref([])
const submissionsForm = ref(null)
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

const tabs = computed(() =>
  [
    { id: 'settings', label: 'Configurações', visible: canSettings.value },
    { id: 'pages', label: 'Páginas', visible: canPages.value },
    { id: 'comunicados', label: 'Comunicados', visible: canCom.value },
    { id: 'pastorais', label: 'Pastorais', visible: canPast.value },
    { id: 'forms', label: 'Formulários', visible: canForms.value },
  ].filter((t) => t.visible),
)

const publicUrl = computed(() => (tenantStore.slug ? `/site/${tenantStore.slug}` : '#'))

const blockGroups = computed(() => {
  const groups = new Map()
  for (const block of BLOCK_LIBRARY) {
    if (!groups.has(block.grupo)) groups.set(block.grupo, [])
    groups.get(block.grupo).push(block)
  }
  return [...groups.entries()].map(([grupo, itens]) => ({ grupo, itens }))
})

const currentBlock = computed(() => pageForm.value?.blocks?.[selectedBlock.value] || null)

const submissionColumns = computed(() => {
  const keys = new Set()
  for (const item of submissions.value) {
    for (const key of Object.keys(item.values || {})) keys.add(key)
  }
  return [...keys]
})

const ensureTenant = () => {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/inicio')
    return false
  }
  return true
}

/** A API devolve `[]` para os JSONs vazios; o editor precisa de objetos. */
const asObject = (value) => (value && !Array.isArray(value) && typeof value === 'object' ? value : {})

const loadSettings = async () => {
  const { data } = await api.get('/site/settings')
  const loaded = data.data || data || {}
  settings.value = {
    ...settings.value,
    ...loaded,
    seo: asObject(loaded.seo),
    cores: asObject(loaded.cores),
    contato: asObject(loaded.contato),
  }
  menuItems.value = normalizeMenuItems(settings.value.menu)
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

/** Carregamentos auxiliares (selects, prévia) não devem quebrar a aba. */
const loadOptional = (tasks) => Promise.allSettled(tasks.map((task) => task()))

const loadTab = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    if (tab.value === 'settings' && canSettings.value) {
      await loadSettings()
      await loadOptional([loadPages])
    }
    if (tab.value === 'pages' && canPages.value) {
      await loadPages()
      await loadOptional([loadForms])
    }
    if (tab.value === 'comunicados' && canCom.value) {
      await loadComunicados()
      await loadOptional([loadIgrejas])
    }
    if (tab.value === 'pastorais' && canPast.value) {
      await loadPastorais()
      await loadOptional([loadIgrejas])
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
      menu: menuItemsToPayload(menuItems.value),
      seo: settings.value.seo,
      cores: settings.value.cores,
      contato: settings.value.contato,
    })
    const saved = data.data || data || {}
    settings.value = {
      ...settings.value,
      ...saved,
      seo: asObject(saved.seo),
      cores: asObject(saved.cores),
      contato: asObject(saved.contato),
    }
    menuItems.value = normalizeMenuItems(settings.value.menu)
    innovToast('success', 'Site', 'Configurações salvas')
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
    blocks: [createBlock('hero', 0)],
  }
  selectedBlock.value = 0
  pagePane.value = 'editor'
}

const openEditPage = (p) => {
  pageForm.value = {
    ...p,
    blocks: reindexBlocks(
      [...(p.blocks || [])]
        .sort((a, b) => (Number(a.ordem) || 0) - (Number(b.ordem) || 0))
        .map((b) => ({
          tipo: b.tipo,
          visivel: b.visivel !== false,
          payload: { ...blockMeta(b.tipo).payload, ...(b.payload || {}) },
        })),
    ),
  }
  selectedBlock.value = 0
  pagePane.value = 'editor'
}

const selectBlock = (index) => {
  selectedBlock.value = index
  pagePane.value = 'editor'
}

const addBlock = () => {
  pageForm.value.blocks.push(createBlock(newBlockType.value, pageForm.value.blocks.length))
  selectedBlock.value = pageForm.value.blocks.length - 1
  pagePane.value = 'editor'
}

const removeBlock = (index) => {
  pageForm.value.blocks = reindexBlocks(
    pageForm.value.blocks.filter((_, i) => i !== index),
  )
  selectedBlock.value = Math.max(0, Math.min(selectedBlock.value, pageForm.value.blocks.length - 1))
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
      blocks: reindexBlocks(pageForm.value.blocks),
    }
    if (pageForm.value.id) {
      await api.put(`/site/pages/${pageForm.value.id}`, body)
    } else {
      await api.post('/site/pages', body)
    }
    innovToast('success', 'Site', 'Página salva')
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
    const body = {
      titulo: comForm.value.titulo,
      resumo: comForm.value.resumo || null,
      corpo: comForm.value.corpo,
      status: comForm.value.status,
      destaque: !!comForm.value.destaque,
      igreja_id: comForm.value.igreja_id || null,
    }
    if (comForm.value.id) {
      await api.put(`/site/comunicados/${comForm.value.id}`, body)
    } else {
      await api.post('/site/comunicados', body)
    }
    innovToast('success', 'Site', 'Comunicado salvo')
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
    innovToast('success', 'Site', 'Pastoral salva')
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
  formErrors.value = []
  formForm.value = {
    id: null,
    nome: '',
    slug: '',
    descricao: '',
    ativo: true,
    sucesso_mensagem: 'Obrigado pelo contato!',
    fields: normalizeFields([
      { label: 'Nome', tipo: 'text', obrigatorio: true },
      { label: 'E-mail', tipo: 'email', obrigatorio: true },
      { label: 'Mensagem', tipo: 'textarea', obrigatorio: true },
    ]),
  }
}

const openEditForm = (f) => {
  formErrors.value = []
  formForm.value = { ...f, fields: normalizeFields(f.fields || []) }
}

const saveFormDef = async () => {
  const fields = normalizeFields(formForm.value.fields)
  formErrors.value = validateFields(fields)
  if (formErrors.value.length) return

  saving.value = true
  try {
    const body = {
      nome: formForm.value.nome,
      slug: formForm.value.slug,
      descricao: formForm.value.descricao || null,
      ativo: formForm.value.ativo,
      sucesso_mensagem: formForm.value.sucesso_mensagem,
      fields,
    }
    if (formForm.value.id) {
      await api.put(`/site/forms/${formForm.value.id}`, body)
    } else {
      await api.post('/site/forms', body)
    }
    innovToast('success', 'Site', 'Formulário salvo')
    formForm.value = null
    await loadForms()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const loadSubmissions = async (form) => {
  submissionsForm.value = form
  const { data } = await api.get(`/site/forms/${form.id}/submissions`)
  submissions.value = data.data || data || []
}

const closeSubmissions = () => {
  submissionsForm.value = null
  submissions.value = []
}

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('pt-BR')
}

watch(
  () => formForm.value?.nome,
  (nome) => {
    if (!formForm.value || formForm.value.id || !nome) return
    if (!formForm.value.slug) {
      formForm.value.slug = nome
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
    }
  },
)

onMounted(async () => {
  tab.value = tabs.value[0]?.id || 'settings'
  // O selo de publicação e a prévia dependem das configurações em qualquer aba.
  if (canSettings.value) await loadOptional([loadSettings])
  await loadTab()
})

const setTab = async (t) => {
  tab.value = t
  pageForm.value = null
  comForm.value = null
  pastForm.value = null
  formForm.value = null
  formErrors.value = []
  submissions.value = []
  submissionsForm.value = null
  await loadTab()
}
</script>

<template>
  <div data-testid="site-admin">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="page-eyebrow">Presença digital</p>
        <h2 class="text-3xl" style="color: var(--color-primary)">Site público</h2>
        <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
          Monte as páginas, o conteúdo e os formulários da vitrine da organização.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <span
          class="badge"
          :class="settings.publicado ? 'badge-success' : 'badge-warning'"
          data-testid="site-status"
        >
          {{ settings.publicado ? 'Publicado' : 'Rascunho' }}
        </span>
        <a :href="publicUrl" target="_blank" rel="noopener" class="btn btn-ghost">
          <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
          Abrir site
        </a>
      </div>
    </div>

    <div class="card p-1.5 inline-flex flex-wrap gap-1 mb-6">
      <button
        v-for="t in tabs"
        :key="t.id"
        type="button"
        class="px-4 py-2 rounded-[11px] text-sm font-semibold transition-colors"
        :style="tab === t.id
          ? 'background: var(--color-primary); color: #F7F4EE'
          : 'color: var(--color-muted)'"
        :data-testid="`site-tab-${t.id}`"
        @click="setTab(t.id)"
      >
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="card p-8 text-center" style="color: var(--color-muted)">
      Carregando…
    </div>

    <!-- Configurações -->
    <div v-else-if="tab === 'settings'" class="space-y-5">
      <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-xl">Publicação</h3>
            <p class="mt-1 text-sm" style="color: var(--color-muted)">
              Enquanto estiver em rascunho, o endereço público responde como indisponível.
            </p>
          </div>
          <label class="flex items-center gap-2.5 text-sm font-semibold">
            <input v-model="settings.publicado" type="checkbox" data-testid="site-publicado" />
            Site publicado
          </label>
        </div>
        <p class="mt-4 text-sm font-mono rounded-xl px-3.5 py-2.5" style="background: var(--color-surface-2); color: var(--color-muted)">
          {{ publicUrl }}
        </p>
      </div>

      <div class="card p-6 space-y-4">
        <h3 class="text-xl">Identidade</h3>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="fld" for="st-titulo">Título do site</label>
            <input id="st-titulo" v-model="settings.titulo" class="input" data-testid="site-titulo" />
          </div>
          <div>
            <label class="fld" for="st-subtitulo">Subtítulo</label>
            <input id="st-subtitulo" v-model="settings.subtitulo" class="input" />
          </div>
          <div>
            <label class="fld" for="st-cor">Cor principal</label>
            <div class="flex items-center gap-3">
              <input
                id="st-cor"
                v-model="settings.cores.primary"
                type="color"
                class="h-11 w-14 rounded-[11px] cursor-pointer"
                style="border: 1px solid var(--color-line)"
              />
              <input v-model="settings.cores.primary" class="input" placeholder="#00234E" />
            </div>
          </div>
        </div>
      </div>

      <div class="card p-6 space-y-4">
        <div>
          <h3 class="text-xl">Menu de navegação</h3>
          <p class="mt-1 text-sm" style="color: var(--color-muted)">
            Itens exibidos no topo do site, na ordem definida abaixo.
          </p>
        </div>
        <SiteMenuEditor v-model="menuItems" :pages="pages" />
      </div>

      <div class="card p-6 space-y-4">
        <h3 class="text-xl">Busca e contato</h3>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="fld" for="st-seo-title">Título para buscadores</label>
            <input id="st-seo-title" v-model="settings.seo.title" class="input" />
          </div>
          <div>
            <label class="fld" for="st-seo-desc">Descrição para buscadores</label>
            <input id="st-seo-desc" v-model="settings.seo.description" class="input" />
          </div>
          <div>
            <label class="fld" for="st-contato-email">E-mail de contato</label>
            <input id="st-contato-email" v-model="settings.contato.email" type="email" class="input" />
          </div>
          <div>
            <label class="fld" for="st-contato-tel">Telefone</label>
            <input id="st-contato-tel" v-model="settings.contato.telefone" class="input" />
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          class="btn btn-primary disabled:opacity-50"
          :disabled="saving || !can('site.settings.update')"
          data-testid="site-salvar-settings"
          @click="saveSettings"
        >
          {{ saving ? 'Salvando…' : 'Salvar configurações' }}
        </button>
      </div>
    </div>

    <!-- Páginas -->
    <div v-else-if="tab === 'pages'">
      <div v-if="!pageForm" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm" style="color: var(--color-muted)">
            {{ pages.length }} página(s) no site.
          </p>
          <button
            v-if="can('site.pages.manage')"
            type="button"
            class="btn btn-primary"
            data-testid="site-nova-pagina"
            @click="openNewPage"
          >
            <FontAwesomeIcon :icon="faPlus" />
            Nova página
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-if="!pages.length"
            class="card p-8 sm:col-span-2 text-center"
            style="color: var(--color-muted)"
          >
            Nenhuma página criada. Comece pela página inicial.
          </div>
          <div v-for="p in pages" :key="p.id" class="card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-lg leading-tight">{{ p.titulo }}</h3>
                <p class="text-xs font-mono mt-1" style="color: var(--color-muted)">/{{ p.slug }}</p>
              </div>
              <span class="badge" :class="p.status === 'publicado' ? 'badge-success' : 'badge-warning'">
                {{ p.status === 'publicado' ? 'Publicada' : 'Rascunho' }}
              </span>
            </div>
            <p class="mt-3 text-sm" style="color: var(--color-muted)">
              {{ (p.blocks || []).length }} bloco(s)
              <span v-if="p.is_home"> · página inicial</span>
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <button class="btn btn-ghost" data-testid="pagina-editar" @click="openEditPage(p)">
                Editar
              </button>
              <button
                v-if="can('site.pages.manage')"
                class="btn btn-ghost"
                style="color: var(--color-danger)"
                @click="removePage(p)"
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="space-y-4" data-testid="site-page-builder">
        <div class="card px-5 py-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-muted)">
              Construtor de página
            </p>
            <h3 class="text-lg leading-tight">
              {{ pageForm.titulo || (pageForm.id ? 'Página sem título' : 'Nova página') }}
            </h3>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-ghost" @click="pageForm = null">Cancelar</button>
            <button
              type="button"
              class="btn btn-primary disabled:opacity-50"
              :disabled="saving"
              data-testid="pagina-salvar"
              @click="savePage"
            >
              {{ saving ? 'Salvando…' : 'Salvar página' }}
            </button>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-12 items-start">
          <div class="lg:col-span-4 space-y-4">
            <div class="card p-5 space-y-4">
              <h4 class="text-base">Dados da página</h4>
              <div>
                <label class="fld" for="pg-titulo">Título</label>
                <input id="pg-titulo" v-model="pageForm.titulo" class="input" data-testid="pagina-titulo" />
              </div>
              <div>
                <label class="fld" for="pg-slug">Endereço (slug)</label>
                <input id="pg-slug" v-model="pageForm.slug" class="input" placeholder="sobre" />
              </div>
              <div>
                <label class="fld" for="pg-status">Situação</label>
                <select id="pg-status" v-model="pageForm.status" class="input">
                  <option value="rascunho">Rascunho</option>
                  <option value="publicado">Publicada</option>
                </select>
              </div>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="pageForm.is_home" type="checkbox" />
                Usar como página inicial
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="pageForm.mostrar_no_menu" type="checkbox" />
                Sugerir no menu
              </label>
            </div>

            <div class="card p-5 space-y-3">
              <h4 class="text-base">Blocos</h4>
              <ul class="space-y-2">
                <li v-for="(b, idx) in pageForm.blocks" :key="idx">
                  <div
                    class="rounded-xl p-3 flex items-start gap-3 cursor-pointer transition-colors"
                    :style="idx === selectedBlock
                      ? 'background: rgba(0,35,78,0.07); border: 1px solid var(--color-primary)'
                      : 'background: var(--color-surface-2); border: 1px solid transparent'"
                    :data-testid="`bloco-item-${idx}`"
                    @click="selectBlock(idx)"
                  >
                    <span class="mt-0.5" style="color: var(--color-primary)">
                      <SiteBlockIcon :tipo="b.tipo" />
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="text-sm font-semibold leading-tight">{{ blockMeta(b.tipo).label }}</p>
                      <p class="text-xs truncate mt-0.5" style="color: var(--color-muted)">
                        {{ blockSummary(b) }}
                      </p>
                    </div>
                    <button
                      type="button"
                      class="text-xs shrink-0 h-7 w-7 rounded-lg hover:bg-red-50"
                      style="color: var(--color-danger)"
                      aria-label="Remover bloco"
                      :data-testid="`bloco-remover-${idx}`"
                      @click.stop="removeBlock(idx)"
                    >
                      <FontAwesomeIcon :icon="faTrash" />
                    </button>
                  </div>
                </li>
              </ul>
              <p v-if="!pageForm.blocks.length" class="text-sm" style="color: var(--color-muted)">
                Página sem blocos. Adicione o primeiro abaixo.
              </p>

              <div class="pt-1 flex gap-2">
                <select v-model="newBlockType" class="input" aria-label="Tipo de bloco">
                  <optgroup v-for="g in blockGroups" :key="g.grupo" :label="g.grupo">
                    <option v-for="b in g.itens" :key="b.tipo" :value="b.tipo">{{ b.label }}</option>
                  </optgroup>
                </select>
                <button type="button" class="btn btn-ghost shrink-0" data-testid="bloco-adicionar" @click="addBlock">
                  <FontAwesomeIcon :icon="faPlus" />
                </button>
              </div>
            </div>
          </div>

          <div class="lg:col-span-8 card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
              <div class="inline-flex rounded-lg p-0.5" style="background: var(--color-surface-2)">
                <button
                  type="button"
                  class="px-3.5 py-1.5 rounded-md text-xs font-semibold"
                  :style="pagePane === 'editor'
                    ? 'background: var(--color-surface); color: var(--color-primary)'
                    : 'color: var(--color-muted)'"
                  data-testid="pane-editor"
                  @click="pagePane = 'editor'"
                >
                  Editar bloco
                </button>
                <button
                  type="button"
                  class="px-3.5 py-1.5 rounded-md text-xs font-semibold"
                  :style="pagePane === 'preview'
                    ? 'background: var(--color-surface); color: var(--color-primary)'
                    : 'color: var(--color-muted)'"
                  data-testid="pane-preview"
                  @click="pagePane = 'preview'"
                >
                  Pré-visualizar
                </button>
              </div>
              <p v-if="pagePane === 'editor' && currentBlock" class="text-xs" style="color: var(--color-muted)">
                Bloco {{ selectedBlock + 1 }} de {{ pageForm.blocks.length }}
              </p>
            </div>

            <SitePagePreview
              v-if="pagePane === 'preview'"
              :blocks="pageForm.blocks"
              :settings="settings"
              :tenant-slug="tenantStore.slug || ''"
              :page-title="pageForm.titulo"
            />
            <SiteBlockEditor
              v-else-if="currentBlock"
              :key="selectedBlock"
              :block="currentBlock"
              :forms="forms"
            />
            <p v-else class="py-12 text-center text-sm" style="color: var(--color-muted)">
              Selecione ou adicione um bloco para editar.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Comunicados -->
    <div v-else-if="tab === 'comunicados'" class="space-y-4">
      <div v-if="!comForm" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm" style="color: var(--color-muted)">
            {{ comunicados.length }} comunicado(s).
          </p>
          <button
            v-if="can('site.comunicados.manage')"
            type="button"
            class="btn btn-primary"
            data-testid="site-novo-comunicado"
            @click="openNewCom"
          >
            <FontAwesomeIcon :icon="faPlus" />
            Novo comunicado
          </button>
        </div>
        <div
          v-if="!comunicados.length"
          class="card p-8 text-center"
          style="color: var(--color-muted)"
        >
          Nenhum comunicado cadastrado.
        </div>
        <div v-else class="card divide-y" style="border-color: var(--color-line)">
          <div
            v-for="c in comunicados"
            :key="c.id"
            class="px-5 py-4 flex flex-wrap items-center justify-between gap-3"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-base leading-tight">{{ c.titulo }}</h3>
                <span class="badge" :class="c.status === 'publicado' ? 'badge-success' : 'badge-warning'">
                  {{ c.status === 'publicado' ? 'Publicado' : 'Rascunho' }}
                </span>
              </div>
              <p v-if="c.resumo" class="text-sm mt-1 truncate" style="color: var(--color-muted)">
                {{ c.resumo }}
              </p>
            </div>
            <div class="flex gap-2">
              <button class="btn btn-ghost" @click="comForm = { ...c }">Editar</button>
              <button class="btn btn-ghost" style="color: var(--color-danger)" @click="removeCom(c)">
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card p-6 max-w-2xl space-y-4">
        <h3 class="text-xl">{{ comForm.id ? 'Editar comunicado' : 'Novo comunicado' }}</h3>
        <div>
          <label class="fld" for="com-titulo">Título</label>
          <input id="com-titulo" v-model="comForm.titulo" class="input" data-testid="comunicado-titulo" />
        </div>
        <div>
          <label class="fld" for="com-resumo">Resumo</label>
          <input id="com-resumo" v-model="comForm.resumo" class="input" />
        </div>
        <div>
          <label class="fld" for="com-corpo">Conteúdo</label>
          <textarea id="com-corpo" v-model="comForm.corpo" rows="8" class="input" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="fld" for="com-status">Situação</label>
            <select id="com-status" v-model="comForm.status" class="input">
              <option value="rascunho">Rascunho</option>
              <option value="publicado">Publicado</option>
            </select>
          </div>
          <div>
            <label class="fld" for="com-igreja">Igreja (opcional)</label>
            <select id="com-igreja" v-model="comForm.igreja_id" class="input">
              <option :value="null">Toda a organização</option>
              <option v-for="i in igrejas" :key="i.id" :value="i.id">{{ i.nome }}</option>
            </select>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="comForm.destaque" type="checkbox" />
          Marcar como destaque
        </label>
        <div class="flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="saveCom">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
          <button type="button" class="btn btn-ghost" @click="comForm = null">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Pastorais -->
    <div v-else-if="tab === 'pastorais'" class="space-y-4">
      <div v-if="!pastForm" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm" style="color: var(--color-muted)">
            {{ pastorais.length }} pastoral(is).
          </p>
          <button
            v-if="can('site.pastorais.manage')"
            type="button"
            class="btn btn-primary"
            data-testid="site-nova-pastoral"
            @click="openNewPast"
          >
            <FontAwesomeIcon :icon="faPlus" />
            Nova pastoral
          </button>
        </div>
        <div
          v-if="!pastorais.length"
          class="card p-8 text-center"
          style="color: var(--color-muted)"
        >
          Nenhuma pastoral cadastrada.
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2">
          <div v-for="p in pastorais" :key="p.id" class="card p-5">
            <div class="flex items-start justify-between gap-3">
              <h3 class="text-lg leading-tight">{{ p.nome }}</h3>
              <span class="badge" :class="p.publicado_no_site ? 'badge-success' : 'badge-info'">
                {{ p.publicado_no_site ? 'No site' : 'Interna' }}
              </span>
            </div>
            <p class="text-sm mt-1" style="color: var(--color-muted)">
              {{ p.igreja_nome || igrejas.find((i) => i.id === p.igreja_id)?.nome || '—' }}
            </p>
            <div class="mt-4 flex gap-2">
              <button class="btn btn-ghost" @click="pastForm = { ...p }">Editar</button>
              <button class="btn btn-ghost" style="color: var(--color-danger)" @click="removePast(p)">
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card p-6 max-w-2xl space-y-4">
        <h3 class="text-xl">{{ pastForm.id ? 'Editar pastoral' : 'Nova pastoral' }}</h3>
        <div>
          <label class="fld" for="past-igreja">Igreja</label>
          <select id="past-igreja" v-model="pastForm.igreja_id" class="input">
            <option v-for="i in igrejas" :key="i.id" :value="i.id">{{ i.nome }}</option>
          </select>
        </div>
        <div>
          <label class="fld" for="past-nome">Nome</label>
          <input id="past-nome" v-model="pastForm.nome" class="input" data-testid="pastoral-nome" />
        </div>
        <div>
          <label class="fld" for="past-desc">Descrição pública</label>
          <textarea id="past-desc" v-model="pastForm.descricao_publica" rows="4" class="input" />
        </div>
        <div>
          <label class="fld" for="past-contato">Contato público</label>
          <input id="past-contato" v-model="pastForm.contato_publico" class="input" />
        </div>
        <div class="flex flex-wrap gap-5">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="pastForm.publicado_no_site" type="checkbox" />
            Exibir no site
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="pastForm.ativa" type="checkbox" />
            Pastoral ativa
          </label>
        </div>
        <div class="flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="savePast">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
          <button type="button" class="btn btn-ghost" @click="pastForm = null">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Formulários -->
    <div v-else-if="tab === 'forms'" class="space-y-4">
      <div v-if="!formForm" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm" style="color: var(--color-muted)">
            {{ forms.length }} formulário(s).
          </p>
          <button
            v-if="can('site.forms.manage')"
            type="button"
            class="btn btn-primary"
            data-testid="site-novo-formulario"
            @click="openNewForm"
          >
            <FontAwesomeIcon :icon="faPlus" />
            Novo formulário
          </button>
        </div>

        <div v-if="!forms.length" class="card p-8 text-center" style="color: var(--color-muted)">
          Nenhum formulário criado.
        </div>
        <div v-else class="card divide-y" style="border-color: var(--color-line)">
          <div
            v-for="f in forms"
            :key="f.id"
            class="px-5 py-4 flex flex-wrap items-center justify-between gap-3"
          >
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base leading-tight">{{ f.nome }}</h3>
                <span class="badge" :class="f.ativo ? 'badge-success' : 'badge-warning'">
                  {{ f.ativo ? 'Ativo' : 'Inativo' }}
                </span>
              </div>
              <p class="text-xs font-mono mt-1" style="color: var(--color-muted)">/{{ f.slug }}</p>
            </div>
            <div class="flex gap-2">
              <button class="btn btn-ghost" data-testid="formulario-editar" @click="openEditForm(f)">
                Editar
              </button>
              <button
                v-if="can('site.forms.submissions.view')"
                class="btn btn-ghost"
                @click="loadSubmissions(f)"
              >
                Respostas
              </button>
            </div>
          </div>
        </div>

        <div v-if="submissionsForm" class="card p-5" data-testid="site-submissions">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-lg">Respostas · {{ submissionsForm.nome }}</h3>
            <button class="btn btn-ghost" @click="closeSubmissions">Fechar</button>
          </div>
          <p v-if="!submissions.length" class="text-sm" style="color: var(--color-muted)">
            Nenhuma resposta recebida.
          </p>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left" style="color: var(--color-muted)">
                  <th class="py-2 pr-4 font-semibold whitespace-nowrap">Recebida em</th>
                  <th
                    v-for="col in submissionColumns"
                    :key="col"
                    class="py-2 pr-4 font-semibold whitespace-nowrap"
                  >
                    {{ col }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="s in submissions"
                  :key="s.id"
                  class="border-t"
                  style="border-color: var(--color-line)"
                >
                  <td class="py-2.5 pr-4 whitespace-nowrap">{{ formatDate(s.created_at) }}</td>
                  <td v-for="col in submissionColumns" :key="col" class="py-2.5 pr-4">
                    {{ s.values?.[col] ?? '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else class="grid gap-4 lg:grid-cols-12 items-start">
        <div class="lg:col-span-5 card p-5 space-y-4">
          <h3 class="text-xl">{{ formForm.id ? 'Editar formulário' : 'Novo formulário' }}</h3>
          <div>
            <label class="fld" for="fm-nome">Nome</label>
            <input id="fm-nome" v-model="formForm.nome" class="input" data-testid="formulario-nome" />
          </div>
          <div>
            <label class="fld" for="fm-slug">Endereço (slug)</label>
            <input id="fm-slug" v-model="formForm.slug" class="input" placeholder="contato" />
          </div>
          <div>
            <label class="fld" for="fm-desc">Descrição</label>
            <textarea id="fm-desc" v-model="formForm.descricao" rows="3" class="input" />
          </div>
          <div>
            <label class="fld" for="fm-sucesso">Mensagem após o envio</label>
            <input id="fm-sucesso" v-model="formForm.sucesso_mensagem" class="input" />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="formForm.ativo" type="checkbox" />
            Formulário ativo
          </label>
          <p
            class="text-[13px] rounded-xl px-3.5 py-3 flex gap-2"
            style="background: var(--color-surface-2); color: var(--color-muted)"
          >
            <FontAwesomeIcon :icon="faCircleInfo" class="mt-0.5 shrink-0" />
            As respostas são armazenadas criptografadas e só aparecem para quem tem permissão de
            visualizá-las.
          </p>
        </div>

        <div class="lg:col-span-7 card p-5 space-y-4">
          <div>
            <h3 class="text-xl">Campos</h3>
            <p class="mt-1 text-sm" style="color: var(--color-muted)">
              Definem o que o visitante preenche no site.
            </p>
          </div>

          <ul
            v-if="formErrors.length"
            class="rounded-xl px-4 py-3 text-sm space-y-1"
            style="background: rgba(162, 58, 58, 0.08); color: var(--color-danger)"
            data-testid="formulario-erros"
          >
            <li v-for="(err, i) in formErrors" :key="i">{{ err }}</li>
          </ul>

          <SiteFormFieldsEditor v-model="formForm.fields" />

          <div class="flex gap-2 pt-2">
            <button
              type="button"
              class="btn btn-primary"
              :disabled="saving"
              data-testid="formulario-salvar"
              @click="saveFormDef"
            >
              {{ saving ? 'Salvando…' : 'Salvar formulário' }}
            </button>
            <button type="button" class="btn btn-ghost" @click="formForm = null">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
