<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'

const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()

const displayName = computed(() => {
  return (
    authTenant.user?.name ||
    authAdmin.user?.name ||
    authAdmin.user?.email ||
    'bem-vindo(a)'
  )
})

const orgName = computed(() => tenantStore.name || tenantStore.slug || '')
</script>

<template>
  <div data-testid="welcome-page" class="max-w-2xl">
    <p class="page-eyebrow">Início</p>
    <h2 class="text-3xl mt-1" style="color: var(--color-primary); font-family: Fraunces, serif">
      Olá, {{ displayName }}
    </h2>
    <p class="mt-3 text-[15px] leading-relaxed" style="color: var(--color-muted)">
      Bem-vindo(a) ao Eclésia
      <template v-if="orgName">
        — <span class="font-medium" style="color: var(--color-ink)">{{ orgName }}</span>
      </template>
      .
    </p>
    <p class="mt-2 text-[15px] leading-relaxed" style="color: var(--color-muted)">
      Use o menu ao lado para acessar as áreas disponíveis para o seu perfil.
    </p>
  </div>
</template>
