/**
 * Utilitários do editor visual de formulários do site público.
 */

export const FIELD_TYPES = [
  { value: 'text', label: 'Texto curto' },
  { value: 'textarea', label: 'Texto longo' },
  { value: 'email', label: 'E-mail' },
  { value: 'tel', label: 'Telefone' },
  { value: 'select', label: 'Lista de opções' },
  { value: 'checkbox', label: 'Caixa de seleção' },
]

const TYPES_WITH_OPTIONS = ['select']

/**
 * @param {string} tipo
 */
export function fieldTypeLabel(tipo) {
  return FIELD_TYPES.find((t) => t.value === tipo)?.label || String(tipo || '')
}

/**
 * Nome técnico do campo, usado como chave da submission.
 * @param {unknown} label
 */
export function slugifyFieldName(label) {
  if (typeof label !== 'string') return ''
  return label
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
}

/**
 * @param {string} tipo
 * @param {number} ordem
 */
export function createField(tipo = 'text', ordem = 0) {
  return {
    nome: '',
    label: '',
    tipo,
    obrigatorio: false,
    opcoes: [],
    ordem: Number(ordem) || 0,
  }
}

/**
 * Deriva nomes ausentes, resolve duplicidades e reindexa a ordem.
 * @param {unknown} fields
 */
export function normalizeFields(fields) {
  if (!Array.isArray(fields)) return []
  const used = new Set()

  return fields.map((field, index) => {
    const tipo = String(field?.tipo || 'text')
    const label = String(field?.label || '').trim()

    let nome = slugifyFieldName(field?.nome) || slugifyFieldName(label) || `campo_${index + 1}`
    if (used.has(nome)) {
      let suffix = 2
      while (used.has(`${nome}_${suffix}`)) suffix += 1
      nome = `${nome}_${suffix}`
    }
    used.add(nome)

    const opcoes = TYPES_WITH_OPTIONS.includes(tipo) && Array.isArray(field?.opcoes)
      ? field.opcoes.map((o) => String(o).trim()).filter(Boolean)
      : []

    return {
      ...field,
      nome,
      label,
      tipo,
      obrigatorio: !!field?.obrigatorio,
      opcoes,
      ordem: index,
    }
  })
}

/**
 * Mensagens de erro exibidas antes de salvar o formulário.
 * @param {Array<Record<string, unknown>>} fields
 * @returns {string[]}
 */
export function validateFields(fields) {
  const list = Array.isArray(fields) ? fields : []
  if (list.length === 0) return ['Adicione ao menos um campo ao formulário.']

  const errors = []
  const seen = new Map()

  list.forEach((field, index) => {
    const posicao = index + 1
    const label = String(field?.label || '').trim()
    if (!label) {
      errors.push(`Campo ${posicao}: informe o rótulo.`)
    }
    if (TYPES_WITH_OPTIONS.includes(String(field?.tipo)) ) {
      const opcoes = Array.isArray(field?.opcoes)
        ? field.opcoes.map((o) => String(o).trim()).filter(Boolean)
        : []
      if (opcoes.length === 0) {
        errors.push(`Campo ${posicao}${label ? ` (${label})` : ''}: adicione ao menos uma opção.`)
      }
    }
    const nome = String(field?.nome || '').trim()
    if (nome) seen.set(nome, (seen.get(nome) || 0) + 1)
  })

  const duplicados = [...seen.entries()].filter(([, count]) => count > 1).map(([nome]) => nome)
  if (duplicados.length > 0) {
    errors.push(`Nomes de campo duplicados: ${duplicados.join(', ')}.`)
  }

  return errors
}
