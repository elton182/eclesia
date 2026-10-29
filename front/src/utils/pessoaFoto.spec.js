import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  casalTemFichaComFoto,
  fileFromInputEvent,
  fileInputsAvoidDisplayNone,
  FILE_INPUT_VISUALLY_HIDDEN_CLASS,
} from './pessoaFoto.js'

const here = dirname(fileURLToPath(import.meta.url))

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

  it('rejeita input file com classe hidden (display:none)', () => {
    assert.equal(FILE_INPUT_VISUALLY_HIDDEN_CLASS, 'sr-only')
    assert.equal(
      fileInputsAvoidDisplayNone('<input type="file" class="hidden" accept="image/*">'),
      false,
    )
    assert.equal(
      fileInputsAvoidDisplayNone('<input type="file" class="sr-only" accept="image/*">'),
      true,
    )
    assert.equal(fileInputsAvoidDisplayNone('<div>sem file</div>'), false)
  })

  it('PessoaFotoField e ComprovanteAnexoField usam sr-only nos inputs file', () => {
    const foto = readFileSync(join(here, '../components/ecc/PessoaFotoField.vue'), 'utf8')
    const comprovante = readFileSync(
      join(here, '../components/ecc/ComprovanteAnexoField.vue'),
      'utf8',
    )
    assert.equal(fileInputsAvoidDisplayNone(foto), true)
    assert.equal(fileInputsAvoidDisplayNone(comprovante), true)
    assert.match(foto, /data-testid="pessoa-foto-gallery"/)
    assert.match(foto, /data-testid="pessoa-foto-camera"/)
    assert.match(foto, /<label[\s\S]*Galeria[\s\S]*type="file"/)
    assert.match(foto, /<label[\s\S]*Tirar foto[\s\S]*type="file"/)
  })
})
