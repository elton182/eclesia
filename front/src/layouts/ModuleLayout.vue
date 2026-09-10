<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { userHasPermission } from '@/utils/userRoles'

const props = defineProps({
  moduleKey: {
    type: String,
    required: true,
    validator: (v) => ['ecc', 'site'].includes(v),
  },
})

const route = useRoute()
const router = useRouter()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()
const igrejaStore = useIgrejaStore()

const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)

const can = (permission) =>
  userHasPermission(authTenant.user, permission, {
    isSuperAdmin: isPlatformAdmin.value,
  })

const orgName = computed(() => tenantStore.name || 'Paróquia')
const displayName = computed(
  () => authTenant.user?.name || authAdmin.user?.name || 'Usuário',
)
const initials = computed(() => {
  const n = displayName.value
  const parts = n.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})

const igrejaLabel = computed(() => igrejaStore.current?.nome || 'Todas as comunidades')

const moduleMeta = computed(() => {
  if (props.moduleKey === 'site') {
    return {
      title: 'Site',
      subtitle: tenantStore.slug ? `/${tenantStore.slug}` : 'Página pública',
      subtitleMono: true,
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
  const items = []
  if (can('telas.equipes') || isPlatformAdmin.value) {
    items.push({ label: 'Equipes', path: '/ecc/equipes' })
  }
  if (can('telas.casais') || isPlatformAdmin.value) {
    items.push({ label: 'Casais', path: '/ecc/casais' })
  }
  items.push({ label: 'Encontros', path: '/ecc/encontros', stub: true })
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
  return route.path === item.path || route.path.startsWith(item.path + '/')
}

function onNav(item) {
  if (item.stub) return
  router.push({ path: item.path, query: item.query || {} })
}

onMounted(async () => {
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
  <div class="min-h-screen flex" style="background: #F7F4EF" data-testid="module-layout">
    <aside
      class="hidden md:flex w-[214px] shrink-0 flex-col gap-6 px-3.5 py-5"
      style="background: #4E1220"
      data-testid="module-sidebar"
    >
      <router-link
        to="/inicio"
        class="flex items-center gap-2.5 no-underline px-2"
        data-testid="module-back-launcher"
      >
        <span style="color: #C88A5E; font-size: 13px">←</span>
        <span class="text-[12.5px]" style="color: rgba(255, 253, 250, 0.72)">Todos os módulos</span>
      </router-link>

      <div class="px-2">
        <div
          class="text-[11px] font-medium tracking-wider uppercase mb-1.5"
          style="color: #C88A5E"
        >
          Módulo
        </div>
        <div class="font-serif text-[21px] font-medium leading-tight" style="color: #FFFDFA">
          {{ moduleMeta.title }}
        </div>
        <div
          class="text-[12px] leading-relaxed mt-1"
          :class="moduleMeta.subtitleMono ? 'font-mono text-[11.5px]' : ''"
          style="color: rgba(255, 253, 250, 0.55)"
        >
          {{ moduleMeta.subtitle }}
        </div>
      </div>

      <nav class="flex flex-col gap-0.5">
        <button
          v-for="item in navItems"
          :key="item.label"
          type="button"
          class="text-left px-3 py-2.5 rounded-lg text-[13.5px] border-0 cursor-pointer"
          :style="
            isActive(item)
              ? { background: '#FFFDFA', color: '#4E1220', fontWeight: 500 }
              : {
                  background: 'transparent',
                  color: item.stub ? 'rgba(255,253,250,0.45)' : 'rgba(255,253,250,0.85)',
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
        style="background: #FFFDFA; border-bottom: 1px solid rgba(42, 20, 24, 0.1)"
      >
        <div class="flex items-center gap-2.5 min-w-0 text-[13px]" style="color: rgba(42, 20, 24, 0.62)">
          <router-link to="/inicio" class="md:hidden no-underline" style="color: #8A2436">←</router-link>
          <span class="truncate hidden sm:inline">{{ orgName }}</span>
          <span class="hidden sm:inline" style="color: rgba(42, 20, 24, 0.3)">/</span>
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[13px] font-medium truncate"
            style="border: 1px solid rgba(42, 20, 24, 0.14); color: #2A1418"
          >
            {{ igrejaLabel }}
            <span class="text-[9px]" style="color: rgba(42, 20, 24, 0.62)">▾</span>
          </span>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
          <span class="text-[12.5px] hidden sm:inline" style="color: rgba(42, 20, 24, 0.62)">
            {{ displayName }}
          </span>
          <div
            class="w-7 h-7 rounded-full flex items-center justify-center text-[11.5px] font-medium"
            style="background: #6B1C2B; color: #F0D8C2"
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
