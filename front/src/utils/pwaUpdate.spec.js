import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  hardResetAppCache,
  PWA_UPDATE_CHECK_MS,
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
})
