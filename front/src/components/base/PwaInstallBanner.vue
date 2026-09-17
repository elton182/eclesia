<script setup>
import { usePwaInstall } from '@/composables/usePwaInstall'

const { canInstall, installing, install } = usePwaInstall()

async function onInstall() {
  await install()
}
</script>

<template>
  <div
    v-if="canInstall"
    class="fixed bottom-4 left-4 right-4 z-50 mx-auto flex max-w-md items-center gap-3 rounded-xl px-4 py-3 shadow-lg md:left-auto"
    style="background: var(--color-primary); color: #F7EDE0"
    role="status"
    data-testid="pwa-install-banner"
  >
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold">Instalar Eclésias</p>
      <p class="text-xs opacity-80">Acesse mais rápido, como um app no seu dispositivo.</p>
    </div>
    <button
      type="button"
      class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium disabled:opacity-60"
      style="background: var(--color-accent); color: var(--color-primary)"
      data-testid="pwa-install-button"
      :disabled="installing"
      @click="onInstall"
    >
      {{ installing ? 'Abrindo…' : 'Instalar' }}
    </button>
  </div>
</template>
