/**
 * Utilitários PWA — instalação e estado do app instalado.
 * O evento beforeinstallprompt é capturado em registerInstallPrompt().
 */

/** @type {BeforeInstallPromptEvent | null} */
let deferredPrompt = null

/** @type {Set<(canInstall: boolean) => void>} */
const listeners = new Set()

function notify() {
  const canInstall = Boolean(deferredPrompt)
  for (const listener of listeners) {
    listener(canInstall)
  }
}

/**
 * Indica se a app já roda em modo instalado (standalone / iOS).
 * @returns {boolean}
 */
export function isRunningStandalone() {
  if (typeof window === 'undefined') {
    return false
  }

  const displayModeStandalone = window.matchMedia('(display-mode: standalone)').matches
  const iosStandalone = /** @type {Navigator & { standalone?: boolean }} */ (window.navigator)
    .standalone === true

  return displayModeStandalone || iosStandalone
}

/**
 * Indica se há um prompt de instalação disponível no momento.
 * @returns {boolean}
 */
export function canPromptInstall() {
  return Boolean(deferredPrompt) && !isRunningStandalone()
}

/**
 * Assina mudanças na disponibilidade do prompt de instalação.
 * @param {(canInstall: boolean) => void} listener
 * @returns {() => void} unsubscribe
 */
export function onInstallAvailabilityChange(listener) {
  listeners.add(listener)
  listener(canPromptInstall())
  return () => {
    listeners.delete(listener)
  }
}

/**
 * Registra o listener global de beforeinstallprompt (chamar uma vez no boot).
 * @returns {() => void} cleanup
 */
export function registerInstallPrompt() {
  if (typeof window === 'undefined') {
    return () => {}
  }

  const onBeforeInstall = (event) => {
    event.preventDefault()
    deferredPrompt = /** @type {BeforeInstallPromptEvent} */ (event)
    notify()
  }

  const onInstalled = () => {
    deferredPrompt = null
    notify()
  }

  window.addEventListener('beforeinstallprompt', onBeforeInstall)
  window.addEventListener('appinstalled', onInstalled)

  return () => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstall)
    window.removeEventListener('appinstalled', onInstalled)
  }
}

/**
 * Dispara o prompt nativo de instalação, se disponível.
 * @returns {Promise<'accepted' | 'dismissed' | 'unavailable'>}
 */
export async function promptInstall() {
  if (!deferredPrompt) {
    return 'unavailable'
  }

  const promptEvent = deferredPrompt
  deferredPrompt = null
  notify()

  await promptEvent.prompt()
  const choice = await promptEvent.userChoice
  return choice.outcome === 'accepted' ? 'accepted' : 'dismissed'
}
