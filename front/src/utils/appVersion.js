/**
 * Versão do front injetada no build (vite.config.js → __ECLESIA_APP_VERSION__).
 * Usada em suporte para confirmar se o aparelho já está no bundle novo.
 */

/** Fallback em testes / ambientes sem define. */
export const APP_VERSION_FALLBACK = 'dev'

/**
 * @returns {string}
 */
export function getAppVersion() {
  try {
    // eslint-disable-next-line no-undef
    if (typeof __ECLESIA_APP_VERSION__ === 'string' && __ECLESIA_APP_VERSION__) {
      // eslint-disable-next-line no-undef
      return __ECLESIA_APP_VERSION__
    }
  } catch {
    // ignore
  }
  return APP_VERSION_FALLBACK
}

/** Versão corrente do bundle (constante no boot). */
export const APP_VERSION = getAppVersion()
