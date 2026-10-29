<script setup>
import { computed, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { useBrandingStore } from '@/stores/branding'
import {
  userHasPermission,
  canSeeEccCasaisNav,
  canSeeEccEquipesNav,
  canSeeEccEventosNav,
  canSeeEccFinanceiroNav,
  canSeePastoraisNav,
  isSomenteLiderEquipe,
  isViewAsLider,
} from '@/utils/userRoles'
import { formatMuralBadges } from '@/utils/eccMural'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBars } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import PwaInstallNavButton from '@/components/base/PwaInstallNavButton.vue'
import UserMenuAppActions from '@/components/base/UserMenuAppActions.vue'
import ViewAsLiderBanner from '@/components/ViewAsLiderBanner.vue'
import LiderBottomNav from '@/components/ecc/LiderBottomNav.vue'
import { asideNavClasses } from '@/utils/mobileNav'
import fallbackLogo from '@/assets/logo-icon.png'

library.add(faBars)

const props = defineProps({
  moduleKey: {
    type: String,
    required: true,
    validator: (v) => ['ecc', 'site', 'eventos', 'calendario', 'pastorais'].includes(v),
  },
})

const route = useRoute()
const router = useRouter()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()
const igrejaStore = useIgrejaStore()
const branding = useBrandingStore()

const brandLogo = computed(() => branding.logoUrl || fallbackLogo)
const brandName = computed(() =>
  branding.logoUrl
    ? tenantStore.name || tenantStore.slug || 'Organização'
    : 'Apostolus',
)
const brandAlt = computed(() =>
  branding.logoUrl ? brandName.value : 'Apostolus',
)

const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)

const can = (permission) =>
  userHasPermission(authTenant.user, permission, {
    isSuperAdmin: isPlatformAdmin.value,
  })

const orgName = computed(() => {
  const name = (tenantStore.name || '').trim()
  if (name) return name
  return tenantStore.slug || 'Organização'
})

const displayName = computed(
  () => authTenant.user?.name || authAdmin.user?.name || 'Usuário',
)
const initials = computed(() => {
  const n = displayName.value
  const parts = n.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})

const igrejaLabel = computed(() => {
  if (igrejaStore.current?.nome) return igrejaStore.current.nome
  if (igrejaStore.igrejas?.length === 1) return igrejaStore.igrejas[0].nome
  if ((igrejaStore.igrejas?.length || 0) > 1) return 'Todas as comunidades'
  return null
})

const showOrgInHeader = computed(() => {
  if (!igrejaLabel.value) return true
  return orgName.value.trim().toLowerCase() !== igrejaLabel.value.trim().toLowerCase()
})

const headerContextLabel = computed(() => igrejaLabel.value || orgName.value)

const moduleMeta = computed(() => {
  if (props.moduleKey === 'site') {
    return {
      title: 'Site',
      subtitle: tenantStore.slug ? `/${tenantStore.slug}` : 'Página pública',
      subtitleMono: true,
    }
  }
  if (props.moduleKey === 'eventos') {
    return {
      title: 'Eventos',
      subtitle: 'Eventos gerais da igreja',
      subtitleMono: false,
    }
  }
  if (props.moduleKey === 'calendario') {
    return {
      title: 'Agenda Paroquial',
      subtitle: 'Missas, casamentos e batismos',
      subtitleMono: false,
    }
  }
  if (props.moduleKey === 'pastorais') {
    return {
      title: 'Planejamento Anual',
      subtitle: 'Pastorais propõem · padres revisam',
      subtitleMono: false,
    }
  }
  return {
    title: 'ECC',
    subtitle: 'Encontro de Casais com Cristo',
    subtitleMono: false,
  }
})

const somenteLider = computed(() =>
  isSomenteLiderEquipe(authTenant.user, { isSuperAdmin: isPlatformAdmin.value }),
)

const viewAsLider = computed(() => isViewAsLider(authTenant.user))

const liderShell = computed(
  () => props.moduleKey === 'ecc' && (somenteLider.value || viewAsLider.value),
)

const recadosBadge = ref(null)
const reflexoesBadge = ref(null)
const tourHelpRequest = ref(0)
provide('liderTourHelpRequest', tourHelpRequest)

async function refreshMuralBadges() {
  if (!liderShell.value) {
    recadosBadge.value = null
    reflexoesBadge.value = null
    return
  }
  try {
    const { data } = await api.get('/ecc/mural/nao-lidos')
    const badges = formatMuralBadges(data?.data)
    recadosBadge.value = badges.recados
    reflexoesBadge.value = badges.reflexoes
  } catch {
    recadosBadge.value = null
    reflexoesBadge.value = null
  }
}

/** @deprecated use refreshMuralBadges — mantido para evento do router-view */
async function refreshRecadosBadge() {
  await refreshMuralBadges()
}

const liderNavActive = computed(() => {
  if (route.path.startsWith('/ecc/recados')) return 'recados'
  if (route.path.startsWith('/ecc/reflexoes')) return 'reflexoes'
  return 'equipe'
})

function onLiderNav(tab) {
  if (tab === 'equipe') router.push({ name: 'ecc-minha-equipe' })
  else if (tab === 'recados') router.push({ name: 'ecc-recados' })
  else if (tab === 'reflexoes') router.push({ name: 'ecc-reflexoes' })
}

function requestLiderTourHelp() {
  if (route.name !== 'ecc-minha-equipe') {
    router.push({ name: 'ecc-minha-equipe', query: { tour: '1' } })
    return
  }
  tourHelpRequest.value += 1
}

watch(
  () => [liderShell.value, route.path],
  () => {
    if (liderShell.value) refreshMuralBadges()
  },
  { immediate: true },
)

const navItems = computed(() => {
  if (props.moduleKey === 'ecc' && somenteLider.value) {
    return [
      { label: 'Minha equipe', path: '/ecc/minha-equipe', exact: true },
      { label: 'Recados', path: '/ecc/recados' },
      { label: 'Reflexões', path: '/ecc/reflexoes' },
    ]
  }
  if (props.moduleKey === 'site') {
    return [
      { label: 'Páginas e seções', path: '/site', exact: true },
      { label: 'Identidade visual', path: '/site', query: { tab: 'settings' } },
      { label: 'Comunicados', path: '/site', query: { tab: 'comunicados' } },
      { label: 'Pastorais', path: '/site', query: { tab: 'pastorais' } },
      { label: 'Formulários', path: '/site', query: { tab: 'forms' } },
      { label: 'Mídias', path: '/site', stub: true },
      { label: 'Domínio e SEO', path: '/site', stub: true },
      { label: 'Histórico de publicações', path: '/site', stub: true },
    ]
  }
  if (props.moduleKey === 'calendario') {
    return [
      { label: 'Meses', path: '/calendario', exact: true },
      { label: 'Locais e horários', path: '/calendario/locais' },
      { label: 'Tipos de evento', path: '/calendario/tipos' },
    ]
  }
  if (props.moduleKey === 'pastorais') {
    const items = []
    if (
      canSeePastoraisNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value }) ||
      can('telas.pastorais') ||
      can('pastorais.manage') ||
      can('pastorais.view')
    ) {
      items.push({ label: 'Pastorais', path: '/pastorais', exact: true })
    }
    if (
      can('planejamento.gerir') ||
      can('planejamento.propor') ||
      can('planejamento.ver_global') ||
      can('telas.pastorais') ||
      isPlatformAdmin.value
    ) {
      items.push({ label: 'Planejamento anual', path: '/planejamento' })
    }
    if (can('planejamento.gerir') || isPlatformAdmin.value) {
      items.push({ label: 'Espaços e recursos', path: '/planejamento/recursos' })
    }
    return items
  }
  if (props.moduleKey === 'eventos') {
    if (canSeeEccEventosNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
      return [{ label: 'Agenda', path: '/eventos', exact: true }]
    }
    return []
  }
  const items = []
  if (canSeeEccCasaisNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Casais', path: '/ecc/casais' })
  }
  if (canSeeEccEquipesNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Equipes', path: '/ecc/equipes' })
  }
  if (canSeeEccEventosNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Eventos', path: '/ecc/eventos' })
  }
  if (canSeeEccFinanceiroNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Financeiro', path: '/ecc/financeiro' })
  }
  if (
    can('ecc.recados.view') ||
    can('ecc.recados.manage') ||
    can('ecc.reflexoes.view') ||
    can('ecc.reflexoes.manage')
  ) {
    items.push({ label: 'Recados', path: '/ecc/recados' })
    items.push({ label: 'Reflexões', path: '/ecc/reflexoes' })
  }
  if (can('ecc.perguntas.view') || can('ecc.perguntas.manage')) {
    items.push({ label: 'Perguntas', path: '/ecc/perguntas' })
  }
  items.push({ label: 'Relatórios', path: '/ecc/relatorios', stub: true })
  return items
})

function isActive(item) {
  if (item.stub) return false
  if (props.moduleKey === 'site') {
    const q = route.query.tab ? String(route.query.tab) : ''
    if (item.exact) return route.path === '/site' && !q
    if (item.query?.tab) return route.path === '/site' && q === item.query.tab
  }
  if (item.exact) return route.path === item.path
  if (props.moduleKey === 'calendario' && item.path === '/calendario') {
    return route.path === '/calendario'
  }
  if (props.moduleKey === 'calendario' && item.path === '/calendario/locais') {
    return route.path === '/calendario/locais' || route.path.startsWith('/calendario/locais/')
  }
  if (props.moduleKey === 'calendario' && item.path === '/calendario/tipos') {
    return route.path === '/calendario/tipos' || route.path.startsWith('/calendario/tipos/')
  }
  if (props.moduleKey === 'pastorais' && item.path === '/pastorais') {
    return route.path === '/pastorais' || route.path.startsWith('/pastorais/')
  }
  if (props.moduleKey === 'pastorais' && item.path === '/planejamento') {
    if (route.path.startsWith('/planejamento/recursos')) return false
    return route.path === '/planejamento' || route.path.startsWith('/planejamento/')
  }
  return route.path === item.path || route.path.startsWith(item.path + '/')
}

function onNav(item) {
  if (item.stub) return
  closeMobileNav()
  router.push({ path: item.path, query: item.query || {} })
}

const userMenuOpen = ref(false)
const mobileNavOpen = ref(false)

const sidebarClass = computed(() =>
  asideNavClasses({ open: mobileNavOpen.value || false }) + ' px-3.5 py-5',
)

function closeMobileNav() {
  mobileNavOpen.value = false
}

function toggleMobileNav() {
  mobileNavOpen.value = !mobileNavOpen.value
}

async function logout() {
  userMenuOpen.value = false
  closeMobileNav()
  if (isPlatformAdmin.value) {
    tenantStore.clear()
    igrejaStore.reset()
    branding.reset()
    await authAdmin.logout()
    router.push('/admin/login')
    return
  }
  await authTenant.logout()
  router.push('/entrar')
}

function onDocumentClick(e) {
  if (!e.target.closest('[data-testid="module-user-menu"]')) {
    userMenuOpen.value = false
  }
}

function onKeydown(e) {
  if (e.key === 'Escape') closeMobileNav()
}

watch(
  () => route.fullPath,
  () => closeMobileNav(),
)

onMounted(async () => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
  if (tenantStore.slug) {
    await branding.ensureLoaded()
  }
  if (tenantStore.slug && !igrejaStore.igrejas.length) {
    try {
      await igrejaStore.load()
    } catch {
      /* ignore */
    }
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    style="background: var(--color-bg)"
    data-testid="module-layout"
  >
    <ViewAsLiderBanner v-if="viewAsLider" />
    <div
      v-if="mobileNavOpen && !liderShell"
      class="fixed inset-0 z-40 md:hidden"
      style="background: rgba(42, 20, 24, 0.45)"
      data-testid="module-nav-backdrop"
      @click="closeMobileNav"
    />
    <div class="flex flex-1 min-h-0">
    <aside
      :class="sidebarClass"
      style="background: var(--color-primary); color: var(--color-on-primary)"
      data-testid="module-sidebar"
    >
      <div class="flex items-center gap-3 px-2 pb-3 border-b border-white/10">
        <img
          :src="brandLogo"
          :alt="brandAlt"
          class="h-12 w-12 rounded-xl object-cover bg-white shrink-0"
          data-testid="module-sidebar-brand-logo"
        />
        <div class="min-w-0">
          <div
            class="font-semibold text-[16px] leading-tight truncate"
            style="font-family: Fraunces, serif; color: var(--color-on-primary)"
          >
            {{ brandName }}
          </div>
          <div
            class="text-[10px] uppercase tracking-[0.14em] font-semibold mt-0.5"
            style="color: color-mix(in srgb, var(--color-on-primary) 70%, transparent)"
          >
            Gestão eclesial
          </div>
        </div>
      </div>

      <router-link
        v-if="!somenteLider"
        to="/inicio"
        class="flex items-center gap-2.5 no-underline px-2"
        data-testid="module-back-launcher"
      >
        <span style="color: var(--color-accent); font-size: 13px">←</span>
        <span
          class="text-[12.5px]"
          style="color: color-mix(in srgb, var(--color-on-primary) 72%, transparent)"
        >
          Todos os módulos
        </span>
      </router-link>

      <div class="px-2">
        <div
          class="text-[11px] font-medium tracking-wider uppercase mb-1.5"
          style="color: var(--color-accent)"
        >
          Módulo
        </div>
        <div
          class="font-serif text-[21px] font-medium leading-tight"
          style="color: var(--color-on-primary)"
        >
          {{ moduleMeta.title }}
        </div>
        <div
          class="text-[12px] leading-relaxed mt-1"
          :class="moduleMeta.subtitleMono ? 'font-mono text-[11.5px]' : ''"
          style="color: color-mix(in srgb, var(--color-on-primary) 55%, transparent)"
        >
          {{ moduleMeta.subtitle }}
        </div>
      </div>

      <nav class="flex flex-col gap-1">
        <button
          v-for="item in navItems"
          :key="item.label"
          type="button"
          class="text-left px-3 py-2.5 rounded-lg text-[13.5px] border-0 cursor-pointer leading-snug min-h-[40px]"
          :style="
            isActive(item)
              ? { background: 'var(--color-surface)', color: 'var(--color-primary)', fontWeight: 500 }
              : {
                  background: 'transparent',
                  color: item.stub
                    ? 'color-mix(in srgb, var(--color-on-primary) 45%, transparent)'
                    : 'color-mix(in srgb, var(--color-on-primary) 85%, transparent)',
                }
          "
          :disabled="item.stub"
          @click="onNav(item)"
        >
          <span class="inline-flex items-center gap-2">
            {{ item.label }}
            <span
              v-if="liderShell && item.path === '/ecc/recados' && recadosBadge"
              class="min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-semibold inline-flex items-center justify-center"
              :style="
                isActive(item)
                  ? { background: 'var(--color-accent-dark)', color: '#fff' }
                  : { background: 'var(--color-accent)', color: 'var(--color-primary)' }
              "
              data-testid="sidebar-recados-badge"
            >{{ recadosBadge }}</span>
            <span
              v-if="liderShell && item.path === '/ecc/reflexoes' && reflexoesBadge"
              class="min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-semibold inline-flex items-center justify-center"
              :style="
                isActive(item)
                  ? { background: 'var(--color-accent-dark)', color: '#fff' }
                  : { background: 'var(--color-accent)', color: 'var(--color-primary)' }
              "
              data-testid="sidebar-reflexoes-badge"
            >{{ reflexoesBadge }}</span>
          </span>
        </button>
      </nav>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col w-full">
      <header
        v-if="moduleKey !== 'site' || !liderShell"
        class="h-14 shrink-0 flex items-center justify-between px-4 md:px-6 gap-3"
        style="background: var(--color-surface); border-bottom: 1px solid var(--color-line)"
        :class="moduleKey === 'site' ? 'md:hidden' : ''"
      >
        <div
          class="flex items-center gap-2.5 min-w-0 text-[13px]"
          style="color: var(--color-muted)"
        >
          <button
            v-if="!liderShell"
            type="button"
            class="md:hidden w-9 h-9 rounded-lg flex items-center justify-center border-0 cursor-pointer shrink-0"
            style="background: var(--color-primary-soft); color: var(--color-on-primary)"
            aria-label="Abrir menu de navegação"
            :aria-expanded="mobileNavOpen"
            data-testid="module-nav-toggle"
            @click="toggleMobileNav"
          >
            <FontAwesomeIcon :icon="faBars" class="text-[15px]" />
          </button>
          <router-link
            v-if="!liderShell && moduleKey !== 'site'"
            to="/inicio"
            class="md:hidden no-underline"
            style="color: var(--color-primary-hover)"
            data-testid="module-back-launcher-mobile"
          >
            ←
          </router-link>
          <template v-if="showOrgInHeader && igrejaLabel && !liderShell">
            <span class="truncate hidden sm:inline">{{ orgName }}</span>
            <span class="hidden sm:inline" style="color: var(--color-line)">/</span>
          </template>
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[13px] font-medium truncate max-w-[16rem]"
            :style="liderShell
              ? { color: 'var(--color-ink)', border: 'none', paddingLeft: 0 }
              : { border: '1px solid var(--color-line)', color: 'var(--color-ink)' }"
          >
            {{ headerContextLabel }}
            <span v-if="!liderShell" class="text-[9px]" style="color: var(--color-muted)">▾</span>
          </span>
        </div>
        <div class="flex items-center gap-2.5 shrink-0 relative" data-testid="module-user-menu">
          <button
            v-if="liderShell"
            type="button"
            class="w-8 h-8 rounded-full flex items-center justify-center border-0 cursor-pointer"
            style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
            aria-label="Ajuda — ver tour"
            data-testid="lider-ajuda-tour"
            data-tour="rever"
            @click="requestLiderTourHelp"
          >
            <span class="text-[14px] font-semibold leading-none">?</span>
          </button>
          <PwaInstallNavButton v-if="!liderShell" labeled />
          <span
            v-if="!liderShell"
            class="text-[12.5px] hidden sm:inline"
            style="color: var(--color-muted)"
          >
            {{ displayName }}
          </span>
          <button
            type="button"
            class="w-8 h-8 rounded-full flex items-center justify-center text-[11.5px] font-medium"
            style="background: var(--color-primary-soft); color: #F0D8C2"
            aria-label="Menu do usuário"
            data-testid="module-user-menu-toggle"
            @click.stop="userMenuOpen = !userMenuOpen"
          >
            {{ initials }}
          </button>
          <div
            v-if="userMenuOpen"
            class="absolute right-0 top-9 rounded-lg py-2 min-w-[160px] z-20 shadow-lg"
            style="background: var(--color-surface); border: 1px solid var(--color-line)"
            data-testid="module-user-menu-dropdown"
          >
            <UserMenuAppActions />
            <button
              type="button"
              class="w-full text-left px-4 py-2 text-[13px]"
              style="color: var(--color-ink)"
              data-testid="module-logout"
              @click="logout"
            >
              Sair
            </button>
          </div>
        </div>
      </header>

      <main
        class="flex-1 min-w-0 w-full"
        :class="liderShell ? 'pb-[72px] md:pb-0' : ''"
      >
        <router-view @mural-seen="refreshMuralBadges" @recados-seen="refreshMuralBadges" />
      </main>

    </div>
    </div>

    <LiderBottomNav
      v-if="liderShell"
      :active="liderNavActive"
      :recados-badge="recadosBadge"
      :reflexoes-badge="reflexoesBadge"
      @navigate="onLiderNav"
    />
  </div>
</template>
