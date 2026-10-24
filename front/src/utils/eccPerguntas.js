/**
 * Helpers da caixa de perguntas ECC (SPEC-030).
 */

/**
 * @param {{ pendentes?: number, com_resposta_nova?: number }|null|undefined} resumo
 * @returns {string|null} texto curto para o cartão / badge
 */
export function labelResumoPerguntas(resumo) {
  const pendentes = Number(resumo?.pendentes || 0)
  const comResposta = Number(resumo?.com_resposta_nova || 0)
  if (pendentes > 0) {
    return pendentes === 1 ? '1 para responder' : `${pendentes} para responder`
  }
  if (comResposta > 0) {
    return comResposta === 1 ? '1 resposta disponível' : `${comResposta} respostas disponíveis`
  }
  return null
}

/**
 * @param {{ pendentes?: number, com_resposta_nova?: number }|null|undefined} resumo
 */
export function temAtividadePerguntas(resumo) {
  return Number(resumo?.pendentes || 0) > 0 || Number(resumo?.com_resposta_nova || 0) > 0
}

/**
 * @param {string} status
 */
export function labelStatusPergunta(status) {
  switch (status) {
    case 'rascunho':
      return 'Rascunho'
    case 'disponivel':
      return 'Disponível'
    case 'arquivado':
      return 'Arquivado'
    case 'enviado':
      return 'Enviado'
    case 'respondido':
      return 'Respondido'
    default:
      return status || '—'
  }
}

/**
 * @param {string} tipo
 */
export function labelTipoCampo(tipo) {
  switch (tipo) {
    case 'texto_curto':
      return 'Texto curto'
    case 'texto_longo':
      return 'Texto longo'
    case 'lista':
      return 'Lista'
    default:
      return tipo || '—'
  }
}

/**
 * @returns {{ tipo: string, rotulo: string, obrigatorio: boolean, opcoes: string[] }}
 */
export function campoVazio(tipo = 'texto_curto') {
  return {
    tipo,
    rotulo: '',
    obrigatorio: true,
    opcoes: tipo === 'lista' ? [''] : [],
  }
}
