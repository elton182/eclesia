import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  canPromptInstall,
  dismissInstallBanner,
  isInstallBannerDismissed,
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
  const bannerDismissed = ref(isInstallBannerDismissed())

  const showBanner = computed(() => canInstall.value && !bannerDismissed.value)

  let unsubscribe = () => {}

  onMounted(() => {
    isInstalled.value = isRunningStandalone()
    bannerDismissed.value = isInstallBannerDismissed()
    unsubscribe = onInstallAvailabilityChange((available) => {
      canInstall.value = available && !isRunningStandalone()
    })
    canInstall.value = canPromptInstall()
  })

  onUnmounted(() => {
    unsubscribe()
  })

  function dismissBanner() {
    dismissInstallBanner()
    bannerDismissed.value = true
  }

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
    showBanner,
    isInstalled,
    installing,
    install,
    dismissBanner,
  }
}
