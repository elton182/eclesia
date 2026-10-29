/**
 * Atualização do service worker — evita PWA mobile presa em bundle antigo
 * (ex.: botões de foto que “não fazem nada” após um deploy).
 */

/** Intervalo padrão entre checks de update (ms). */
export const PWA_UPDATE_CHECK_MS = 5 * 60 * 1000

/**
 * Desregistra service workers, limpa Cache Storage e recarrega a página.
 * Usado no menu do perfil (“Atualizar app”) para forçar o bundle novo.
 *
 * @param {{
 *   cachesApi?: CacheStorage | null,
 *   swContainer?: ServiceWorkerContainer | null,
 *   reload?: () => void,
 * }} [opts]
 * @returns {Promise<{ unregistered: number, cachesCleared: number }>}
 */
export async function hardResetAppCache(opts = {}) {
  const cachesApi =
    opts.cachesApi !== undefined
      ? opts.cachesApi
      : (typeof caches !== 'undefined' ? caches : null)
  const swContainer =
    opts.swContainer !== undefined
      ? opts.swContainer
      : (typeof navigator !== 'undefined' ? navigator.serviceWorker : null)
  const reload =
    opts.reload
    ?? (() => {
      if (typeof window !== 'undefined') {
        window.location.reload()
      }
    })

  let unregistered = 0
  if (swContainer?.getRegistrations) {
    const regs = await swContainer.getRegistrations()
    await Promise.all(
      regs.map(async (reg) => {
        try {
          const ok = await reg.unregister()
          if (ok) unregistered += 1
        } catch {
          // ignore
        }
      }),
    )
  }

  let cachesCleared = 0
  if (cachesApi?.keys) {
    const keys = await cachesApi.keys()
    await Promise.all(
      keys.map(async (key) => {
        try {
          const ok = await cachesApi.delete(key)
          if (ok) cachesCleared += 1
        } catch {
          // ignore
        }
      }),
    )
  }

  reload()
  return { unregistered, cachesCleared }
}

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
