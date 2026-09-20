import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import { createPinia, setActivePinia } from 'pinia'
import { useDialogStore } from '../stores/dialog.js'
import { innovConfirm, innovPrompt, innovAlert } from '../plugins/dialog.js'

describe('dialog', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('innovConfirm resolve true/false', async () => {
    const store = useDialogStore()
    const p = innovConfirm({ title: 'Remover', message: 'Tem certeza?' })
    assert.equal(store.open, true)
    assert.equal(store.type, 'confirm')
    store.confirm()
    assert.equal(await p, true)

    const p2 = innovConfirm('Título', 'Msg')
    store.cancel()
    assert.equal(await p2, false)
  })

  it('innovPrompt resolve string ou null', async () => {
    const store = useDialogStore()
    const p = innovPrompt({
      title: 'Local',
      label: 'Nome',
      placeholder: 'MATRIZ',
    })
    store.inputValue = '  Matriz  '
    store.confirm()
    assert.equal(await p, 'Matriz')

    const p2 = innovPrompt({ title: 'Local', label: 'Nome' })
    store.cancel()
    assert.equal(await p2, null)
  })

  it('innovAlert resolve true', async () => {
    const store = useDialogStore()
    const p = innovAlert('Operação concluída')
    assert.equal(store.type, 'alert')
    store.confirm()
    assert.equal(await p, true)
  })
})
