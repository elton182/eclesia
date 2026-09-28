/**
 * Etapas da ficha de casal (SPEC-017): gravação a cada Continuar.
 */

/** @typedef {'contato'|'endereco'|'pessoas'|'ecc'|'servico'} FichaStepId */

/**
 * @typedef {{ id: FichaStepId, title: string, fields: string[] }} FichaStep
 */

/** @type {FichaStep[]} */
export const FICHA_STEPS = [
  {
    id: 'contato',
    title: 'Contato',
    fields: [
      'equipe_id',
      'nome',
      'nome_conjuge',
      'email',
      'email_conjuge',
      'telefone',
      'telefone_conjuge',
      'data_nascimento',
      'data_nascimento_conjuge',
      'foto_url_ele',
      'foto_url_ela',
      'pending_foto_ele',
      'pending_foto_ela',
    ],
  },
  {
    id: 'endereco',
    title: 'Endereço',
    fields: ['endereco', 'bairro', 'cidade', 'uf', 'cep'],
  },
  {
    id: 'pessoas',
    title: 'Pessoas',
    fields: [
      'nome_usual_ele',
      'nome_usual_ela',
      'profissao_ele',
      'profissao_ela',
      'religiao_ele',
      'religiao_ela',
      'endereco_profissional_ele',
      'endereco_profissional_ela',
      'telefone_profissional_ele',
      'telefone_profissional_ela',
    ],
  },
  {
    id: 'ecc',
    title: 'ECC',
    fields: [
      'data_casamento',
      'anos_casados',
      'piloto',
      'foi_coordenador_geral',
      'ecc_origem',
      'funcao_dirigente',
      'etapas',
    ],
  },
  {
    id: 'servico',
    title: 'Serviço',
    fields: [
      'engajamento_paroquial',
      'habilidades',
      'atividades',
      'preferencias',
      'filhos',
      'observacoes',
    ],
  },
]

/**
 * @returns {number}
 */
export function fichaStepsCount() {
  return FICHA_STEPS.length
}

/**
 * @param {number} index
 * @returns {FichaStep|null}
 */
export function fichaStepAt(index) {
  if (!Number.isInteger(index) || index < 0 || index >= FICHA_STEPS.length) return null
  return FICHA_STEPS[index]
}

/**
 * @param {number} index
 * @returns {string}
 */
export function fichaStepProgress(index) {
  const total = FICHA_STEPS.length
  const n = Math.min(Math.max(index + 1, 1), total)
  return `${n} de ${total}`
}

/**
 * @param {number} index
 */
export function isFirstFichaStep(index) {
  return index <= 0
}

/**
 * @param {number} index
 */
export function isLastFichaStep(index) {
  return index >= FICHA_STEPS.length - 1
}

/**
 * Validação mínima da etapa (nomes obrigatórios no Contato).
 * @param {number} index
 * @param {Record<string, unknown>|null|undefined} form
 * @returns {{ ok: true } | { ok: false, error: string }}
 */
export function validateFichaStep(index, form) {
  const step = fichaStepAt(index)
  if (!step) {
    return { ok: false, error: 'Etapa inválida.' }
  }
  if (step.id !== 'contato') {
    return { ok: true }
  }
  const nome = String(form?.nome ?? '').trim()
  const nomeConjuge = String(form?.nome_conjuge ?? '').trim()
  if (!nome || !nomeConjuge) {
    return { ok: false, error: 'Informe o nome de Ele e de Ela.' }
  }
  return { ok: true }
}
