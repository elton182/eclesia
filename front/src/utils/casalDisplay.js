/**
 * Nomes Ele/Ela para exibição (independente da ordem de cadastro).
 * @param {Record<string, any>|null|undefined} casal
 */
export function casalEle(casal) {
  return casal?.ele?.nome ?? casal?.nome ?? ''
}

/**
 * @param {Record<string, any>|null|undefined} casal
 */
export function casalEla(casal) {
  return casal?.ela?.nome ?? casal?.nome_conjuge ?? ''
}

/**
 * Campos do formulário a partir de Ele/Ela (API).
 * @param {Record<string, any>} item
 */
export function formFieldsFromEleEla(item) {
  const ele = item.ele || {}
  const ela = item.ela || {}
  return {
    nome: ele.nome ?? item.nome ?? '',
    email: ele.email ?? item.email ?? '',
    telefone: ele.telefone ?? item.telefone ?? '',
    data_nascimento: ele.data_nascimento ?? item.data_nascimento ?? '',
    pessoa_a_id: ele.id ?? null,
    foto_url_ele: ele.foto_url ?? null,
    nome_usual_ele: ele.nome_usual ?? '',
    profissao_ele: ele.profissao ?? '',
    religiao_ele: ele.religiao ?? '',
    endereco_profissional_ele: ele.endereco_profissional ?? '',
    telefone_profissional_ele: ele.telefone_profissional ?? '',
    nome_conjuge: ela.nome ?? item.nome_conjuge ?? '',
    email_conjuge: ela.email ?? item.email_conjuge ?? '',
    telefone_conjuge: ela.telefone ?? item.telefone_conjuge ?? '',
    data_nascimento_conjuge: ela.data_nascimento ?? item.data_nascimento_conjuge ?? '',
    pessoa_b_id: ela.id ?? null,
    foto_url_ela: ela.foto_url ?? null,
    nome_usual_ela: ela.nome_usual ?? '',
    profissao_ela: ela.profissao ?? '',
    religiao_ela: ela.religiao ?? '',
    endereco_profissional_ela: ela.endereco_profissional ?? '',
    telefone_profissional_ela: ela.telefone_profissional ?? '',
  }
}

/**
 * Inverte campos Ele ↔ Ela no formulário (sem chamar API).
 * @param {Record<string, any>} form
 * @returns {Record<string, any>}
 */
export function swapEleElaFormFields(form) {
  const pairs = [
    ['nome', 'nome_conjuge'],
    ['email', 'email_conjuge'],
    ['telefone', 'telefone_conjuge'],
    ['data_nascimento', 'data_nascimento_conjuge'],
    ['pessoa_a_id', 'pessoa_b_id'],
    ['foto_url_ele', 'foto_url_ela'],
    ['pending_foto_ele', 'pending_foto_ela'],
    ['nome_usual_ele', 'nome_usual_ela'],
    ['profissao_ele', 'profissao_ela'],
    ['religiao_ele', 'religiao_ela'],
    ['endereco_profissional_ele', 'endereco_profissional_ela'],
    ['telefone_profissional_ele', 'telefone_profissional_ela'],
  ]
  const next = { ...form }
  for (const [a, b] of pairs) {
    const tmp = next[a]
    next[a] = next[b]
    next[b] = tmp
  }
  return next
}
