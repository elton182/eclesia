<script setup>
import { ref, onMounted, computed } from 'vue'
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

library.add(faBars, faSignOutAlt, faChevronDown)

const emit = defineEmits(['toggleSidebar'])
const authAdmin = useAuthAdminStore()
const authTenant = useAuthStore()
const tenantStore = useTenantStore()
const router = useRouter()
const route = useRoute()
const userMenuOpen = ref(false)

const displayName = computed(
  () => authTenant.user?.name || authAdmin.user?.name || 'Usuário',
)

const isAdminArea = computed(() => route.path.startsWith('/admin') || route.path.startsWith('/ecc'))

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
    await authAdmin.logout()
    router.push('/admin/login')
    return
  }

  await authTenant.logout()
  router.push('/')
}
</script>

<template>
  <header class="top-navbar h-16 border-b flex items-center justify-between px-4 md:px-6">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="top-navbar__btn p-2 rounded-lg"
        aria-label="Alternar menu"
        @click="emit('toggleSidebar')"
      >
        <FontAwesomeIcon :icon="faBars" />
      </button>
      <div v-if="tenantStore.slug" class="hidden sm:block">
        <span class="top-navbar__eyebrow text-xs uppercase tracking-wider font-bold">
          Tenant ativo
        </span>
        <div class="top-navbar__tenant text-sm font-semibold">
          {{ tenantStore.name || tenantStore.slug }}
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
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
  background: #fff;
  border-color: var(--color-line);
}
.top-navbar__btn {
  color: var(--color-primary);
}
.top-navbar__btn:hover {
  background: var(--color-bg);
}
.top-navbar__eyebrow {
  color: var(--color-accent);
}
.top-navbar__tenant {
  color: var(--color-primary);
}
.top-navbar__menu-item:hover {
  background: var(--color-bg);
}
</style>
