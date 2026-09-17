import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { casalTemFichaComFoto, fileFromInputEvent } from './pessoaFoto.js'

describe('pessoaFoto', () => {
  it('detecta ficha com foto via foto_url', () => {
    assert.equal(casalTemFichaComFoto({ ele: { foto_url: 'http://x/a.jpg' }, ela: {} }), true)
    assert.equal(casalTemFichaComFoto({ ele: {}, ela: { foto_url: 'http://x/b.jpg' } }), true)
    assert.equal(casalTemFichaComFoto({ ele: {}, ela: {}, ficha_com_foto: false }), false)
    assert.equal(casalTemFichaComFoto({ ficha_com_foto: true }), true)
  })

  it('extrai arquivo do input e limpa o valor', () => {
    const file = { name: 'a.jpg' }
    const input = { files: [file], value: 'C:\\fakepath\\a.jpg' }
    const event = { target: input }
    assert.equal(fileFromInputEvent(event), file)
    assert.equal(input.value, '')
    assert.equal(fileFromInputEvent({ target: { files: [] } }), null)
  })
})
