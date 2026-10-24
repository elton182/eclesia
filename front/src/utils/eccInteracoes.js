/**
 * @param {string} status
 * @returns {string}
 */
export function labelStatusComentario(status) {
  switch (status) {
    case 'pendente':
      return 'Aguardando revisão'
    case 'aprovado':
      return 'Aprovado'
    case 'rejeitado':
      return 'Rejeitado'
    case 'oculto':
      return 'Oculto'
    default:
      return status || ''
  }
}

/**
 * @param {{ likes_count?: number, liked_by_me?: boolean }} item
 * @param {{ liked: boolean, likes_count: number }} result
 */
export function applyLikeResult(item, result) {
  return {
    ...item,
    liked_by_me: !!result?.liked,
    likes_count: Number(result?.likes_count) || 0,
  }
}

/**
 * @param {string} alvoTipo recado|reflexao
 * @param {string} alvoId
 */
export function comentariosPath(alvoTipo, alvoId) {
  const base = alvoTipo === 'reflexao' ? 'reflexoes' : 'recados'
  return `/ecc/${base}/${alvoId}/comentarios`
}

/**
 * @param {string} alvoTipo
 * @param {string} alvoId
 */
export function likePath(alvoTipo, alvoId) {
  const base = alvoTipo === 'reflexao' ? 'reflexoes' : 'recados'
  return `/ecc/${base}/${alvoId}/like`
}
