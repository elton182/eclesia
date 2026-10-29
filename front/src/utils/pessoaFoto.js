/**
 * Upload multipart da foto da pessoa.
 * @param {import('axios').AxiosInstance} api
 * @param {string} pessoaId
 * @param {File|Blob} file
 */
export async function uploadPessoaFoto(api, pessoaId, file) {
  const body = new FormData()
  body.append('file', file)
  const { data } = await api.post(`/pessoas/${pessoaId}/foto`, body)
  return data.data || data
}

/**
 * Remove foto da pessoa.
 * @param {import('axios').AxiosInstance} api
 * @param {string} pessoaId
 */
export async function deletePessoaFoto(api, pessoaId) {
  await api.delete(`/pessoas/${pessoaId}/foto`)
}

/** MIME types aceitos pela API (SPEC-006). */
export const PESSOA_FOTO_ALLOWED_MIME = Object.freeze([
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/webp',
])

/** Extensões aceitas (fallback quando o browser não informa MIME). */
export const PESSOA_FOTO_ALLOWED_EXT = Object.freeze(['.jpg', '.jpeg', '.png', '.webp'])

/**
 * accept amplo no input: listas MIME restritas quebram o picker em vários Androids.
 * A validação fina fica em validatePessoaFotoFile.
 */
export const PESSOA_FOTO_ACCEPT = 'image/*'

/** Limite alinhado à API (5 MB). */
export const PESSOA_FOTO_MAX_BYTES = 5 * 1024 * 1024

/**
 * Classe do input file que cobre o label (toque direto no input).
 * Preferível a `sr-only`/`hidden` em WebViews e PWA mobile.
 */
export const FILE_INPUT_HIT_CLASS = 'file-input-hit'

/** @deprecated Preferir FILE_INPUT_HIT_CLASS — mantido para compat de testes. */
export const FILE_INPUT_VISUALLY_HIDDEN_CLASS = FILE_INPUT_HIT_CLASS

/**
 * Aceita imagem do input (galeria ou câmera).
 * @param {Event} event
 * @returns {File|null}
 */
export function fileFromInputEvent(event) {
  const input = event?.target
  const file = input?.files?.[0] ?? null
  if (input) input.value = ''
  return file
}

/**
 * @param {string|null|undefined} name
 * @returns {string}
 */
function fileExtension(name) {
  const base = String(name || '').trim().toLowerCase()
  const dot = base.lastIndexOf('.')
  if (dot < 0) return ''
  return base.slice(dot)
}

/**
 * Valida arquivo antes do upload (MIME/extensão + tamanho).
 * @param {File|{ name?: string, type?: string, size?: number }|null|undefined} file
 * @returns {{ ok: true } | { ok: false, message: string }}
 */
export function validatePessoaFotoFile(file) {
  if (!file) {
    return { ok: false, message: 'Nenhuma imagem selecionada' }
  }

  const size = Number(file.size) || 0
  if (size <= 0) {
    return { ok: false, message: 'Arquivo de imagem inválido' }
  }
  if (size > PESSOA_FOTO_MAX_BYTES) {
    return { ok: false, message: 'A foto deve ter no máximo 5 MB' }
  }

  const mime = String(file.type || '').toLowerCase()
  const ext = fileExtension(file.name)
  const mimeOk = mime && PESSOA_FOTO_ALLOWED_MIME.includes(mime)
  const extOk = PESSOA_FOTO_ALLOWED_EXT.includes(ext)

  if (mimeOk || (!mime && extOk)) {
    return { ok: true }
  }

  if (ext === '.heic' || ext === '.heif' || mime === 'image/heic' || mime === 'image/heif') {
    return {
      ok: false,
      message: 'Formato HEIC não é suportado. Escolha JPG, PNG ou WebP (no iPhone: Ajustes → Câmera → Formatos → Mais Compatível).',
    }
  }

  return { ok: false, message: 'Use JPG, PNG ou WebP (até 5 MB)' }
}

/**
 * Garante que o markup do seletor de arquivo não usa display:none (classe `hidden`).
 * Aceita `file-input-hit` (overlay) ou `sr-only` (legado).
 * @param {string} vueSfcSource
 * @returns {boolean}
 */
export function fileInputsAvoidDisplayNone(vueSfcSource) {
  const source = String(vueSfcSource || '')
  const fileInputBlocks = source.match(/<input\b[^>]*\btype=["']file["'][^>]*>/gi) || []
  if (fileInputBlocks.length === 0) return false
  const allowed = new Set([FILE_INPUT_HIT_CLASS, 'sr-only'])
  return fileInputBlocks.every((tag) => {
    const classMatch = tag.match(/\bclass=["']([^"']*)["']/i)
    const classes = (classMatch?.[1] ?? '').split(/\s+/).filter(Boolean)
    if (classes.includes('hidden')) return false
    return classes.some((c) => allowed.has(c))
  })
}

/**
 * Casal tem ficha com foto se ele ou ela possui foto_url.
 * @param {{ ele?: { foto_url?: string|null }, ela?: { foto_url?: string|null }, ficha_com_foto?: boolean }} casal
 */
export function casalTemFichaComFoto(casal) {
  if (!casal) return false
  if (casal.ele?.foto_url || casal.ela?.foto_url) return true
  return Boolean(casal.ficha_com_foto)
}
