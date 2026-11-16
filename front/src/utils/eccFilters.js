/**
 * Filtros de listagem ECC (pesquisa local).
 */

export function filterEquipesBySearch(equipes, search) {
  const q = String(search || '').trim().toLowerCase()
  if (!q) return equipes
  return equipes.filter((e) => String(e.nome || '').toLowerCase().includes(q))
}

export function filterCasais({ casais, search, equipeId, includeInativos = true }) {
  const q = String(search || '').trim().toLowerCase()
  return casais.filter((c) => {
    if (!includeInativos && c.ativo === false) return false
    if (equipeId) {
      const ids = Array.isArray(c.equipe_ids) && c.equipe_ids.length
        ? c.equipe_ids.map(String)
        : c.equipe_id
          ? [String(c.equipe_id)]
          : []
      if (!ids.includes(String(equipeId))) return false
    }
    if (!q) return true
    const equipeNomes = Array.isArray(c.equipes)
      ? c.equipes.map((e) => e?.nome).filter(Boolean)
      : []
    const haystack = [
      c.nome,
      c.nome_conjuge,
      c.ele?.nome,
      c.ela?.nome,
      c.equipe_nome,
      ...equipeNomes,
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

/**
 * Query de listagem de casais preservável na URL (equipe + pesquisa).
 * Ignora outros params (ex.: edit).
 */
export function casaisListQuery({ equipeId = '', search = '' } = {}) {
  const query = {}
  const equipe = String(equipeId || '').trim()
  const q = String(search || '').trim()
  if (equipe) query.equipe = equipe
  if (q) query.q = q
  return query
}

/** Extrai filtros de listagem a partir de route.query (ou objeto similar). */
export function casaisListQueryFromRoute(routeQuery = {}) {
  const equipe = routeQuery.equipe ? String(routeQuery.equipe) : ''
  const q = routeQuery.q ? String(routeQuery.q) : ''
  return casaisListQuery({ equipeId: equipe, search: q })
}

/** Rota nomeada para listar casais, opcionalmente filtrados. */
export function casaisListRoute({ equipeId = '', search = '' } = {}) {
  return {
    name: 'ecc-casais',
    query: casaisListQuery({ equipeId, search }),
  }
}

/** @deprecated Preferir casaisListRoute — mantido para compatibilidade. */
export function casaisRouteForEquipe(equipeId) {
  return casaisListRoute({ equipeId })
}
