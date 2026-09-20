import { useDialogStore } from '../stores/dialog.js'

/**
 * @param {{
 *   title?: string,
 *   message?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 *   danger?: boolean,
 * }|string} options
 * @param {string} [message]
 * @returns {Promise<boolean>}
 */
export function innovConfirm(options, message) {
  const opts =
    typeof options === 'string'
      ? { title: options, message: message || '' }
      : { ...options }

  return /** @type {Promise<boolean>} */ (
    useDialogStore().show({
      type: 'confirm',
      title: opts.title,
      message: opts.message,
      confirmText: opts.confirmText,
      cancelText: opts.cancelText,
      danger: opts.danger ?? true,
    })
  )
}

/**
 * @param {{
 *   title?: string,
 *   message?: string,
 *   label?: string,
 *   placeholder?: string,
 *   defaultValue?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 * }|string} options
 * @param {string} [message]
 * @returns {Promise<string|null>}
 */
export function innovPrompt(options, message) {
  const opts =
    typeof options === 'string'
      ? { title: options, message: message || '', label: options }
      : { ...options }

  return /** @type {Promise<string|null>} */ (
    useDialogStore().show({
      type: 'prompt',
      title: opts.title || 'Informe',
      message: opts.message,
      label: opts.label || opts.title || 'Valor',
      placeholder: opts.placeholder,
      defaultValue: opts.defaultValue,
      confirmText: opts.confirmText || 'Salvar',
      cancelText: opts.cancelText,
      danger: false,
    })
  )
}

/**
 * @param {{ title?: string, message?: string, confirmText?: string }|string} options
 * @param {string} [message]
 * @returns {Promise<true>}
 */
export function innovAlert(options, message) {
  const opts =
    typeof options === 'string'
      ? { title: 'Aviso', message: options }
      : { ...options, message: options.message || message || '' }

  return /** @type {Promise<true>} */ (
    useDialogStore().show({
      type: 'alert',
      title: opts.title || 'Aviso',
      message: opts.message,
      confirmText: opts.confirmText || 'OK',
      danger: false,
    })
  )
}
