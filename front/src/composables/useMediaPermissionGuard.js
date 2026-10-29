import { onMounted, onUnmounted, ref } from 'vue'
import { innovAlert } from '@/plugins/dialog'
import { innovToast } from '@/plugins/toast'
import {
  assessCameraAccess,
  CAMERA_PERMISSION_HELP,
} from '@/utils/mediaPermissions'
import { trackDiagnostic } from '@/utils/clientDiagnostics'

/**
 * Bloqueia “Tirar foto” com aviso quando a Permissions API reporta denied.
 *
 * Em Android/PWA a API frequentemente fica em `prompt` mesmo após o usuário
 * negar o diálogo do capture — aí o clique passa (blocked:false) e o SO
 * engole o picker. O fallback + onSilentFail cobrem esse caso.
 */
export function useMediaPermissionGuard() {
  const cameraDenied = ref(false)
  /** @type {import('vue').Ref<string>} */
  const cameraPermission = ref('unknown')
  let galleryTipShown = false
  let refreshTimer = 0

  async function refreshCameraPermission() {
    const result = await assessCameraAccess({ probeIfUnknown: false })
    cameraDenied.value = result.blocked
    cameraPermission.value = result.state
    return result
  }

  function schedulePermissionRefresh() {
    if (typeof window === 'undefined') return
    if (refreshTimer) clearTimeout(refreshTimer)
    refreshTimer = window.setTimeout(() => {
      refreshCameraPermission()
    }, 800)
  }

  function onVisibilityOrFocus() {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
    refreshCameraPermission()
  }

  /**
   * Handler de click no input capture — deve permanecer majoritariamente síncrono.
   * @param {Event} event
   */
  function guardCameraClick(event) {
    if (!cameraDenied.value) {
      trackDiagnostic('foto.camera.click', {
        blocked: false,
        permission: cameraPermission.value,
      })
      schedulePermissionRefresh()
      return
    }

    event.preventDefault()
    event.stopPropagation()
    trackDiagnostic('foto.camera.click', {
      blocked: true,
      reason: 'permission_denied',
      permission: cameraPermission.value,
    })
    void innovAlert({
      title: 'Câmera bloqueada',
      message: CAMERA_PERMISSION_HELP,
      confirmText: 'Entendi',
    })
  }

  /**
   * @param {Event} [_event]
   */
  function guardGalleryClick(_event) {
    trackDiagnostic('foto.gallery.click', {
      camera_denied: cameraDenied.value,
      permission: cameraPermission.value,
    })
    if (cameraDenied.value && !galleryTipShown) {
      galleryTipShown = true
      innovToast(
        'info',
        'Permissões',
        'Se a galeria não abrir, ative Fotos/arquivos em Ajustes → Apps → Permissões.',
      )
    }
    schedulePermissionRefresh()
  }

  onMounted(() => {
    refreshCameraPermission()
    if (typeof window !== 'undefined') {
      window.addEventListener('focus', onVisibilityOrFocus)
      document.addEventListener('visibilitychange', onVisibilityOrFocus)
    }
  })

  onUnmounted(() => {
    if (refreshTimer) clearTimeout(refreshTimer)
    if (typeof window !== 'undefined') {
      window.removeEventListener('focus', onVisibilityOrFocus)
      document.removeEventListener('visibilitychange', onVisibilityOrFocus)
    }
  })

  return {
    cameraDenied,
    cameraPermission,
    refreshCameraPermission,
    guardCameraClick,
    guardGalleryClick,
  }
}
