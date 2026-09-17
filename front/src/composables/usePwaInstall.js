import { onMounted, onUnmounted, ref } from 'vue'
import {
  canPromptInstall,
  isRunningStandalone,
  onInstallAvailabilityChange,
  promptInstall,
} from '@/utils/pwa'

/**
 * Composable para UI de “Instalar app”.
 */
export function usePwaInstall() {
  const canInstall = ref(false)
  const isInstalled = ref(isRunningStandalone())
  const installing = ref(false)

  let unsubscribe = () => {}

  onMounted(() => {
    isInstalled.value = isRunningStandalone()
    unsubscribe = onInstallAvailabilityChange((available) => {
      canInstall.value = available && !isRunningStandalone()
    })
    canInstall.value = canPromptInstall()
  })

  onUnmounted(() => {
    unsubscribe()
  })

  async function install() {
    if (!canInstall.value || installing.value) {
      return 'unavailable'
    }
    installing.value = true
    try {
      const outcome = await promptInstall()
      if (outcome === 'accepted') {
        isInstalled.value = true
        canInstall.value = false
      }
      return outcome
    } finally {
      installing.value = false
    }
  }

  return {
    canInstall,
    isInstalled,
    installing,
    install,
  }
}
