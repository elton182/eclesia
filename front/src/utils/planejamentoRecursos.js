/**
 * Espaços e recursos do Planejamento Anual (SPEC-019).
 */

export const TIPO_RECURSO = Object.freeze({
  espaco: 'Espaço',
  recurso: 'Recurso',
})

/** @param {string} tipo */
export function labelTipoRecurso(tipo) {
  return TIPO_RECURSO[tipo] || tipo || ''
}

/**
 * @template {{ tipo?: string }} T
 * @param {T[]} itens
 * @returns {{ espacos: T[], recursos: T[] }}
 */
export function splitPorTipo(itens) {
  const list = Array.isArray(itens) ? itens : []
  return {
    espacos: list.filter((i) => i?.tipo === 'espaco'),
    recursos: list.filter((i) => i?.tipo === 'recurso'),
  }
}

/** @returns {{ espacoIds: string[], quantidades: Record<string, number> }} */
export function emptySelecaoRecursos() {
  return { espacoIds: [], quantidades: {} }
}

/**
 * Seleção do picker a partir de `evento.recursos[]` da API.
 * @param {{ recursos?: Array<{ recurso_id: string, tipo?: string, quantidade?: number }> }|null|undefined} evento
 */
export function selecaoFromEvento(evento) {
  const sel = emptySelecaoRecursos()
  for (const r of Array.isArray(evento?.recursos) ? evento.recursos : []) {
    if (!r?.recurso_id) continue
    if (r.tipo === 'espaco') sel.espacoIds.push(r.recurso_id)
    else sel.quantidades[r.recurso_id] = Math.max(1, Number(r.quantidade) || 1)
  }
  return sel
}

/**
 * @param {{ espacoIds?: string[], quantidades?: Record<string, number> }|null|undefined} selecao
 * @returns {Array<{ recurso_id: string, quantidade: number }>}
 */
export function buildRecursosPayload(selecao) {
  const out = []
  for (const id of new Set(selecao?.espacoIds || [])) {
    if (id) out.push({ recurso_id: id, quantidade: 1 })
  }
  for (const [id, q] of Object.entries(selecao?.quantidades || {})) {
    const n = Math.floor(Number(q) || 0)
    if (id && n > 0) out.push({ recurso_id: id, quantidade: n })
  }
  return out
}

/** @param {{ espacoIds?: string[] }|null|undefined} selecao */
export function selecaoTemEspaco(selecao) {
  return Array.isArray(selecao?.espacoIds) && selecao.espacoIds.length > 0
}

/**
 * @param {{ espacoIds: string[], quantidades: Record<string, number> }} selecao
 * @param {string} id
 */
export function toggleEspaco(selecao, id) {
  const set = new Set(selecao.espacoIds)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  return { ...selecao, espacoIds: [...set] }
}

/**
 * @param {{ espacoIds: string[], quantidades: Record<string, number> }} selecao
 * @param {string} id
 * @param {number} quantidade
 * @param {number} [max]
 */
export function setQuantidadeRecurso(selecao, id, quantidade, max = Infinity) {
  const n = Math.max(0, Math.min(Math.floor(Number(quantidade) || 0), max))
  const quantidades = { ...selecao.quantidades }
  if (n === 0) delete quantidades[id]
  else quantidades[id] = n
  return { ...selecao, quantidades }
}

/**
 * Parâmetros de GET .../disponibilidade a partir do formulário; null sem data.
 * @param {{ data_inicio?: string, data_fim?: string, hora_inicio?: string, hora_fim?: string }} form
 * @param {string} [ignorarEventoId]
 */
export function disponibilidadeParams(form, ignorarEventoId) {
  const data = String(form?.data_inicio || '').slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) return null
  /** @type {Record<string, string>} */
  const params = { data_inicio: data }
  const fim = String(form?.data_fim || '').slice(0, 10)
  if (/^\d{4}-\d{2}-\d{2}$/.test(fim) && fim >= data) params.data_fim = fim
  const hi = String(form?.hora_inicio || '').slice(0, 5)
  const hf = String(form?.hora_fim || '').slice(0, 5)
  if (/^\d{2}:\d{2}$/.test(hi)) params.hora_inicio = hi
  if (/^\d{2}:\d{2}$/.test(hf)) params.hora_fim = hf
  if (ignorarEventoId) params.ignorar_evento_id = ignorarEventoId
  return params
}

/**
 * @param {{ tipo?: string, quantidade?: number, disponivel?: number }|null|undefined} item
 */
export function formatSaldo(item) {
  if (!item) return ''
  const disp = Math.max(0, Number(item.disponivel) || 0)
  if (item.tipo === 'espaco') return disp > 0 ? 'Livre' : 'Ocupado'
  const total = Number(item.quantidade) || 0
  return `${disp} de ${total} ${disp === 1 ? 'livre' : 'livres'}`
}

/**
 * Itens da seleção que excedem o disponível (para destacar antes de salvar).
 * @param {{ espacoIds: string[], quantidades: Record<string, number> }} selecao
 * @param {Array<{ recurso_id: string, disponivel: number }>} disponibilidade
 * @returns {string[]}
 */
export function itensExcedidos(selecao, disponibilidade) {
  const map = new Map((disponibilidade || []).map((d) => [d.recurso_id, d]))
  return buildRecursosPayload(selecao)
    .filter((p) => {
      const d = map.get(p.recurso_id)
      return d ? p.quantidade > (Number(d.disponivel) || 0) : false
    })
    .map((p) => p.recurso_id)
}

/** @param {unknown} err */
export function isErroCapacidade(err) {
  const e = /** @type {{ response?: { status?: number, data?: { conflitos_recursos?: unknown } } }} */ (err)
  return e?.response?.status === 422 && Array.isArray(e.response.data?.conflitos_recursos)
}

/**
 * Mensagem amigável para o 422 de capacidade.
 * @param {{ conflitos_recursos?: Array<{ nome: string, tipo?: string, disponivel: number, quantidade?: number, pedido: number, eventos?: Array<{ titulo: string, hora_inicio?: string|null }> }>, message?: string }|null|undefined} body
 */
export function formatConflitosRecursos(body) {
  const conflitos = Array.isArray(body?.conflitos_recursos) ? body.conflitos_recursos : []
  if (!conflitos.length) return body?.message || ''
  return conflitos
    .map((c) => {
      const base =
        c.tipo === 'espaco'
          ? `${c.nome} já está ocupado neste horário`
          : `${c.nome}: ${c.disponivel} de ${c.quantidade ?? '?'} livre(s), pedido ${c.pedido}`
      const evs = Array.isArray(c.eventos) ? c.eventos : []
      if (!evs.length) return base
      const nomes = evs
        .map((e) => (e.hora_inicio ? `${e.titulo} (${String(e.hora_inicio).slice(0, 5)})` : e.titulo))
        .join(', ')
      return `${base} — em uso por ${nomes}`
    })
    .join('. ')
}

/**
 * Nova data para mover evento (drag & drop) preservando a duração em dias.
 * @param {{ data_inicio: string, data_fim?: string|null }} evento
 * @param {string} novoDia YYYY-MM-DD
 * @returns {{ data_inicio: string, data_fim: string|null }}
 */
export function moverEventoParaDia(evento, novoDia) {
  const inicio = String(evento.data_inicio || '').slice(0, 10)
  const fim = evento.data_fim ? String(evento.data_fim).slice(0, 10) : null
  if (!fim || !inicio) return { data_inicio: novoDia, data_fim: null }
  const toUtc = (iso) => {
    const [y, m, d] = iso.split('-').map(Number)
    return Date.UTC(y, m - 1, d)
  }
  const dias = Math.round((toUtc(fim) - toUtc(inicio)) / 86400000)
  const novoFim = new Date(toUtc(novoDia) + dias * 86400000).toISOString().slice(0, 10)
  return { data_inicio: novoDia, data_fim: novoFim }
}

/**
 * Resumo curto para chips/listas: "Sala 1, Projetor ×2".
 * @param {{ recursos?: Array<{ nome: string, tipo?: string, quantidade?: number }> }|null|undefined} evento
 */
export function labelRecursosEvento(evento) {
  const list = Array.isArray(evento?.recursos) ? evento.recursos : []
  return list
    .map((r) => (r.tipo !== 'espaco' && Number(r.quantidade) > 1 ? `${r.nome} ×${r.quantidade}` : r.nome))
    .join(', ')
}
