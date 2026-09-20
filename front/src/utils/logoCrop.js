/**
 * Crop/resize de logo no browser (SPEC-014).
 * Saída: PNG quadrado (padrão 512px) para preencher navbar/site/PDF.
 */

export const LOGO_OUTPUT_SIZE = 512
export const LOGO_MIN_ZOOM = 1
export const LOGO_MAX_ZOOM = 3

/**
 * @param {File|Blob} file
 * @returns {Promise<HTMLImageElement>}
 */
export function loadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Não foi possível carregar a imagem.'))
    }
    img.src = url
  })
}

/**
 * Escala base: imagem cabe no viewport quadrado (contain).
 * @param {number} imgW
 * @param {number} imgH
 * @param {number} viewport
 */
export function baseScaleToFit(imgW, imgH, viewport) {
  if (imgW <= 0 || imgH <= 0 || viewport <= 0) return 1
  return Math.min(viewport / imgW, viewport / imgH)
}

/**
 * Retângulo da imagem (em coords da imagem) visível no crop quadrado.
 *
 * @param {{
 *   imgW: number,
 *   imgH: number,
 *   viewport: number,
 *   zoom: number,
 *   offsetX: number,
 *   offsetY: number,
 * }} opts
 * @returns {{ sx: number, sy: number, sw: number, sh: number }}
 */
export function computeCropRect(opts) {
  const { imgW, imgH, viewport, zoom, offsetX, offsetY } = opts
  const z = Math.min(LOGO_MAX_ZOOM, Math.max(LOGO_MIN_ZOOM, zoom || 1))
  const base = baseScaleToFit(imgW, imgH, viewport)
  const scale = base * z
  const drawnW = imgW * scale
  const drawnH = imgH * scale

  // Centro da imagem no viewport + pan (offset em px do viewport)
  const centerX = viewport / 2 + offsetX
  const centerY = viewport / 2 + offsetY
  const left = centerX - drawnW / 2
  const top = centerY - drawnH / 2

  const sx = Math.max(0, Math.min(imgW, (-left) / scale))
  const sy = Math.max(0, Math.min(imgH, (-top) / scale))
  const ex = Math.max(0, Math.min(imgW, (viewport - left) / scale))
  const ey = Math.max(0, Math.min(imgH, (viewport - top) / scale))

  return {
    sx,
    sy,
    sw: Math.max(1, ex - sx),
    sh: Math.max(1, ey - sy),
  }
}

/**
 * Limita o pan para a imagem cobrir o viewport no zoom atual.
 * @param {{ imgW: number, imgH: number, viewport: number, zoom: number, offsetX: number, offsetY: number }} opts
 */
export function clampLogoOffsets(opts) {
  const { imgW, imgH, viewport, zoom } = opts
  const z = Math.min(LOGO_MAX_ZOOM, Math.max(LOGO_MIN_ZOOM, zoom || 1))
  const base = baseScaleToFit(imgW, imgH, viewport)
  const scale = base * z
  const drawnW = imgW * scale
  const drawnH = imgH * scale

  const maxX = Math.max(0, (drawnW - viewport) / 2)
  const maxY = Math.max(0, (drawnH - viewport) / 2)

  return {
    offsetX: Math.max(-maxX, Math.min(maxX, opts.offsetX || 0)) || 0,
    offsetY: Math.max(-maxY, Math.min(maxY, opts.offsetY || 0)) || 0,
  }
}

/**
 * Desenha o crop no canvas de saída.
 *
 * @param {CanvasImageSource} image
 * @param {{
 *   imgW: number,
 *   imgH: number,
 *   viewport: number,
 *   zoom: number,
 *   offsetX: number,
 *   offsetY: number,
 *   outputSize?: number,
 *   canvas?: HTMLCanvasElement,
 * }} opts
 * @returns {HTMLCanvasElement}
 */
export function renderLogoCrop(image, opts) {
  const outputSize = opts.outputSize ?? LOGO_OUTPUT_SIZE
  const canvas = opts.canvas ?? (typeof document !== 'undefined' ? document.createElement('canvas') : null)
  if (!canvas) {
    throw new Error('Canvas indisponível.')
  }
  canvas.width = outputSize
  canvas.height = outputSize
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('Contexto 2D indisponível.')
  }

  const { sx, sy, sw, sh } = computeCropRect({
    imgW: opts.imgW,
    imgH: opts.imgH,
    viewport: opts.viewport,
    zoom: opts.zoom,
    offsetX: opts.offsetX,
    offsetY: opts.offsetY,
  })

  ctx.clearRect(0, 0, outputSize, outputSize)
  ctx.drawImage(image, sx, sy, sw, sh, 0, 0, outputSize, outputSize)
  return canvas
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {string} [filename]
 * @param {number} [quality]
 * @returns {Promise<File>}
 */
export function canvasToPngFile(canvas, filename = 'logo.png', quality = 0.92) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error('Falha ao gerar a imagem.'))
          return
        }
        resolve(new File([blob], filename, { type: 'image/png' }))
      },
      'image/png',
      quality,
    )
  })
}
