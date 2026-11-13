/**
 * Atualização do service worker — evita PWA mobile presa em bundle antigo
 * (ex.: botões de foto que “não fazem nada” após um deploy).
 *
 * Após deploy com rsync --delete, um index.html antigo no Cache Storage pode
 * apontar para JS/CSS já removidos → tela branca (comum em Samsung Internet /
 * deep links do WhatsApp). Ver recoverFromBrokenBundle.
 */

/** Intervalo padrão entre checks de update (ms). */
export const PWA_UPDATE_CHECK_MS = 5 * 60 * 1000

/** Evita loop de reload quando o bundle continua quebrado. */
export const BUNDLE_RELOAD_GUARD_KEY = 'eclesia_bundle_reload'

/**
 * Erros típicos de shell/chunk stale (MIME text/html, 404 de hash antigo, etc.).
 * @param {unknown} error
 */
export function isBundleLoadError(error) {
  const msg = String(
    (error && typeof error === 'object' && 'message' in error
      ? /** @type {{ message?: unknown }} */ (error).message
      : error) ?? '',
  )
  if (!msg) return false
  return (
    /Failed to fetch dynamically imported module/i.test(msg) ||
    /error loading dynamically imported module/i.test(msg) ||
    /Importing a module script failed/i.test(msg) ||
    /Failed to load module script/i.test(msg) ||
    /Expected a JavaScript/i.test(msg) ||
    /Loading chunk [\w-]+ failed/i.test(msg)
  )
}

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

/**
 * Limpa SW/caches e recarrega uma única vez por aba (sessionStorage).
 * @param {{
 *   sessionStorage?: Storage | null,
 *   cachesApi?: CacheStorage | null,
 *   swContainer?: ServiceWorkerContainer | null,
 *   reload?: () => void,
 * }} [opts]
 * @returns {Promise<{ recovered: boolean, reason?: string, unregistered?: number, cachesCleared?: number }>}
 */
export async function recoverFromBrokenBundle(opts = {}) {
  const storage =
    opts.sessionStorage !== undefined
      ? opts.sessionStorage
      : typeof sessionStorage !== 'undefined'
        ? sessionStorage
        : null

  if (storage?.getItem(BUNDLE_RELOAD_GUARD_KEY)) {
    return { recovered: false, reason: 'already-tried' }
  }

  try {
    storage?.setItem(BUNDLE_RELOAD_GUARD_KEY, '1')
  } catch {
    // private mode / quota — segue o reset mesmo assim
  }

  const result = await hardResetAppCache(opts)
  return { recovered: true, ...result }
}

/**
 * Escuta falhas de módulo/chunk e tenta recuperar automaticamente.
 * @param {{
 *   window?: Window | null,
 *   sessionStorage?: Storage | null,
 *   cachesApi?: CacheStorage | null,
 *   swContainer?: ServiceWorkerContainer | null,
 *   reload?: () => void,
 * }} [opts]
 * @returns {() => void} cleanup
 */
export function installBundleRecovery(opts = {}) {
  const win =
    opts.window !== undefined
      ? opts.window
      : typeof window !== 'undefined'
        ? window
        : null
  if (!win?.addEventListener) return () => {}

  let recovering = false
  const tryRecover = (error, { force = false } = {}) => {
    if (recovering) return
    if (!force && error != null && !isBundleLoadError(error)) return
    recovering = true
    void recoverFromBrokenBundle(opts).finally(() => {
      recovering = false
    })
  }

  const onPreloadError = (event) => {
    try {
      event.preventDefault?.()
    } catch {
      // ignore
    }
    // vite:preloadError = chunk/hash stale — sempre tenta recuperar
    tryRecover(event?.payload ?? event?.message ?? event, { force: true })
  }

  const onUnhandledRejection = (event) => {
    tryRecover(event?.reason)
  }

  const onError = (event) => {
    const target = event?.target
    if (target && target.tagName === 'SCRIPT') {
      tryRecover(event?.error ?? event?.message ?? 'Failed to load module script', {
        force: true,
      })
      return
    }
    tryRecover(event?.error)
  }

  win.addEventListener('vite:preloadError', onPreloadError)
  win.addEventListener('unhandledrejection', onUnhandledRejection)
  win.addEventListener('error', onError, true)

  return () => {
    win.removeEventListener('vite:preloadError', onPreloadError)
    win.removeEventListener('unhandledrejection', onUnhandledRejection)
    win.removeEventListener('error', onError, true)
  }
}
