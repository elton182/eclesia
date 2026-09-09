import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { buildSyncRolesPayload, roleLabel, userHasPermission, isLiderEquipeScoped, equipesLideradasIds } from './userRoles.js'

describe('userRoles', () => {
  it('roleLabel usa nomes amigáveis por tela', () => {
    assert.equal(roleLabel('cadastros-usuarios'), 'Cadastros · Usuários')
    assert.equal(roleLabel('cadastros-equipes'), 'Cadastros · Equipes')
    assert.equal(roleLabel('cadastros-casais'), 'Cadastros · Casais')
    assert.equal(roleLabel('lider-equipe'), 'Líder de Equipe')
    assert.equal(roleLabel('admin-tenant'), 'Admin Organização')
  })

  it('buildSyncRolesPayload monta N papéis e equipe_ids do líder', () => {
    const payload = buildSyncRolesPayload({
      igrejaId: '01IGREJA',
      toggles: {
        'admin-igreja': true,
        'cadastros-usuarios': true,
        'lider-equipe': true,
        'admin-tenant': false,
      },
      equipeIds: ['eq1', 'eq2'],
    })

    assert.deepEqual(payload, {
      igreja_id: '01IGREJA',
      roles: [
        { name: 'admin-igreja' },
        { name: 'cadastros-usuarios' },
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

  it('userHasPermission respeita telas por papel de cadastros', () => {
    const user = {
      roles: [{ name: 'cadastros-casais' }],
    }
    assert.equal(userHasPermission(user, 'telas.casais'), true)
    assert.equal(userHasPermission(user, 'telas.usuarios'), false)
    assert.equal(userHasPermission(user, 'telas.equipes', { isSuperAdmin: true }), true)
  })

  it('líder de equipe não tem manage e fica com escopo de equipe', () => {
    const lider = {
      roles: [{ name: 'lider-equipe' }],
      permissions: ['telas.casais', 'telas.equipes', 'ecc.casais.view', 'ecc.equipes.view'],
      equipes_lideradas: [{ id: 'eq1', nome: 'A' }],
    }
    assert.equal(userHasPermission(lider, 'ecc.casais.view'), true)
    assert.equal(userHasPermission(lider, 'ecc.casais.manage'), false)
    assert.equal(isLiderEquipeScoped(lider), true)
    assert.deepEqual(equipesLideradasIds(lider), ['eq1'])
  })

  it('cadastros-casais não é escopo de líder', () => {
    const user = { roles: [{ name: 'cadastros-casais' }] }
    assert.equal(userHasPermission(user, 'ecc.casais.manage'), true)
    assert.equal(isLiderEquipeScoped(user), false)
  })
})
