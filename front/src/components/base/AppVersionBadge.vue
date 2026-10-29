<script setup>
import { APP_VERSION } from '@/utils/appVersion'

defineProps({
  /** Prefixo opcional (ex.: "v") — default vazio, mostra o valor cru. */
  prefix: { type: String, default: '' },
})
</script>

<template>
  <span
    class="inline-flex items-center font-sans font-medium tracking-normal normal-case align-middle"
    style="color: var(--color-muted); font-size: 0.55em; letter-spacing: 0.02em"
    :title="`Versão do app: ${APP_VERSION}`"
    data-testid="app-version-badge"
  >
    {{ prefix }}{{ APP_VERSION }}
  </span>
</template>
