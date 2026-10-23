import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { buildUsersListParams, parseUsersListMeta } from './usersList.js'

describe('usersList', () => {
  it('buildUsersListParams omite q vazio e limita per_page', () => {
    assert.deepEqual(buildUsersListParams({}), { page: 1, per_page: 15 })
    assert.deepEqual(buildUsersListParams({ page: 2, perPage: 25, q: ' ana ' }), {
      page: 2,
      per_page: 25,
      q: 'ana',
    })
    assert.equal(buildUsersListParams({ perPage: 999 }).per_page, 100)
  })

  it('parseUsersListMeta lê meta Laravel', () => {
    assert.deepEqual(
      parseUsersListMeta({
        data: [],
        meta: { current_page: 2, last_page: 4, per_page: 15, total: 55 },
      }),
      { current_page: 2, last_page: 4, per_page: 15, total: 55 },
    )
  })
})
