<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faBars,
  faSignOutAlt,
  faChevronDown,
} from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'

library.add(faBars, faSignOutAlt, faChevronDown)

const emit = defineEmits(['toggleSidebar'])
const authStore = useAuthAdminStore()
const tenantStore = useTenantStore()
const router = useRouter()
const userMenuOpen = ref(false)

onMounted(() => {
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu') && !e.target.closest('.user-menu-toggle')) {
      userMenuOpen.value = false
    }
  })
})

const logout = async () => {
  tenantStore.clear()
  await authStore.logout()
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
          <span class="text-sm font-medium">{{ authStore.user?.name || 'Admin' }}</span>
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
  border-color: var(--color-line);
  /* Escopo local: base.css com prefers-color-scheme:dark força texto claro no body */
  color: var(--color-ink);
}

.top-navbar__btn {
  color: var(--color-primary);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.top-navbar__btn:hover {
  background: var(--color-surface-2);
  color: var(--color-primary-dark);
}

.top-navbar__eyebrow {
  color: var(--color-accent);
}

.top-navbar__tenant {
  color: var(--color-primary);
}

.top-navbar__menu-item {
  color: var(--color-ink);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background-color 0.2s;
}

.top-navbar__menu-item:hover {
  background: var(--color-surface-2);
  color: var(--color-primary);
}
</style>
