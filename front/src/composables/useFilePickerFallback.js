import { onMounted, onUnmounted, ref } from 'vue'
import { trackDiagnostic } from '../utils/clientDiagnostics.js'
import { isRunningStandalone } from '../utils/pwa.js'

/** Tempo sem `change` após toque nos botões principais para revelar o fallback. */
export const FILE_PICKER_FALLBACK_MS = 2000

/**
 * Fallback com `<input type="file">` nativo visível quando o picker estilizado falha
 * (PWA Android) ou o usuário pede ajuda.
 *
 * No Android, `permissions.query('camera')` costuma ficar em `prompt` mesmo após
 * negar o diálogo do capture — o clique “não faz nada”. Por isso armamos um
 * timeout sem `change` e disparamos `onSilentFail` (alerta + input nativo).
 *
 * @param {{
 *   source?: string,
 *   onSilentFail?: (info: { reason: string, kind: string }) => void,
 * }} [options]
 */
export function useFilePickerFallback(options = {}) {
  const source = options.source || 'file'
  const showFallback = ref(false)
  let waitTimer = 0
  /** @type {string} */
  let lastKind = 'file'

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
    trackDiagnostic(`${source}.fallback.reveal`, { reason })
  }

  /**
   * Chamar no click dos botões Galeria / Tirar foto / Arquivo.
   * @param {string} [kind] 'camera' | 'gallery' | 'file'
   */
  function onPrimaryPickerInteract(kind = 'file') {
    if (typeof window === 'undefined') return
    lastKind = kind || 'file'
    clearWait()
    // Mesmo com fallback já visível (PWA standalone), detectamos falha silenciosa.
    waitTimer = window.setTimeout(() => {
      waitTimer = 0
      const already = showFallback.value
      if (!already) {
        revealFallback('no_change')
      } else {
        trackDiagnostic(`${source}.fallback.silent`, {
          kind: lastKind,
          already_shown: true,
        })
      }
      options.onSilentFail?.({ reason: 'no_change', kind: lastKind })
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
