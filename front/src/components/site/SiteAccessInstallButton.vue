<script setup>
/**
 * Link compacto do header do site → página /instalar (SPEC-023).
 * A ação de prompt fica na página dedicada (melhor no mobile).
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMobileScreen } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { tenantAccessInstallPath } from '@/utils/tenantAccessManifest'

library.add(faMobileScreen)

const props = defineProps({
  tenantSlug: {
    type: String,
    required: true,
  },
})

const href = computed(() => tenantAccessInstallPath(props.tenantSlug))
</script>

<template>
  <RouterLink
    :to="href"
    class="inline-flex items-center justify-center gap-1.5 shrink-0 rounded-md px-2.5 py-2 text-[13px] font-medium no-underline sm:px-3.5"
    style="border: 1px solid var(--color-line); color: var(--color-ink)"
    data-testid="site-install-link"
    aria-label="Instalar acesso"
    title="Instalar acesso"
  >
    <FontAwesomeIcon :icon="faMobileScreen" class="text-[13px]" />
    <span class="hidden sm:inline">Instalar</span>
  </RouterLink>
</template>
