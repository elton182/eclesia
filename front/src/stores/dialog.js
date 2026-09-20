import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Dialog global (substitui alert / confirm / prompt nativos).
 * @typedef {'alert'|'confirm'|'prompt'} DialogType
 */

export const useDialogStore = defineStore('dialog', () => {
  const open = ref(false)
  const type = ref(/** @type {DialogType} */ ('confirm'))
  const title = ref('')
  const message = ref('')
  const label = ref('')
  const placeholder = ref('')
  const defaultValue = ref('')
  const inputValue = ref('')
  const confirmText = ref('Confirmar')
  const cancelText = ref('Cancelar')
  const danger = ref(false)

  /** @type {((value: unknown) => void) | null} */
  let resolveFn = null

  /**
   * @param {{
   *   type: DialogType,
   *   title?: string,
   *   message?: string,
   *   label?: string,
   *   placeholder?: string,
   *   defaultValue?: string,
   *   confirmText?: string,
   *   cancelText?: string,
   *   danger?: boolean,
   * }} opts
   * @returns {Promise<unknown>}
   */
  function show(opts) {
    if (resolveFn) {
      resolveFn(opts.type === 'prompt' ? null : false)
      resolveFn = null
    }

    type.value = opts.type
    title.value = opts.title || defaultTitle(opts.type)
    message.value = opts.message || ''
    label.value = opts.label || ''
    placeholder.value = opts.placeholder || ''
    defaultValue.value = opts.defaultValue || ''
    inputValue.value = opts.defaultValue || ''
    confirmText.value = opts.confirmText || (opts.type === 'alert' ? 'OK' : 'Confirmar')
    cancelText.value = opts.cancelText || 'Cancelar'
    danger.value = !!opts.danger
    open.value = true

    return new Promise((resolve) => {
      resolveFn = resolve
    })
  }

  function defaultTitle(t) {
    if (t === 'prompt') return 'Informe'
    if (t === 'alert') return 'Aviso'
    return 'Confirmar'
  }

  function close(result) {
    open.value = false
    const resolve = resolveFn
    resolveFn = null
    if (resolve) resolve(result)
  }

  function confirm() {
    if (type.value === 'prompt') {
      const value = String(inputValue.value || '').trim()
      close(value === '' ? null : value)
      return
    }
    close(true)
  }

  function cancel() {
    if (type.value === 'prompt') {
      close(null)
      return
    }
    if (type.value === 'alert') {
      close(true)
      return
    }
    close(false)
  }

  return {
    open,
    type,
    title,
    message,
    label,
    placeholder,
    defaultValue,
    inputValue,
    confirmText,
    cancelText,
    danger,
    show,
    confirm,
    cancel,
  }
})
