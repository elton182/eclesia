<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { usePwaInstall } from '@/composables/usePwaInstall'

library.add(faTimes)

const route = useRoute()
const { showBanner, installing, install, dismissBanner } = usePwaInstall()

/** No site público o CTA é o atalho da paróquia (SPEC-023), não o PWA genérico. */
const onPublicSite = computed(() => String(route.path || '').startsWith('/site/'))
const visible = computed(() => showBanner.value && !onPublicSite.value)

async function onInstall() {
  await install()
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed bottom-4 left-4 right-4 z-50 mx-auto flex max-w-md items-center gap-3 rounded-xl px-4 py-3 shadow-lg md:left-auto"
    style="background: var(--color-primary); color: #F7EDE0"
    role="status"
    data-testid="pwa-install-banner"
  >
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold">Instalar Apostolus</p>
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
    <button
      type="button"
      class="shrink-0 rounded-lg p-2 opacity-80 hover:opacity-100"
      aria-label="Fechar banner de instalação"
      data-testid="pwa-install-dismiss"
      @click="dismissBanner"
    >
      <FontAwesomeIcon :icon="faTimes" />
    </button>
  </div>
</template>
