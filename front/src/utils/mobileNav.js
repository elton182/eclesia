/**
 * Classes do aside de navegação: coluna no desktop e drawer no mobile.
 *
 * @param {{ open: boolean, widthClass?: string, gapClass?: string }} opts
 * @returns {string}
 */
export function asideNavClasses({ open, widthClass = 'w-[214px]', gapClass = 'gap-5' } = {}) {
  const parts = [
    'flex flex-col shrink-0',
    gapClass,
    widthClass,
    'fixed inset-y-0 left-0 z-50 max-md:shadow-xl',
    'transition-transform duration-200 ease-out',
    'md:relative md:translate-x-0 md:z-0 md:shadow-none',
    open ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
  ]
  return parts.join(' ')
}

/**
 * Classes do rail AdminLayout (colapsável no desktop, drawer no mobile).
 *
 * @param {{ open: boolean }} opts
 * @returns {string}
 */
export function adminAsideClasses({ open } = { open: false }) {
  const parts = [
    'flex flex-col overflow-y-auto',
    'fixed left-0 top-16 z-40 h-[calc(100vh-4rem)]',
    'max-md:shadow-xl transition-all duration-300 ease-in-out',
    open ? 'translate-x-0 w-60' : '-translate-x-full w-60 md:translate-x-0 md:w-20',
  ]
  return parts.join(' ')
}
