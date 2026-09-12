<script setup>
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
  createHomeOnePagerBlocks,
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
const route = useRoute()
const tenantStore = useTenantStore()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()

const tab = ref('pages')
const loading = ref(false)
const saving = ref(false)
const previewMode = ref('desktop')
const dirty = ref(false)
const lastSavedAt = ref(null)
const showSectionPicker = ref(false)
const dragFrom = ref(null)

const settings = ref({
  publicado: false,
  titulo: '',
  subtitulo: '',
  menu: [],
  seo: {},
  contato: {},
})
const menuItems = ref([])

const pages = ref([])
const pageForm = ref(null)
const selectedBlock = ref(0)
const newBlockType = ref('richtext')
const comOverlay = ref(false)

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
      await loadOptional([loadForms, loadComunicados, loadSettings])
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
      contato: settings.value.contato,
    })
    const saved = data.data || data || {}
    settings.value = {
      ...settings.value,
      ...saved,
      seo: asObject(saved.seo),
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
    slug: 'home',
    titulo: 'Página inicial',
    status: 'rascunho',
    is_home: true,
    mostrar_no_menu: true,
    blocks: createHomeOnePagerBlocks(),
  }
  selectedBlock.value = 0
  dirty.value = true
}

const openEditPage = async (p) => {
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
  dirty.value = false
  showSectionPicker.value = false
  await loadOptional([loadComunicados, loadForms])
}

const selectBlock = async (index, { fromPreview = false } = {}) => {
  selectedBlock.value = index
  await nextTick()
  const el = document.querySelector(`[data-testid="bloco-item-${index}"]`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: fromPreview ? 'center' : 'nearest' })
  }
}

const toggleBlock = (index) => {
  if (selectedBlock.value === index) {
    selectedBlock.value = -1
    return
  }
  selectBlock(index)
}

const addBlock = (tipo = newBlockType.value) => {
  pageForm.value.blocks.push(createBlock(tipo, pageForm.value.blocks.length))
  selectedBlock.value = pageForm.value.blocks.length - 1
  dirty.value = true
  showSectionPicker.value = false
}

const onDragStart = (idx) => {
  dragFrom.value = idx
}

const onDrop = (idx) => {
  const from = dragFrom.value
  dragFrom.value = null
  if (from === null || from === idx || !pageForm.value) return
  const list = [...pageForm.value.blocks]
  const [item] = list.splice(from, 1)
  list.splice(idx, 0, item)
  pageForm.value.blocks = reindexBlocks(list)
  selectedBlock.value = idx
  dirty.value = true
}

const savedLabel = computed(() => {
  if (dirty.value) return 'alterações pendentes'
  if (!lastSavedAt.value) return 'salvo automaticamente'
  const t = lastSavedAt.value
  const hh = String(t.getHours()).padStart(2, '0')
  const mm = String(t.getMinutes()).padStart(2, '0')
  return `salvo automaticamente às ${hh}:${mm}`
})

const openNewComFromBlock = () => {
  openNewCom()
  comOverlay.value = true
}

const openEditComFromBlock = (c) => {
  comForm.value = { ...c }
  comOverlay.value = true
}

const closeComOverlay = () => {
  comForm.value = null
  comOverlay.value = false
}

const removeBlock = (index) => {
  pageForm.value.blocks = reindexBlocks(
    pageForm.value.blocks.filter((_, i) => i !== index),
  )
  selectedBlock.value = Math.max(0, Math.min(selectedBlock.value, pageForm.value.blocks.length - 1))
  dirty.value = true
}

const toggleBlockVisible = (index) => {
  const block = pageForm.value.blocks[index]
  if (!block) return
  block.visivel = block.visivel === false
  dirty.value = true
}

const publishPage = async () => {
  if (!pageForm.value) return
  pageForm.value.status = 'publicado'
  saving.value = true
  try {
    // Publicar a página E o site (settings.publicado) — o público exige os dois
    if (!settings.value.publicado) {
      if (!settings.value.titulo) {
        settings.value.titulo = pageForm.value.titulo || 'Site'
      }
      await api.put('/site/settings', {
        publicado: true,
        titulo: settings.value.titulo,
        subtitulo: settings.value.subtitulo,
        menu: menuItemsToPayload(menuItems.value),
        seo: settings.value.seo,
        contato: settings.value.contato,
      })
      settings.value.publicado = true
    }

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
    innovToast('success', 'Site', 'Publicado — o site está no ar')
    dirty.value = false
    lastSavedAt.value = new Date()
    await loadPages()
    const saved = pages.value.find((p) => p.slug === pageForm.value.slug || p.id === pageForm.value.id)
    if (saved) await openEditPage(saved)
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao publicar')
  } finally {
    saving.value = false
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
      blocks: reindexBlocks(pageForm.value.blocks),
    }
    if (pageForm.value.id) {
      await api.put(`/site/pages/${pageForm.value.id}`, body)
    } else {
      await api.post('/site/pages', body)
    }
    innovToast('success', 'Site', 'Página salva')
    dirty.value = false
    lastSavedAt.value = new Date()
    await loadPages()
    const saved = pages.value.find((p) => p.slug === pageForm.value.slug || p.id === pageForm.value.id)
    if (saved) await openEditPage(saved)
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
    closeComOverlay()
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

const enterPageBuilder = async () => {
  if (tab.value !== 'pages') return
  if (pageForm.value) return
  if (pages.value.length) {
    const home = pages.value.find((p) => p.is_home) || pages.value[0]
    await openEditPage(home)
    return
  }
  // Sem páginas: entra direto no editor 2a com o one-pager (não na lista Innov)
  openNewPage()
  await loadOptional([loadComunicados, loadForms])
}

onMounted(async () => {
  applyTabFromRoute()
  if (canSettings.value) await loadOptional([loadSettings])
  await loadTab()
  await enterPageBuilder()
})

const applyTabFromRoute = () => {
  const q = route.query.tab ? String(route.query.tab) : 'pages'
  const allowed = tabs.value.map((t) => t.id)
  const next = allowed.includes(q) ? q : (allowed[0] || 'pages')
  tab.value = next
}

watch(
  () => route.query.tab,
  async () => {
    const prev = tab.value
    applyTabFromRoute()
    if (tab.value !== prev) {
      pageForm.value = null
      comForm.value = null
      comOverlay.value = false
      pastForm.value = null
      formForm.value = null
      formErrors.value = []
      submissions.value = []
      submissionsForm.value = null
      await loadTab()
      await enterPageBuilder()
    }
  },
)

const setTab = async (t) => {
  const query = t === 'pages' ? {} : { tab: t }
  await router.replace({ path: '/site', query })
}

/** Páginas = só o builder 2a (sem chrome Innov). */
const showLegacyChrome = computed(() => tab.value !== 'pages')
</script>

<template>
  <div data-testid="site-admin">
    <template v-if="showLegacyChrome">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 px-6 pt-6">
      <div>
        <p class="page-eyebrow">Site</p>
        <h2 class="font-serif text-[28px]" style="color: #2A1418">
          {{ tabs.find((t) => t.id === tab)?.label || 'Site' }}
        </h2>
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
    </template>

    <div v-if="loading" class="card p-8 text-center mx-6" style="color: var(--color-muted)">
      Carregando…
    </div>

    <div v-else class="px-0" :class="{ 'px-6': showLegacyChrome }">
    <!-- Configurações -->
    <div v-if="tab === 'settings'" class="space-y-5">
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

    <!-- Páginas — sempre o builder 2a -->
    <div v-else-if="tab === 'pages'">
      <div
        v-if="!pageForm"
        class="p-10 text-center text-[14px]"
        style="color: rgba(42, 20, 24, 0.55)"
        data-testid="site-builder-booting"
      >
        Abrindo editor…
      </div>

      <div
        v-else
        class="site-editor-2a flex flex-col"
        data-testid="site-page-builder"
      >
        <!-- Top bar 2a -->
        <div class="site-editor-2a__top shrink-0 flex items-center justify-between px-6 gap-4">
          <div class="flex items-center gap-2.5 min-w-0 text-[13px]">
            <span class="truncate" style="color: rgba(42, 20, 24, 0.62)">{{ tenantStore.name || 'Paróquia' }}</span>
            <span style="color: rgba(42, 20, 24, 0.3)">/</span>
            <span class="font-medium truncate" style="color: #2A1418">{{ pageForm.titulo || 'Página inicial' }}</span>
            <span
              v-if="dirty || pageForm.status !== 'publicado'"
              class="inline-flex items-center gap-1.5 text-[11.5px] font-medium px-2.5 py-1 rounded-full shrink-0"
              style="color: #B4703F; background: #F6EDE4"
            >
              <span class="w-1.5 h-1.5 rounded-full" style="background: #B4703F" />
              {{ dirty ? 'alterações não publicadas' : 'rascunho' }}
            </span>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <a
              v-if="tenantStore.slug"
              :href="publicUrl"
              target="_blank"
              rel="noopener"
              class="rounded-lg px-3.5 py-2.5 text-[12.5px] font-medium no-underline"
              style="border: 1px solid rgba(42, 20, 24, 0.16); background: #fff; color: #2A1418"
            >
              Pré-visualizar
            </a>
            <button
              type="button"
              class="rounded-lg px-4 py-2.5 text-[12.5px] font-medium border-0 disabled:opacity-50"
              style="background: #6B1C2B; color: #FFFDFA"
              :disabled="saving"
              data-testid="pagina-salvar"
              @click="publishPage"
            >
              {{ saving ? 'Publicando…' : 'Publicar' }}
            </button>
          </div>
        </div>

        <div class="site-editor-2a__body flex-1 min-h-0">
          <div
            class="border-r min-w-0 overflow-y-auto px-6 py-[26px]"
            style="border-color: rgba(42, 20, 24, 0.1)"
          >
            <div class="flex items-end justify-between gap-4 mb-5">
              <div>
                <div class="font-serif text-[25px] leading-tight" style="color: #2A1418">Seções da página</div>
                <div class="text-[13px] mt-1 leading-relaxed" style="color: rgba(42, 20, 24, 0.62)">
                  Arraste para reordenar. Desligue o que a paróquia ainda não quer mostrar.
                </div>
              </div>
              <div class="relative shrink-0">
                <button
                  type="button"
                  class="rounded-lg px-3.5 py-2.5 text-[12.5px] font-medium whitespace-nowrap"
                  style="border: 1px dashed rgba(42, 20, 24, 0.24); background: transparent; color: #2A1418"
                  data-testid="bloco-adicionar"
                  @click="showSectionPicker = !showSectionPicker"
                >
                  + Seção
                </button>
                <div
                  v-if="showSectionPicker"
                  class="absolute right-0 top-full mt-2 z-20 w-64 max-h-72 overflow-y-auto rounded-[10px] p-2 shadow-lg"
                  style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.14)"
                  data-testid="bloco-picker"
                >
                  <template v-for="g in blockGroups" :key="g.grupo">
                    <div class="text-[10.5px] font-medium tracking-wide uppercase px-2 pt-2 pb-1" style="color: #B4703F">
                      {{ g.grupo }}
                    </div>
                    <button
                      v-for="b in g.itens"
                      :key="b.tipo"
                      type="button"
                      class="w-full text-left px-2.5 py-2 rounded-lg text-[13px] border-0 cursor-pointer"
                      style="background: transparent; color: #2A1418"
                      @click="addBlock(b.tipo)"
                    >
                      {{ b.label }}
                    </button>
                  </template>
                </div>
              </div>
            </div>

            <div class="flex flex-col gap-2 mb-2">
              <div
                v-for="(b, idx) in pageForm.blocks"
                :key="`${b.tipo}-${idx}`"
                class="site-editor-2a__section-card overflow-hidden"
                :class="{
                  'is-active': idx === selectedBlock,
                  'is-dragging': dragFrom === idx,
                  'is-expanded': idx === selectedBlock,
                }"
                :data-testid="`bloco-item-${idx}`"
                @dragover.prevent
                @drop.prevent="onDrop(idx)"
              >
                <div
                  class="flex items-center gap-[13px] px-[15px] py-3.5 cursor-pointer"
                  @click="toggleBlock(idx)"
                >
                  <span
                    class="font-mono text-[13px] cursor-grab select-none shrink-0"
                    style="color: rgba(42, 20, 24, 0.3)"
                    draggable="true"
                    @click.stop
                    @dragstart="onDragStart(idx)"
                  >⠿</span>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-[14px] font-medium" style="color: #2A1418">{{ blockMeta(b.tipo).label }}</span>
                      <span
                        v-if="b.visivel === false"
                        class="text-[10.5px] font-medium tracking-wide uppercase px-[7px] py-[3px] rounded-full"
                        style="color: #8A2436; background: #F6E9EB"
                      >rascunho</span>
                    </div>
                    <div class="text-[12px] mt-[3px] truncate leading-snug" style="color: rgba(42, 20, 24, 0.62)">
                      {{ idx === selectedBlock ? `${blockSummary(b)} · editando agora` : blockSummary(b) }}
                    </div>
                  </div>
                  <button
                    type="button"
                    class="text-[12.5px] font-medium bg-transparent border-0 cursor-pointer shrink-0"
                    style="color: #8A2436"
                    @click.stop="toggleBlock(idx)"
                  >
                    {{ idx === selectedBlock ? 'fechar' : 'editar' }}
                  </button>
                  <button
                    type="button"
                    class="w-[38px] h-[22px] rounded-full flex items-center p-0.5 border-0 cursor-pointer shrink-0"
                    :style="{
                      background: b.visivel === false ? 'rgba(42,20,24,0.2)' : '#6B1C2B',
                      justifyContent: b.visivel === false ? 'flex-start' : 'flex-end',
                    }"
                    :aria-label="b.visivel === false ? 'Ativar seção' : 'Desativar seção'"
                    @click.stop="toggleBlockVisible(idx)"
                  >
                    <span class="w-[18px] h-[18px] rounded-full bg-white" />
                  </button>
                </div>

                <div
                  v-if="idx === selectedBlock"
                  class="px-[15px] pb-4 pt-1"
                  style="border-top: 1px solid rgba(42, 20, 24, 0.09)"
                  data-testid="site-editing-panel"
                  @click.stop
                >
                  <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="text-[12px]" style="color: rgba(42, 20, 24, 0.62)">{{ savedLabel }}</span>
                  </div>
                  <SiteBlockEditor
                    :key="selectedBlock"
                    :block="b"
                    :forms="forms"
                    :comunicados="comunicados"
                    embedded
                    @dirty="dirty = true"
                    @new-comunicado="openNewComFromBlock"
                    @edit-comunicado="openEditComFromBlock"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Preview sticky -->
          <aside
            class="site-editor-2a__preview p-5 flex flex-col gap-3 min-w-0"
            data-testid="site-editor-preview"
          >
            <div class="flex items-center justify-between shrink-0">
              <span class="text-[11px] font-medium tracking-[0.1em] uppercase" style="color: #B4703F">
                Pré-visualização
              </span>
              <span class="flex gap-[5px]">
                <button
                  type="button"
                  class="text-[11.5px] font-medium px-2.5 py-1 rounded-md"
                  :style="previewMode === 'desktop'
                    ? 'color:#4E1220;background:#FFFDFA;border:1px solid rgba(42,20,24,.14)'
                    : 'color:rgba(42,20,24,.62);border:0;background:transparent'"
                  data-testid="preview-desktop"
                  @click="previewMode = 'desktop'"
                >desktop</button>
                <button
                  type="button"
                  class="text-[11.5px] font-medium px-2.5 py-1 rounded-md"
                  :style="previewMode === 'mobile'
                    ? 'color:#4E1220;background:#FFFDFA;border:1px solid rgba(42,20,24,.14)'
                    : 'color:rgba(42,20,24,.62);border:0;background:transparent'"
                  data-testid="preview-mobile"
                  @click="previewMode = 'mobile'"
                >celular</button>
              </span>
            </div>
            <div
              class="rounded-[10px] overflow-hidden flex-1 min-h-0 flex flex-col"
              :class="previewMode === 'mobile' ? 'max-w-[320px] mx-auto w-full' : 'w-full'"
              style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.14)"
            >
              <div
                class="h-[26px] shrink-0 flex items-center px-2.5"
                style="background: #F3EDE6; border-bottom: 1px solid rgba(42, 20, 24, 0.1)"
              >
                <span class="font-mono text-[9.5px]" style="color: rgba(42, 20, 24, 0.62)">
                  eclesias.com.br/site/{{ tenantStore.slug || '…' }}
                </span>
              </div>
              <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
                <SitePagePreview
                  :blocks="pageForm.blocks"
                  :settings="settings"
                  :tenant-slug="tenantStore.slug || ''"
                  :page-title="pageForm.titulo"
                  :highlight-index="selectedBlock"
                  compact
                  selectable
                  @select="(i) => selectBlock(i, { fromPreview: true })"
                />
              </div>
            </div>
            <p class="text-[11.5px] leading-relaxed shrink-0" style="color: rgba(42, 20, 24, 0.62)">
              Clique em uma seção no preview para editar. Rascunhos só vão ao ar ao publicar.
            </p>
          </aside>
        </div>

        <!-- Overlay comunicado (mantém o builder aberto) -->
        <div
          v-if="comOverlay && comForm"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
          style="background: rgba(42, 20, 24, 0.45)"
          data-testid="com-overlay"
        >
          <div
            class="w-full max-w-lg rounded-[11px] p-5 max-h-[90vh] overflow-y-auto"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.12)"
          >
            <div class="font-serif text-[18px] mb-4" style="color: #2A1418">
              {{ comForm.id ? 'Editar comunicado' : 'Novo comunicado' }}
            </div>
            <div class="space-y-3">
              <div>
                <label class="fld">Título</label>
                <input v-model="comForm.titulo" class="input" required />
              </div>
              <div>
                <label class="fld">Resumo</label>
                <input v-model="comForm.resumo" class="input" />
              </div>
              <div>
                <label class="fld">Corpo</label>
                <textarea v-model="comForm.corpo" class="input min-h-28" />
              </div>
              <div class="flex flex-wrap gap-4 items-center">
                <select v-model="comForm.status" class="input max-w-[160px]">
                  <option value="rascunho">Rascunho</option>
                  <option value="publicado">Publicado</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm">
                  <input v-model="comForm.destaque" type="checkbox" />
                  Destaque
                </label>
              </div>
            </div>
            <div class="flex gap-2 mt-5">
              <button type="button" class="btn btn-primary" :disabled="saving" @click="saveCom">
                {{ saving ? 'Salvando…' : 'Salvar' }}
              </button>
              <button type="button" class="btn btn-ghost" @click="closeComOverlay">Cancelar</button>
            </div>
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
  </div>
</template>
