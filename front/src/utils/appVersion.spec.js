import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { APP_VERSION_FALLBACK, getAppVersion } from './appVersion.js'
import { buildAppVersion } from './buildAppVersion.js'

describe('appVersion', () => {
  it('getAppVersion retorna fallback sem define de build', () => {
    assert.equal(typeof getAppVersion(), 'string')
    assert.ok(getAppVersion().length > 0)
    assert.equal(APP_VERSION_FALLBACK, 'dev')
  })

  it('buildAppVersion respeita VITE_APP_VERSION / package + sha', () => {
    assert.equal(buildAppVersion({ envVersion: ' 9.9.9-test ' }), '9.9.9-test')
    assert.equal(
      buildAppVersion({ packageVersion: '1.2.3', gitSha: 'abc1234', envVersion: '' }),
      '1.2.3+abc1234',
    )
    assert.equal(
      buildAppVersion({ packageVersion: '1.2.3', gitSha: '', envVersion: '' }),
      '1.2.3',
    )
  })
})
