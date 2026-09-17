import { describe, it, beforeEach, afterEach, mock } from 'node:test'
import assert from 'node:assert/strict'
import {
  isRunningStandalone,
  canPromptInstall,
  registerInstallPrompt,
  promptInstall,
  onInstallAvailabilityChange,
} from './pwa.js'

describe('pwa', () => {
  /** @type {Map<string, Set<EventListener>>} */
  let handlers

  beforeEach(() => {
    handlers = new Map()

    globalThis.window = /** @type {Window & typeof globalThis} */ ({
      matchMedia: (query) => ({
        matches: query.includes('standalone') ? false : false,
        media: query,
        addEventListener() {},
        removeEventListener() {},
      }),
      navigator: { standalone: false },
      addEventListener(type, listener) {
        if (!handlers.has(type)) {
          handlers.set(type, new Set())
        }
        handlers.get(type).add(listener)
      },
      removeEventListener(type, listener) {
        handlers.get(type)?.delete(listener)
      },
    })
  })

  afterEach(() => {
    delete globalThis.window
  })

  it('isRunningStandalone é falso fora do modo instalado', () => {
    assert.equal(isRunningStandalone(), false)
  })

  it('isRunningStandalone detecta display-mode standalone', () => {
    window.matchMedia = (query) => ({
      matches: query === '(display-mode: standalone)',
      media: query,
      addEventListener() {},
      removeEventListener() {},
    })
    assert.equal(isRunningStandalone(), true)
  })

  it('registerInstallPrompt captura beforeinstallprompt e habilita instalação', async () => {
    const cleanup = registerInstallPrompt()
    assert.equal(canPromptInstall(), false)

    let available = false
    const unsub = onInstallAvailabilityChange((can) => {
      available = can
    })

    const prompt = mock.fn(async () => {})
    const userChoice = Promise.resolve({ outcome: 'accepted' })
    const event = {
      preventDefault: mock.fn(),
      prompt,
      userChoice,
    }

    for (const listener of handlers.get('beforeinstallprompt') ?? []) {
      listener(event)
    }

    assert.equal(event.preventDefault.mock.callCount(), 1)
    assert.equal(canPromptInstall(), true)
    assert.equal(available, true)

    const outcome = await promptInstall()
    assert.equal(outcome, 'accepted')
    assert.equal(prompt.mock.callCount(), 1)
    assert.equal(canPromptInstall(), false)

    unsub()
    cleanup()
  })

  it('promptInstall retorna unavailable sem prompt pendente', async () => {
    assert.equal(await promptInstall(), 'unavailable')
  })
})
