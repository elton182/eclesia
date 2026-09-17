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
    nome_conjuge: ela.nome ?? item.nome_conjuge ?? '',
    email_conjuge: ela.email ?? item.email_conjuge ?? '',
    telefone_conjuge: ela.telefone ?? item.telefone_conjuge ?? '',
    data_nascimento_conjuge: ela.data_nascimento ?? item.data_nascimento_conjuge ?? '',
    pessoa_b_id: ela.id ?? null,
    foto_url_ela: ela.foto_url ?? null,
  }
}
