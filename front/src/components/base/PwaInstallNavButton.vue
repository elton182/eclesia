<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDownload } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { usePwaInstall } from '@/composables/usePwaInstall'

library.add(faDownload)

defineProps({
  /** Exibe o rótulo “Instalar” ao lado do ícone */
  labeled: { type: Boolean, default: false },
  /** Estilo para barras escuras (launcher) */
  dark: { type: Boolean, default: false },
})

const { canInstall, installing, install } = usePwaInstall()
</script>

<template>
  <button
    v-if="canInstall"
    type="button"
    class="pwa-install-nav shrink-0 inline-flex items-center gap-1.5 rounded-lg disabled:opacity-60"
    :class="[
      labeled ? 'px-2.5 py-1.5 text-[13px] font-medium' : 'p-2',
      dark ? 'pwa-install-nav--dark' : 'pwa-install-nav--light',
    ]"
    aria-label="Instalar aplicativo"
    title="Instalar aplicativo"
    data-testid="pwa-install-navbar"
    :disabled="installing"
    @click="install"
  >
    <FontAwesomeIcon :icon="faDownload" />
    <span v-if="labeled">{{ installing ? 'Abrindo…' : 'Instalar' }}</span>
  </button>
</template>

<style scoped>
.pwa-install-nav--light {
  color: var(--color-ink);
}
.pwa-install-nav--light:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}
.pwa-install-nav--dark {
  color: #f0d8c2;
}
.pwa-install-nav--dark:hover {
  background: rgba(255, 253, 250, 0.12);
}
</style>
