/**
 * Filtros de listagem ECC (pesquisa local).
 */

export function filterEquipesBySearch(equipes, search) {
  const q = String(search || '').trim().toLowerCase()
  if (!q) return equipes
  return equipes.filter((e) => String(e.nome || '').toLowerCase().includes(q))
}

export function filterCasais({ casais, search, equipeId }) {
  const q = String(search || '').trim().toLowerCase()
  return casais.filter((c) => {
    if (equipeId && c.equipe_id !== equipeId) return false
    if (!q) return true
    const haystack = [
      c.nome,
      c.nome_conjuge,
      c.equipe_nome,
      c.cidade,
      c.bairro,
      c.telefone,
      c.telefone_conjuge,
      c.email,
      c.email_conjuge,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(q)
  })
}

/** Rota nomeada para listar casais, opcionalmente filtrados por equipe. */
export function casaisRouteForEquipe(equipeId) {
  return {
    name: 'ecc-casais',
    query: equipeId ? { equipe: String(equipeId) } : {},
  }
}
