import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  applyLikeResult,
  comentariosPath,
  labelStatusComentario,
  likePath,
} from './eccInteracoes.js'

describe('eccInteracoes', () => {
  it('rotula status de comentário', () => {
    assert.equal(labelStatusComentario('pendente'), 'Aguardando revisão')
    assert.equal(labelStatusComentario('aprovado'), 'Aprovado')
  })

  it('aplica resultado de like no item', () => {
    const next = applyLikeResult({ id: '1', likes_count: 0, liked_by_me: false }, {
      liked: true,
      likes_count: 3,
    })
    assert.equal(next.liked_by_me, true)
    assert.equal(next.likes_count, 3)
  })

  it('monta paths de API', () => {
    assert.equal(comentariosPath('recado', 'abc'), '/ecc/recados/abc/comentarios')
    assert.equal(likePath('reflexao', 'xyz'), '/ecc/reflexoes/xyz/like')
  })
})
