import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  casalTemFichaComFoto,
  fileFromInputEvent,
  fileInputsAvoidDisplayNone,
  FILE_INPUT_HIT_CLASS,
  PESSOA_FOTO_ACCEPT,
  PESSOA_FOTO_MAX_BYTES,
  validatePessoaFotoFile,
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

  it('valida tipo e tamanho da foto', () => {
    assert.equal(PESSOA_FOTO_ACCEPT, 'image/*')
    assert.equal(validatePessoaFotoFile(null).ok, false)
    assert.equal(
      validatePessoaFotoFile({ name: 'a.jpg', type: 'image/jpeg', size: 10 }).ok,
      true,
    )
    assert.equal(
      validatePessoaFotoFile({ name: 'a.png', type: 'image/png', size: 10 }).ok,
      true,
    )
    assert.match(
      validatePessoaFotoFile({ name: 'a.heic', type: 'image/heic', size: 10 }).message,
      /HEIC/,
    )
    assert.match(
      validatePessoaFotoFile({
        name: 'a.jpg',
        type: 'image/jpeg',
        size: PESSOA_FOTO_MAX_BYTES + 1,
      }).message,
      /5 MB/,
    )
    assert.equal(
      validatePessoaFotoFile({ name: 'a.webp', type: '', size: 10 }).ok,
      true,
    )
  })

  it('rejeita input file com classe hidden (display:none)', () => {
    assert.equal(FILE_INPUT_HIT_CLASS, 'file-input-hit')
    assert.equal(
      fileInputsAvoidDisplayNone('<input type="file" class="hidden" accept="image/*">'),
      false,
    )
    assert.equal(
      fileInputsAvoidDisplayNone('<input type="file" class="file-input-hit" accept="image/*">'),
      true,
    )
    assert.equal(
      fileInputsAvoidDisplayNone('<input type="file" class="sr-only" accept="image/*">'),
      true,
    )
    assert.equal(fileInputsAvoidDisplayNone('<div>sem file</div>'), false)
  })

  it('PessoaFotoField e ComprovanteAnexoField usam file-input-hit nos inputs file', () => {
    const foto = readFileSync(join(here, '../components/ecc/PessoaFotoField.vue'), 'utf8')
    const comprovante = readFileSync(
      join(here, '../components/ecc/ComprovanteAnexoField.vue'),
      'utf8',
    )
    assert.equal(fileInputsAvoidDisplayNone(foto), true)
    assert.equal(fileInputsAvoidDisplayNone(comprovante), true)
    assert.match(foto, /accept="image\/\*"|:accept="PESSOA_FOTO_ACCEPT"/)
    assert.match(foto, /data-testid="pessoa-foto-gallery"/)
    assert.match(foto, /data-testid="pessoa-foto-camera"/)
    assert.match(foto, /class="file-input-hit"/)
    assert.match(comprovante, /class="file-input-hit"/)
  })
})
