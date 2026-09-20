<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faBars,
  faSignOutAlt,
  faChevronDown,
} from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { useBrandingStore } from '@/stores/branding'
import { igrejaTipoLabel } from '@/utils/igrejaContext'
import PwaInstallNavButton from '@/components/base/PwaInstallNavButton.vue'
import fallbackLogo from '@/assets/logo-icon.png'

library.add(faBars, faSignOutAlt, faChevronDown)

const emit = defineEmits(['toggleSidebar'])
const authAdmin = useAuthAdminStore()
const authTenant = useAuthStore()
const tenantStore = useTenantStore()
const igrejaStore = useIgrejaStore()
const branding = useBrandingStore()
const router = useRouter()
const route = useRoute()
const userMenuOpen = ref(false)

const displayName = computed(
  () => authTenant.user?.name || authAdmin.user?.name || 'Usuário',
)

const brandLogo = computed(() => branding.logoUrl || fallbackLogo)
const brandAlt = computed(() =>
  branding.logoUrl
    ? tenantStore.name || tenantStore.slug || 'Logo'
    : 'Eclésia',
)

const isAdminArea = computed(() => route.path.startsWith('/admin') || route.path.startsWith('/ecc'))

const showIgrejaSelector = computed(
  () =>
    !!tenantStore.slug &&
    (authTenant.isAuthenticated || authAdmin.isAuthenticated) &&
    !route.path.startsWith('/admin'),
)

const onIgrejaChange = (event) => {
  igrejaStore.select(event.target.value)
}

watch(
  () => tenantStore.slug,
  async (slug) => {
    if (slug && showIgrejaSelector.value) {
      try {
        await igrejaStore.load()
      } catch {
        // lista vazia até auth completa
      }
    }
  },
  { immediate: true },
)

onMounted(() => {
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu') && !e.target.closest('.user-menu-toggle')) {
      userMenuOpen.value = false
    }
  })
})

const logout = async () => {
  if (isAdminArea.value && authAdmin.isAuthenticated) {
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
</script>

<template>
  <header class="top-navbar h-16 border-b flex items-center justify-between px-4 md:px-6">
    <div class="flex items-center gap-3 min-w-0">
      <button
        type="button"
        class="top-navbar__btn p-2 rounded-lg shrink-0"
        aria-label="Alternar menu"
        @click="emit('toggleSidebar')"
      >
        <FontAwesomeIcon :icon="faBars" />
      </button>
      <img
        :src="brandLogo"
        :alt="brandAlt"
        class="h-11 w-11 rounded-lg object-cover shrink-0 bg-white/80"
        data-testid="navbar-brand-logo"
      />
      <div v-if="tenantStore.slug" class="hidden sm:block min-w-0">
        <span class="top-navbar__eyebrow text-xs uppercase tracking-wider font-bold">
          Tenant ativo
        </span>
        <div class="top-navbar__tenant text-sm font-semibold truncate">
          {{ tenantStore.name || tenantStore.slug }}
        </div>
      </div>
      <div
        v-if="showIgrejaSelector && igrejaStore.igrejas.length"
        class="hidden md:flex flex-col min-w-0 ml-2 pl-3 border-l"
        style="border-color: color-mix(in srgb, var(--color-ink) 12%, transparent)"
      >
        <label class="top-navbar__eyebrow text-xs uppercase tracking-wider font-bold" for="igreja-selector">
          Igreja
        </label>
        <select
          id="igreja-selector"
          class="text-sm font-semibold bg-transparent border-0 p-0 pr-6 max-w-[14rem] cursor-pointer"
          style="color: var(--color-ink)"
          data-testid="igreja-selector"
          :value="igrejaStore.currentId"
          @change="onIgrejaChange"
        >
          <option
            v-for="igreja in igrejaStore.igrejas"
            :key="igreja.id"
            :value="igreja.id"
          >
            {{ igreja.nome }}{{ igreja.tipo ? ` (${igrejaTipoLabel(igreja.tipo)})` : '' }}
          </option>
        </select>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <PwaInstallNavButton labeled />
      <div class="relative user-menu">
        <button
          type="button"
          class="user-menu-toggle top-navbar__btn flex items-center gap-2 px-3 py-2 rounded-xl"
          @click="userMenuOpen = !userMenuOpen"
        >
          <span class="text-sm font-medium">{{ displayName }}</span>
          <FontAwesomeIcon :icon="faChevronDown" class="text-xs" />
        </button>
        <div
          v-if="userMenuOpen"
          class="absolute right-0 mt-2 w-48 card py-2 z-50"
        >
          <button
            type="button"
            class="top-navbar__menu-item w-full text-left px-4 py-2 text-sm flex items-center gap-2"
            @click="logout"
          >
            <FontAwesomeIcon :icon="faSignOutAlt" />
            Sair
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.top-navbar {
  background: var(--color-surface);
  border-color: color-mix(in srgb, var(--color-ink) 8%, transparent);
}
.top-navbar__eyebrow {
  color: var(--color-muted);
}
.top-navbar__tenant {
  color: var(--color-ink);
}
.top-navbar__btn {
  color: var(--color-ink);
}
.top-navbar__btn:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}
.top-navbar__menu-item:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}
</style>
