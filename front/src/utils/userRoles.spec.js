import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { buildSyncRolesPayload, roleLabel } from './userRoles.js'

describe('userRoles', () => {
  it('roleLabel usa nomes amigáveis', () => {
    assert.equal(roleLabel('cadastros'), 'Cadastros')
    assert.equal(roleLabel('lider-equipe'), 'Líder de Equipe')
    assert.equal(roleLabel('admin-tenant'), 'Admin Organização')
  })

  it('buildSyncRolesPayload monta N papéis e equipe_ids do líder', () => {
    const payload = buildSyncRolesPayload({
      igrejaId: '01IGREJA',
      toggles: {
        'admin-igreja': true,
        cadastros: true,
        'lider-equipe': true,
        'admin-tenant': false,
      },
      equipeIds: ['eq1', 'eq2'],
    })

    assert.deepEqual(payload, {
      igreja_id: '01IGREJA',
      roles: [
        { name: 'admin-igreja' },
        { name: 'cadastros' },
        { name: 'lider-equipe', equipe_ids: ['eq1', 'eq2'] },
      ],
    })
  })

  it('buildSyncRolesPayload sem papéis de igreja omite igreja_id', () => {
    const payload = buildSyncRolesPayload({
      igrejaId: '01IGREJA',
      toggles: { 'admin-tenant': true, 'admin-igreja': false },
      equipeIds: [],
    })

    assert.deepEqual(payload, {
      igreja_id: null,
      roles: [{ name: 'admin-tenant' }],
    })
  })
})
