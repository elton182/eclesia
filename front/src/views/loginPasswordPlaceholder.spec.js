import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))

/**
 * Placeholder de senha com bullets (••••) parece valor preenchido e confunde usuários.
 */
describe('login password placeholder', () => {
  it('campos de senha de login ficam sem placeholder de bullets', () => {
    const views = [
      'TenantLoginView.vue',
      'LoginView.vue',
      'site/SiteAccessInstallView.vue',
    ]
    for (const rel of views) {
      const src = readFileSync(join(here, rel), 'utf8')
      assert.doesNotMatch(
        src,
        /placeholder=["']•+["']/,
        `${rel} não deve usar placeholder de bullets na senha`,
      )
    }
  })
})
