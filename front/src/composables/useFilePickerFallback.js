import { onMounted, onUnmounted, ref } from 'vue'
import { trackDiagnostic } from '../utils/clientDiagnostics.js'
import { isRunningStandalone } from '../utils/pwa.js'

/** Tempo sem `change` após toque nos botões principais para revelar o fallback. */
export const FILE_PICKER_FALLBACK_MS = 2000

/**
 * Fallback com `<input type="file">` nativo visível quando o picker estilizado falha
 * (PWA Android) ou o usuário pede ajuda.
 *
 * @param {{ source?: string }} [options]
 */
export function useFilePickerFallback(options = {}) {
  const source = options.source || 'file'
  const showFallback = ref(false)
  let waitTimer = 0

  function clearWait() {
    if (!waitTimer) return
    if (typeof window !== 'undefined') window.clearTimeout(waitTimer)
    waitTimer = 0
  }

  /**
   * @param {string} [reason]
   */
  function revealFallback(reason = 'manual') {
    if (showFallback.value) return
    showFallback.value = true
    clearWait()
    trackDiagnostic(`${source}.fallback.reveal`, { reason })
  }

  /** Chamar no click dos botões Galeria / Tirar foto / Arquivo. */
  function onPrimaryPickerInteract() {
    if (showFallback.value) return
    if (typeof window === 'undefined') return
    clearWait()
    waitTimer = window.setTimeout(() => {
      revealFallback('no_change')
    }, FILE_PICKER_FALLBACK_MS)
  }

  /** Chamar quando um arquivo foi escolhido (qualquer input). */
  function onFilePicked() {
    clearWait()
  }

  onMounted(() => {
    if (isRunningStandalone()) {
      revealFallback('standalone')
    }
  })

  onUnmounted(() => {
    clearWait()
  })

  return {
    showFallback,
    revealFallback,
    onPrimaryPickerInteract,
    onFilePicked,
  }
}
