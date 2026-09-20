<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faBuilding,
  faHeart,
  faUserShield,
  faHouse,
  faChurch,
  faGlobe,
  faClipboardList,
  faPalette,
} from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useTenantStore } from '@/stores/tenant'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { userHasPermission, canSeeEccCasaisNav } from '@/utils/userRoles'
import fallbackLogo from '@/assets/logo-icon.png'

library.add(faBuilding, faHeart, faUserShield, faHouse, faChurch, faGlobe, faClipboardList, faPalette)

defineProps({
  isOpen: { type: Boolean, default: true },
})

const route = useRoute()
const tenantStore = useTenantStore()
const authAdmin = useAuthAdminStore()
const authTenant = useAuthStore()
const branding = useBrandingStore()

const isActive = (path) => route.path === path || route.path.startsWith(path + '/')

const isPlatform = computed(() => authAdmin.isAuthenticated && route.path.startsWith('/admin'))
const isTenantShell = computed(() => authTenant.isAuthenticated && !route.path.startsWith('/admin'))
const inTenantContext = computed(
  () => !!tenantStore.slug && (authTenant.isAuthenticated || authAdmin.isAuthenticated),
)

const brandLogo = computed(() => branding.logoUrl || fallbackLogo)
const brandAlt = computed(() =>
  branding.logoUrl
    ? tenantStore.name || tenantStore.slug || 'Logo'
    : 'Eclésia',
)

const can = (permission) =>
  userHasPermission(authTenant.user, permission, {
    isSuperAdmin: authAdmin.isAuthenticated && !authTenant.isAuthenticated,
  })

const canManageBranding = computed(() => {
  if (authAdmin.isAuthenticated && !authTenant.isAuthenticated) return true
  const roles = (authTenant.user?.roles || []).map((r) => r.name)
  return roles.includes('admin-tenant')
})

const platformItems = ref([
  { label: 'Tenants', icon: faBuilding, path: '/admin/tenants' },
])

const tenantItems = computed(() => {
  if (!inTenantContext.value) return []

  const items = [{ label: 'Início', icon: faHouse, path: '/inicio' }]
  if (can('telas.igrejas') || (authAdmin.isAuthenticated && !authTenant.isAuthenticated)) {
    items.push({ label: 'Igrejas', icon: faChurch, path: '/igrejas' })
  }
  if (can('telas.usuarios') || (authAdmin.isAuthenticated && !authTenant.isAuthenticated)) {
    items.push({ label: 'Usuários', icon: faUserShield, path: '/usuarios' })
  }
  if (can('telas.auditoria') || (authAdmin.isAuthenticated && !authTenant.isAuthenticated)) {
    items.push({ label: 'Auditoria', icon: faClipboardList, path: '/auditoria' })
  }
  if (canManageBranding.value) {
    items.push({ label: 'Marca', icon: faPalette, path: '/configuracoes/marca' })
  }
  if (can('telas.site') || (authAdmin.isAuthenticated && !authTenant.isAuthenticated)) {
    items.push({ label: 'Site', icon: faGlobe, path: '/site' })
  }
  return items
})

const eccItems = computed(() => {
  if (!inTenantContext.value) return []

  const isPlatformAdmin = authAdmin.isAuthenticated && !authTenant.isAuthenticated
  const items = []
  if (canSeeEccCasaisNav(authTenant.user, { isSuperAdmin: isPlatformAdmin })) {
    items.push({ label: 'Casais', icon: faHeart, path: '/ecc/casais' })
  }
  return items
})
</script>

<template>
  <aside
    :class="[
      'transition-all duration-300 ease-in-out overflow-y-auto hidden md:flex flex-col',
      isOpen ? 'w-60' : 'w-20',
    ]"
    style="background: var(--color-primary); color: var(--color-on-primary, #F7EDE0)"
  >
    <div class="px-4 pt-5 pb-4 flex items-center gap-3 border-b border-white/10">
      <img
        :src="brandLogo"
        :alt="brandAlt"
        class="h-12 w-12 rounded-xl object-cover bg-white"
        data-testid="sidebar-brand-logo"
      />
      <div v-if="isOpen" class="min-w-0">
        <div class="font-semibold text-[17px] leading-tight" style="font-family: Fraunces, serif">
          {{ branding.logoUrl ? (tenantStore.name || 'Organização') : 'Eclésia' }}
        </div>
        <div class="text-[10px] uppercase tracking-[0.14em] font-semibold opacity-80">
          Gestão eclesial
        </div>
      </div>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1">
      <template v-if="isPlatform || (!isTenantShell && authAdmin.isAuthenticated)">
        <p v-if="isOpen" class="px-2 mb-2 text-[11px] uppercase tracking-wider text-[#D4B896] font-bold">
          Plataforma
        </p>
        <router-link
          v-for="item in platformItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 py-2.5 px-3 rounded-xl text-[14.5px] font-medium transition-colors',
            isActive(item.path)
              ? 'bg-[#FCFAF6] text-[var(--color-primary)]'
              : 'text-[#EBCFB8] hover:bg-white/10 hover:text-[#FBEEE4]',
          ]"
          data-testid="nav-tenants"
        >
          <FontAwesomeIcon :icon="item.icon" class="w-5 text-center" />
          <span v-if="isOpen">{{ item.label }}</span>
        </router-link>
      </template>

      <template v-if="tenantItems.length || eccItems.length">
        <p v-if="isOpen" class="px-2 mt-5 mb-2 text-[11px] uppercase tracking-wider text-[#D4B896] font-bold">
          {{ tenantStore.name || tenantStore.slug || 'Organização' }}
        </p>
        <router-link
          v-for="item in tenantItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 py-2.5 px-3 rounded-xl text-[14.5px] font-medium transition-colors',
            isActive(item.path)
              ? 'bg-[#FCFAF6] text-[var(--color-primary)]'
              : 'text-[#EBCFB8] hover:bg-white/10 hover:text-[#FBEEE4]',
          ]"
          :data-testid="item.path === '/inicio' ? 'nav-inicio' : 'nav-usuarios'"
        >
          <FontAwesomeIcon :icon="item.icon" class="w-5 text-center" />
          <span v-if="isOpen">{{ item.label }}</span>
        </router-link>
        <router-link
          v-for="item in eccItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 py-2.5 px-3 rounded-xl text-[14.5px] font-medium transition-colors',
            isActive(item.path)
              ? 'bg-[#FCFAF6] text-[var(--color-primary)]'
              : 'text-[#EBCFB8] hover:bg-white/10 hover:text-[#FBEEE4]',
          ]"
        >
          <FontAwesomeIcon :icon="item.icon" class="w-5 text-center" />
          <span v-if="isOpen">{{ item.label }}</span>
        </router-link>
      </template>
    </nav>

    <div v-if="isOpen" class="px-4 py-4 text-[11.5px] text-[#D69E8E] border-t border-white/10 leading-relaxed">
      Identidade visual baseada na marca Eclésia.
    </div>
  </aside>
</template>
