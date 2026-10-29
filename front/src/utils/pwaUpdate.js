/**
 * Atualização do service worker — evita PWA mobile presa em bundle antigo
 * (ex.: botões de foto que “não fazem nada” após um deploy).
 */

/** Intervalo padrão entre checks de update (ms). */
export const PWA_UPDATE_CHECK_MS = 5 * 60 * 1000

/**
 * Agenda registration.update() periódico e ao voltar para a aba.
 * @param {ServiceWorkerRegistration | null | undefined} registration
 * @param {{ intervalMs?: number, now?: () => number, setIntervalFn?: typeof setInterval, addEventListenerFn?: typeof window.addEventListener }} [opts]
 * @returns {() => void} cleanup
 */
export function watchServiceWorkerUpdates(registration, opts = {}) {
  if (!registration || typeof registration.update !== 'function') {
    return () => {}
  }

  const intervalMs = opts.intervalMs ?? PWA_UPDATE_CHECK_MS
  const setIntervalFn = opts.setIntervalFn ?? setInterval
  const addEventListenerFn =
    opts.addEventListenerFn
    ?? (typeof window !== 'undefined' ? window.addEventListener.bind(window) : null)
  const removeEventListenerFn =
    typeof window !== 'undefined' ? window.removeEventListener.bind(window) : null

  const check = () => {
    try {
      registration.update()
    } catch {
      // ignore
    }
  }

  const timer = setIntervalFn(check, intervalMs)

  const onVisible = () => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
    check()
  }

  if (addEventListenerFn) {
    addEventListenerFn('visibilitychange', onVisible)
    addEventListenerFn('focus', onVisible)
  }

  return () => {
    clearInterval(timer)
    if (removeEventListenerFn) {
      removeEventListenerFn('visibilitychange', onVisible)
      removeEventListenerFn('focus', onVisible)
    }
  }
}
