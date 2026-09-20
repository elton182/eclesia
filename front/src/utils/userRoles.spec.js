import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  buildSyncRolesPayload,
  roleLabel,
  userHasPermission,
  isLiderEquipeScoped,
  equipesLideradasIds,
  canSeeEccCasaisNav,
  canSeeEccEventosNav,
  canSeeCalendarioNav,
} from './userRoles.js'

describe('userRoles', () => {
  it('roleLabel usa nomes amigáveis por tela', () => {
    assert.equal(roleLabel('cadastros-usuarios'), 'Cadastros · Usuários')
    assert.equal(roleLabel('cadastros-equipes'), 'Cadastros · Equipes')
    assert.equal(roleLabel('cadastros-casais'), 'Cadastros · Casais')
    assert.equal(roleLabel('cadastros-eventos'), 'Cadastros · Eventos')
    assert.equal(roleLabel('cadastros-calendario'), 'Cadastros · Calendário')
    assert.equal(roleLabel('lider-equipe'), 'Líder de Equipe')
    assert.equal(roleLabel('admin-tenant'), 'Admin Organização')
  })

  it('userHasPermission reconhece telas.igrejas para admin-igreja', () => {
    assert.equal(
      userHasPermission({ roles: [{ name: 'admin-igreja' }] }, 'telas.igrejas'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'cadastros-equipes' }] }, 'telas.igrejas'),
      false,
    )
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

  it('userHasPermission reconhece telas.auditoria para admin-igreja', () => {
    assert.equal(
      userHasPermission({ roles: [{ name: 'admin-igreja' }] }, 'telas.auditoria'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'admin-igreja' }] }, 'auditoria.view'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'cadastros-usuarios' }] }, 'telas.auditoria'),
      false,
    )
  })

  it('userHasPermission respeita telas por papel de cadastros', () => {
    const user = {
      roles: [{ name: 'cadastros-casais' }],
    }
    assert.equal(userHasPermission(user, 'telas.casais'), true)
    assert.equal(userHasPermission(user, 'telas.usuarios'), false)
    assert.equal(userHasPermission(user, 'telas.equipes', { isSuperAdmin: true }), true)
  })

  it('userHasPermission reconhece gestor-site e limita admin-igreja no CMS global', () => {
    assert.equal(
      userHasPermission({ roles: [{ name: 'gestor-site' }] }, 'telas.site'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'gestor-site' }] }, 'site.settings.update'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'admin-igreja' }] }, 'site.comunicados.manage'),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'admin-igreja' }] }, 'site.settings.update'),
      false,
    )
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

  it('canSeeEccCasaisNav com telas.casais ou telas.equipes', () => {
    assert.equal(
      canSeeEccCasaisNav({ roles: [{ name: 'cadastros-casais' }] }),
      true,
    )
    assert.equal(
      canSeeEccCasaisNav({ roles: [{ name: 'cadastros-equipes' }] }),
      true,
    )
    assert.equal(
      canSeeEccCasaisNav({ roles: [{ name: 'lider-equipe' }] }),
      true,
    )
    assert.equal(
      canSeeEccCasaisNav({ roles: [{ name: 'gestor-site' }] }),
      false,
    )
    assert.equal(canSeeEccCasaisNav(null, { isSuperAdmin: true }), true)
  })

  it('canSeeEccEventosNav para líder, admin e cadastros-eventos', () => {
    assert.equal(
      canSeeEccEventosNav({ roles: [{ name: 'lider-equipe' }] }),
      true,
    )
    assert.equal(
      canSeeEccEventosNav({ roles: [{ name: 'admin-igreja' }] }),
      true,
    )
    assert.equal(
      canSeeEccEventosNav({ roles: [{ name: 'cadastros-eventos' }] }),
      true,
    )
    assert.equal(
      userHasPermission({ roles: [{ name: 'cadastros-eventos' }] }, 'ecc.eventos.manage'),
      true,
    )
    assert.equal(
      canSeeEccEventosNav({ permissions: ['ecc.eventos.view'], roles: [] }),
      true,
    )
    assert.equal(
      canSeeEccEventosNav({ roles: [{ name: 'gestor-site' }] }),
      false,
    )
  })

  it('canSeeCalendarioNav para admin-igreja e cadastros-calendario', () => {
    assert.equal(canSeeCalendarioNav({ roles: [{ name: 'admin-igreja' }] }), true)
    assert.equal(canSeeCalendarioNav({ roles: [{ name: 'cadastros-calendario' }] }), true)
    assert.equal(canSeeCalendarioNav({ roles: [{ name: 'lider-equipe' }] }), true)
    assert.equal(canSeeCalendarioNav({ roles: [{ name: 'cadastros-usuarios' }] }), false)
  })
})
