import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  BUNDLE_RELOAD_GUARD_KEY,
  hardResetAppCache,
  installBundleRecovery,
  isBundleLoadError,
  PWA_UPDATE_CHECK_MS,
  recoverFromBrokenBundle,
  watchServiceWorkerUpdates,
} from './pwaUpdate.js'

describe('pwaUpdate', () => {
  it('não agenda nada sem registration', () => {
    const cleanup = watchServiceWorkerUpdates(null)
    assert.equal(typeof cleanup, 'function')
    cleanup()
  })

  it('chama update no intervalo e no focus/visibility', () => {
    let updateCount = 0
    const registration = {
      update() {
        updateCount += 1
      },
    }

    /** @type {Array<() => void>} */
    const intervals = []
    const listeners = new Map()

    const cleanup = watchServiceWorkerUpdates(registration, {
      intervalMs: 1000,
      setIntervalFn: (fn) => {
        intervals.push(fn)
        return 1
      },
      addEventListenerFn: (type, fn) => {
        listeners.set(type, fn)
      },
    })

    assert.equal(intervals.length, 1)
    assert.ok(listeners.has('visibilitychange'))
    assert.ok(listeners.has('focus'))

    intervals[0]()
    assert.equal(updateCount, 1)

    listeners.get('focus')()
    assert.equal(updateCount, 2)

    cleanup()
    assert.equal(PWA_UPDATE_CHECK_MS, 5 * 60 * 1000)
  })

  it('hardResetAppCache desregistra SW, limpa caches e recarrega', async () => {
    let reloadCount = 0
    const unregistered = []
    const deleted = []

    const result = await hardResetAppCache({
      swContainer: {
        getRegistrations: async () => [
          {
            unregister: async () => {
              unregistered.push('a')
              return true
            },
          },
          {
            unregister: async () => {
              unregistered.push('b')
              return true
            },
          },
        ],
      },
      cachesApi: {
        keys: async () => ['workbox-precache', 'eclesia-fonts'],
        delete: async (key) => {
          deleted.push(key)
          return true
        },
      },
      reload: () => {
        reloadCount += 1
      },
    })

    assert.deepEqual(unregistered, ['a', 'b'])
    assert.deepEqual(deleted, ['workbox-precache', 'eclesia-fonts'])
    assert.equal(reloadCount, 1)
    assert.deepEqual(result, { unregistered: 2, cachesCleared: 2 })
  })

  it('detecta erros típicos de bundle/chunk stale', () => {
    assert.equal(
      isBundleLoadError(new Error('Failed to fetch dynamically imported module: https://x/a.js')),
      true,
    )
    assert.equal(isBundleLoadError(new Error('Failed to load module script')), true)
    assert.equal(isBundleLoadError(new Error('network timeout')), false)
  })

  it('recoverFromBrokenBundle só tenta uma vez por sessão', async () => {
    const store = new Map()
    const sessionStorage = {
      getItem: (k) => (store.has(k) ? store.get(k) : null),
      setItem: (k, v) => {
        store.set(k, String(v))
      },
    }
    let reloads = 0
    const first = await recoverFromBrokenBundle({
      sessionStorage,
      swContainer: { getRegistrations: async () => [] },
      cachesApi: { keys: async () => [], delete: async () => true },
      reload: () => {
        reloads += 1
      },
    })
    assert.equal(first.recovered, true)
    assert.equal(store.get(BUNDLE_RELOAD_GUARD_KEY), '1')
    assert.equal(reloads, 1)

    const second = await recoverFromBrokenBundle({
      sessionStorage,
      swContainer: { getRegistrations: async () => [] },
      cachesApi: { keys: async () => [], delete: async () => true },
      reload: () => {
        reloads += 1
      },
    })
    assert.equal(second.recovered, false)
    assert.equal(second.reason, 'already-tried')
    assert.equal(reloads, 1)
  })

  it('installBundleRecovery reage a vite:preloadError', async () => {
    const listeners = new Map()
    const store = new Map()
    let reloads = 0
    const fakeWindow = {
      addEventListener: (type, fn) => {
        listeners.set(type, fn)
      },
      removeEventListener: (type) => {
        listeners.delete(type)
      },
    }

    const cleanup = installBundleRecovery({
      window: fakeWindow,
      sessionStorage: {
        getItem: (k) => (store.has(k) ? store.get(k) : null),
        setItem: (k, v) => {
          store.set(k, String(v))
        },
      },
      swContainer: { getRegistrations: async () => [] },
      cachesApi: { keys: async () => [], delete: async () => true },
      reload: () => {
        reloads += 1
      },
    })

    assert.ok(listeners.has('vite:preloadError'))
    listeners.get('vite:preloadError')({ preventDefault() {} })
    await new Promise((r) => setTimeout(r, 0))
    assert.equal(reloads, 1)
    cleanup()
  })
})
