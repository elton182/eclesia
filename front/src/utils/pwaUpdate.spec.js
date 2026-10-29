import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { PWA_UPDATE_CHECK_MS, watchServiceWorkerUpdates } from './pwaUpdate.js'

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
})
