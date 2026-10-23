/**
 * Passos da tela /site/{slug}/instalar (SPEC-023).
 * Login primeiro; instalação só após sessão válida.
 */

/**
 * @param {{ authenticated?: boolean, mustChangePassword?: boolean }} state
 * @returns {'login' | 'install' | 'change-password'}
 */
export function resolveInstallPageStep(state = {}) {
  if (state.mustChangePassword) {
    return 'change-password'
  }
  if (state.authenticated) {
    return 'install'
  }
  return 'login'
}
