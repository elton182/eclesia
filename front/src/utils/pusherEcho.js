/**
 * Laravel Echo + Pusher Channels (SPEC-030).
 * Só inicializa se VITE_PUSHER_APP_KEY estiver definido.
 */

let echoInstance = null

export function pusherConfigured() {
  return Boolean(import.meta.env.VITE_PUSHER_APP_KEY)
}

/**
 * @param {{ authEndpoint?: string, authHeaders?: Record<string, string>, userUlid?: string }} opts
 */
export async function getEcho(opts = {}) {
  if (!pusherConfigured()) return null
  if (echoInstance) return echoInstance

  const [{ default: Echo }, { default: Pusher }] = await Promise.all([
    import('laravel-echo'),
    import('pusher-js'),
  ])

  window.Pusher = Pusher

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    forceTLS: true,
    authEndpoint: opts.authEndpoint
      || `${String(import.meta.env.VITE_API_URL || '').replace(/\/$/, '')}/api/v1/broadcasting/auth`,
    auth: {
      headers: opts.authHeaders || {},
    },
  })

  return echoInstance
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

/**
 * @param {string} userUlid
 * @param {(payload: object) => void} onEnvio
 * @param {{ authHeaders?: Record<string, string> }} [opts]
 */
export async function listenPerguntaEnvio(userUlid, onEnvio, opts = {}) {
  if (!userUlid || !pusherConfigured()) return () => {}

  const echo = await getEcho(opts)
  if (!echo) return () => {}

  const channel = echo.private(`eclesia.user.${userUlid}`)
  channel.listen('.pergunta.envio.criado', (payload) => {
    onEnvio?.(payload)
  })

  return () => {
    echo.leave(`eclesia.user.${userUlid}`)
  }
}
