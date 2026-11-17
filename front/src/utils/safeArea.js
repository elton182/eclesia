/**
 * Classes CSS de safe-area (iOS PWA / notch / Dynamic Island).
 * Definidas em `assets/main.css`. Requer `viewport-fit=cover` no HTML.
 *
 * Com `apple-mobile-web-app-status-bar-style=black-translucent`, o conteúdo
 * desenha sob a bandeja — o padding deve ir no próprio header colorido.
 */
export const SAFE_AREA_CLASSES = Object.freeze({
  /** padding-top: env(safe-area-inset-top) */
  padTop: 'safe-pt',
  /** padding-bottom: env(safe-area-inset-bottom) */
  padBottom: 'safe-pb',
  /** Header h-14 + inset superior (fundo estende sob a bandeja) */
  header14: 'safe-header-14',
  /** Header h-16 + inset superior */
  header16: 'safe-header-16',
  /** pt-4 + inset (launcher mobile) */
  padTop4: 'safe-pt-4',
  /** Aside drawer: max(1.25rem, inset) */
  asidePadTop: 'safe-aside-pt',
  /** top: 4rem + inset (admin fixed offset) */
  offsetTop16: 'safe-offset-top-16',
  /** padding-top: 4rem + inset (admin content) */
  padOffsetTop16: 'safe-pad-top-16',
  /** altura admin aside: 100dvh − 4rem − inset */
  adminAsideHeight: 'safe-admin-aside-h',
  /** min-height: 100dvh com fallback 100vh (evita scroll fantasma no iOS) */
  minHeightApp: 'min-h-app',
  /** main líder: 72px + home indicator */
  liderMainPadBottom: 'safe-lider-main-pb',
})

/**
 * @param {boolean} hasTopBanner — ex.: ViewAsLiderBanner já consumiu o inset
 * @returns {string} classes do header de módulo
 */
export function moduleHeaderSafeClass(hasTopBanner = false) {
  return hasTopBanner ? '' : SAFE_AREA_CLASSES.header14
}

/**
 * @param {boolean} hasTopBanner
 * @returns {string} classes extras do aside (além de asideNavClasses)
 */
export function moduleAsideSafePadClass(hasTopBanner = false) {
  return hasTopBanner
    ? 'px-3.5 py-5'
    : `px-3.5 pb-5 ${SAFE_AREA_CLASSES.asidePadTop}`
}
