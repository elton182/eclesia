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
  openFilePicker,
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
    assert.equal(
      validatePessoaFotoFile({ name: 'a.jpg', type: 'image/jpeg', size: 10 }).ok,
      true,
    )
    assert.equal(
      validatePessoaFotoFile({ name: 'a.jpg', type: 'application/octet-stream', size: 10 }).ok,
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
  })

  it('openFilePicker trata bloqueio (legado; foto usa toque direto)', async () => {
    const denied = await openFilePicker({
      disabled: false,
      click: () => {
        throw new Error('nope')
      },
      showPicker: async () => {
        const err = new Error('blocked')
        err.name = 'NotAllowedError'
        throw err
      },
    })
    assert.equal(denied.ok, false)
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
  })

  it('PessoaFotoField usa toque direto no input (sem click programático)', () => {
    const foto = readFileSync(join(here, '../components/ecc/PessoaFotoField.vue'), 'utf8')
    const comprovante = readFileSync(
      join(here, '../components/ecc/ComprovanteAnexoField.vue'),
      'utf8',
    )
    assert.equal(fileInputsAvoidDisplayNone(foto), true)
    assert.equal(fileInputsAvoidDisplayNone(comprovante), true)
    assert.match(foto, /class="file-input-hit"/)
    assert.match(foto, /data-testid="pessoa-foto-gallery"/)
    assert.doesNotMatch(foto, /openFilePicker/)
    assert.doesNotMatch(foto, /@click="pick/)
    assert.match(comprovante, /class="file-input-hit"/)
  })

  it('foto e comprovante têm fallback de input file nativo', () => {
    const foto = readFileSync(join(here, '../components/ecc/PessoaFotoField.vue'), 'utf8')
    const comprovante = readFileSync(
      join(here, '../components/ecc/ComprovanteAnexoField.vue'),
      'utf8',
    )
    const fallback = readFileSync(
      join(here, '../components/ecc/FilePickerFallback.vue'),
      'utf8',
    )
    assert.match(foto, /FilePickerFallback/)
    assert.match(foto, /useFilePickerFallback/)
    assert.match(foto, /pessoa-foto-fallback/)
    assert.match(foto, /onSilentFail/)
    assert.match(foto, /Câmera não abriu/)
    assert.match(foto, /onPrimaryPickerInteract\('camera'\)/)
    assert.match(comprovante, /FilePickerFallback/)
    assert.match(comprovante, /useFilePickerFallback/)
    assert.match(comprovante, /onSilentFail/)
    assert.match(fallback, /Não abriu\? Escolher arquivo/)
    assert.match(fallback, /class="file-input-fallback"/)
    assert.equal(fileInputsAvoidDisplayNone(fallback), true)
    assert.doesNotMatch(fallback, /file-input-hit/)
    assert.doesNotMatch(fallback, /capture=/)
  })
})
