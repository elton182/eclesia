/**
 * Fluxo /site/{slug}/instalar (SPEC-023):
 * 1) login com org amarrada ao slug
 * 2) após sessão válida, vai ao app (onde o beforeinstallprompt da plataforma funciona)
 */

export const PENDING_PWA_INSTALL_KEY = 'eclesia_pending_pwa_install'

/**
 * @param {{ authenticated?: boolean, mustChangePassword?: boolean }} state
 * @returns {'login' | 'go-app-install' | 'change-password'}
 */
export function resolveInstallPageStep(state = {}) {
  if (state.mustChangePassword) {
    return 'change-password'
  }
  if (state.authenticated) {
    return 'go-app-install'
  }
  return 'login'
}

export function markPendingPwaInstall() {
  if (typeof sessionStorage === 'undefined') return
  sessionStorage.setItem(PENDING_PWA_INSTALL_KEY, '1')
}

export function clearPendingPwaInstall() {
  if (typeof sessionStorage === 'undefined') return
  sessionStorage.removeItem(PENDING_PWA_INSTALL_KEY)
}

/**
 * @param {Record<string, unknown>|null|undefined} query
 * @returns {boolean}
 */
export function shouldOfferAppInstall(query) {
  if (query?.instalar === '1' || query?.instalar === true || query?.instalar === 1) {
    return true
  }
  if (typeof sessionStorage === 'undefined') return false
  return sessionStorage.getItem(PENDING_PWA_INSTALL_KEY) === '1'
}

/**
 * @param {string} homePath
 * @returns {{ path: string, query: { instalar: string } }}
 */
export function appInstallRoute(homePath) {
  return {
    path: homePath || '/inicio',
    query: { instalar: '1' },
  }
}
