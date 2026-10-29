/**
 * Permissões de câmera/mídia no browser — para orientar quando o SO negou
 * e o picker de foto “não faz nada” (comum em Android/PWA).
 */

/** @typedef {'granted' | 'denied' | 'prompt' | 'unknown'} MediaPermissionState */

export const CAMERA_PERMISSION_HELP =
  'A permissão de câmera deste aparelho está desativada para o app.\n\n'
  + 'Em Ajustes → Apps → Apostolus (ou Chrome) → Permissões, ative Câmera '
  + '(e Fotos/arquivos, se aparecer). Depois abra o app de novo e tente outra vez.'

export const GALLERY_PERMISSION_HELP =
  'O acesso a fotos/arquivos pode estar desativado para o app.\n\n'
  + 'Em Ajustes → Apps → Apostolus (ou Chrome) → Permissões, ative Fotos e vídeos '
  + '(ou Arquivos/armazenamento). Depois abra o app de novo e tente outra vez.'

/**
 * Consulta o estado da permissão de câmera (Permissions API).
 * @param {{ permissions?: Permissions | null }} [opts]
 * @returns {Promise<MediaPermissionState>}
 */
export async function queryCameraPermission(opts = {}) {
  const permissions =
    opts.permissions !== undefined
      ? opts.permissions
      : (typeof navigator !== 'undefined' ? navigator.permissions : null)

  if (!permissions || typeof permissions.query !== 'function') {
    return 'unknown'
  }

  try {
    const status = await permissions.query({ name: /** @type {PermissionName} */ ('camera') })
    const state = String(status?.state || '')
    if (state === 'granted' || state === 'denied' || state === 'prompt') {
      return state
    }
    return 'unknown'
  } catch {
    return 'unknown'
  }
}

/**
 * Confirma negação de câmera com getUserMedia (quando a Permissions API diz denied
 * ou é inconclusiva e queremos um probe leve). Não deixa o stream aberto.
 *
 * @param {{
 *   mediaDevices?: MediaDevices | null,
 *   permissions?: Permissions | null,
 *   probeIfUnknown?: boolean,
 * }} [opts]
 * @returns {Promise<{ state: MediaPermissionState, blocked: boolean, help: string }>}
 */
export async function assessCameraAccess(opts = {}) {
  const state = await queryCameraPermission({ permissions: opts.permissions })
  if (state === 'denied') {
    return { state, blocked: true, help: CAMERA_PERMISSION_HELP }
  }
  // `prompt` NÃO significa “pode usar”: no Android/Chrome o estado muitas vezes
  // permanece prompt após negar o diálogo do <input capture>, e o picker some
  // em silêncio. Não bloqueamos aqui — o caller detecta falha sem `change`.
  if (state === 'granted' || state === 'prompt') {
    return { state, blocked: false, help: CAMERA_PERMISSION_HELP }
  }

  if (!opts.probeIfUnknown) {
    return { state: 'unknown', blocked: false, help: CAMERA_PERMISSION_HELP }
  }

  const mediaDevices =
    opts.mediaDevices !== undefined
      ? opts.mediaDevices
      : (typeof navigator !== 'undefined' ? navigator.mediaDevices : null)

  if (!mediaDevices || typeof mediaDevices.getUserMedia !== 'function') {
    return { state: 'unknown', blocked: false, help: CAMERA_PERMISSION_HELP }
  }

  try {
    const stream = await mediaDevices.getUserMedia({
      video: { facingMode: 'user' },
      audio: false,
    })
    for (const track of stream.getTracks()) {
      track.stop()
    }
    return { state: 'granted', blocked: false, help: CAMERA_PERMISSION_HELP }
  } catch (err) {
    const name = err && typeof err === 'object' ? String(err.name || '') : ''
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
      return { state: 'denied', blocked: true, help: CAMERA_PERMISSION_HELP }
    }
    // NotFoundError etc. — aparelho sem câmera; não bloquear o file input
    return { state: 'unknown', blocked: false, help: CAMERA_PERMISSION_HELP }
  }
}

/**
 * Galeria: Permissions API quase não cobre “fotos”. Só bloqueamos se a câmera
 * também estiver denied E o caller pedir ( indício forte de permissões do site ).
 * Por padrão não bloqueia a galeria — só devolve texto de ajuda.
 *
 * @returns {Promise<{ blocked: boolean, help: string }>}
 */
export async function assessGalleryAccess() {
  return { blocked: false, help: GALLERY_PERMISSION_HELP }
}
