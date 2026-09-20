<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { useBrandingStore } from '@/stores/branding'

const tenantStore = useTenantStore()
const branding = useBrandingStore()

onMounted(async () => {
  if (tenantStore.slug) {
    await branding.ensureLoaded()
  }
})
</script>

<template>
  <div class="min-h-screen" style="background: var(--color-bg)">
    <RouterView />
  </div>
</template>
