<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { useBrandingStore } from '@/stores/branding'
import { userHasPermission, canSeeEccCasaisNav, canSeeEccEventosNav, canSeeEccFinanceiroNav } from '@/utils/userRoles'
import PwaInstallNavButton from '@/components/base/PwaInstallNavButton.vue'
import fallbackLogo from '@/assets/logo-icon.png'

const props = defineProps({
  moduleKey: {
    type: String,
    required: true,
    validator: (v) => ['ecc', 'site', 'eventos', 'calendario'].includes(v),
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
    : 'Eclésia',
)
const brandAlt = computed(() =>
  branding.logoUrl ? brandName.value : 'Eclésia',
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
      subtitle: 'Agenda paroquial',
      subtitleMono: false,
    }
  }
  if (props.moduleKey === 'calendario') {
    return {
      title: 'Calendário',
      subtitle: 'Calendário oficial mensal',
      subtitleMono: false,
    }
  }
  return {
    title: 'ECC',
    subtitle: 'Encontro de Casais com Cristo',
    subtitleMono: false,
  }
})

const navItems = computed(() => {
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
  if (canSeeEccEventosNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Eventos', path: '/ecc/eventos' })
  }
  if (canSeeEccFinanceiroNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) {
    items.push({ label: 'Financeiro', path: '/ecc/financeiro' })
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
  return route.path === item.path || route.path.startsWith(item.path + '/')
}

function onNav(item) {
  if (item.stub) return
  router.push({ path: item.path, query: item.query || {} })
}

onMounted(async () => {
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
</script>

<template>
  <div
    class="min-h-screen flex"
    style="background: var(--color-bg)"
    data-testid="module-layout"
  >
    <aside
      class="hidden md:flex w-[214px] shrink-0 flex-col gap-5 px-3.5 py-5"
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
          {{ item.label }}
        </button>
      </nav>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col w-full">
      <header
        v-if="moduleKey !== 'site'"
        class="h-14 shrink-0 flex items-center justify-between px-4 md:px-6 gap-3"
        style="background: var(--color-surface); border-bottom: 1px solid var(--color-line)"
      >
        <div
          class="flex items-center gap-2.5 min-w-0 text-[13px]"
          style="color: var(--color-muted)"
        >
          <router-link
            to="/inicio"
            class="md:hidden no-underline"
            style="color: var(--color-primary-hover)"
          >
            ←
          </router-link>
          <template v-if="showOrgInHeader && igrejaLabel">
            <span class="truncate hidden sm:inline">{{ orgName }}</span>
            <span class="hidden sm:inline" style="color: var(--color-line)">/</span>
          </template>
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[13px] font-medium truncate max-w-[16rem]"
            style="border: 1px solid var(--color-line); color: var(--color-ink)"
          >
            {{ headerContextLabel }}
            <span class="text-[9px]" style="color: var(--color-muted)">▾</span>
          </span>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
          <PwaInstallNavButton labeled />
          <span class="text-[12.5px] hidden sm:inline" style="color: var(--color-muted)">
            {{ displayName }}
          </span>
          <div
            class="w-7 h-7 rounded-full flex items-center justify-center text-[11.5px] font-medium"
            style="background: var(--color-primary-soft); color: #F0D8C2"
          >
            {{ initials }}
          </div>
        </div>
      </header>

      <main class="flex-1 min-w-0 w-full">
        <router-view />
      </main>
    </div>
  </div>
</template>
