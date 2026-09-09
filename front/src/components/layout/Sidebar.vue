<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faBuilding,
  faUsers,
  faHeart,
  faUserShield,
  faHouse,
} from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useTenantStore } from '@/stores/tenant'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/logo-icon.png'

library.add(faBuilding, faUsers, faHeart, faUserShield, faHouse)

defineProps({
  isOpen: { type: Boolean, default: true },
})

const route = useRoute()
const tenantStore = useTenantStore()
const authAdmin = useAuthAdminStore()
const authTenant = useAuthStore()

const isActive = (path) => route.path === path || route.path.startsWith(path + '/')

const isPlatform = computed(() => authAdmin.isAuthenticated && route.path.startsWith('/admin'))
const isTenantShell = computed(() => authTenant.isAuthenticated && !route.path.startsWith('/admin'))

const platformItems = ref([
  { label: 'Tenants', icon: faBuilding, path: '/admin/tenants' },
])

const tenantItems = computed(() => {
  const home = { label: 'Início', icon: faHouse, path: '/inicio' }
  // Usuários do tenant: login da organização OU super-admin com tenant selecionado
  if (authTenant.isAuthenticated && !route.path.startsWith('/admin')) {
    return [home, { label: 'Usuários', icon: faUserShield, path: '/usuarios' }]
  }
  if (authAdmin.isAuthenticated && tenantStore.slug) {
    return [home, { label: 'Usuários', icon: faUserShield, path: '/usuarios' }]
  }
  return []
})

const eccItems = computed(() => {
  if (!tenantStore.slug || !authAdmin.isAuthenticated) return []
  return [
    { label: 'Equipes', icon: faUsers, path: '/ecc/equipes' },
    { label: 'Casais', icon: faHeart, path: '/ecc/casais' },
  ]
})
</script>

<template>
  <aside
    :class="[
      'transition-all duration-300 ease-in-out overflow-y-auto hidden md:flex flex-col',
      isOpen ? 'w-60' : 'w-20',
    ]"
    style="background: var(--color-primary); color: #F7EDE0"
  >
    <div class="px-4 pt-5 pb-4 flex items-center gap-3 border-b border-white/10">
      <img :src="logoUrl" alt="Eclésia" class="h-10 w-10 rounded-xl object-contain bg-white p-0.5" />
      <div v-if="isOpen" class="min-w-0">
        <div class="font-semibold text-[17px] leading-tight" style="font-family: Fraunces, serif">
          Eclésia
        </div>
        <div class="text-[10px] uppercase tracking-[0.14em] text-[#E7C9A0] font-semibold">
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
