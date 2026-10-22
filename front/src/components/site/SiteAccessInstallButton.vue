<script setup>
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDownload } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { usePwaInstall } from '@/composables/usePwaInstall'
import { isRunningStandalone } from '@/utils/pwa'

library.add(faDownload)

const props = defineProps({
  orgName: {
    type: String,
    default: 'paróquia',
  },
})

const { canInstall, installing, isInstalled, install } = usePwaInstall()

const standalone = computed(() => isInstalled.value || isRunningStandalone())

const hint = computed(() => {
  if (standalone.value) {
    return 'Acesso já instalado neste dispositivo.'
  }
  if (canInstall.value) {
    return `Instale o acesso de ${props.orgName} e entre sem digitar o código.`
  }
  return 'No menu do navegador, use “Adicionar à tela inicial” para fixar o acesso.'
})

async function onInstall() {
  await install()
}
</script>

<template>
  <div class="flex flex-col items-end gap-1 min-w-0" data-testid="site-access-install">
    <button
      v-if="canInstall && !standalone"
      type="button"
      class="inline-flex items-center gap-1.5 no-underline px-3.5 py-2 rounded-md font-medium text-[13px] shrink-0 disabled:opacity-60"
      style="background: var(--color-primary-soft); color: var(--color-surface)"
      data-testid="site-access-install-button"
      :disabled="installing"
      @click="onInstall"
    >
      <FontAwesomeIcon :icon="faDownload" class="text-[12px]" />
      {{ installing ? 'Abrindo…' : 'Instalar acesso' }}
    </button>
    <p
      class="text-[11px] leading-snug max-w-[220px] text-right md:max-w-[260px]"
      style="color: color-mix(in srgb, var(--color-ink) 55%, transparent)"
      data-testid="site-access-install-hint"
    >
      {{ hint }}
    </p>
  </div>
</template>
