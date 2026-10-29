/**
 * Resolve a string de versão do front no build (Node / vite.config).
 * Formato: `{package.version}+{gitShortSha}` (ex.: 1.0.0+6e78f51).
 */
import { execSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * @param {{
 *   envVersion?: string|null,
 *   packageVersion?: string|null,
 *   gitSha?: string|null,
 *   cwd?: string,
 * }} [opts]
 * @returns {string}
 */
export function buildAppVersion(opts = {}) {
  const envVersion = (opts.envVersion ?? process.env.VITE_APP_VERSION ?? '').trim()
  if (envVersion) return envVersion

  const frontRoot = opts.cwd || join(dirname(fileURLToPath(import.meta.url)), '..', '..')

  let packageVersion = (opts.packageVersion ?? '').trim()
  if (!packageVersion) {
    try {
      const pkg = JSON.parse(readFileSync(join(frontRoot, 'package.json'), 'utf8'))
      packageVersion = String(pkg.version || '0.0.0')
    } catch {
      packageVersion = '0.0.0'
    }
  }

  let sha = ''
  if (Object.prototype.hasOwnProperty.call(opts, 'gitSha')) {
    sha = String(opts.gitSha ?? '').trim()
  } else {
    try {
      sha = execSync('git rev-parse --short HEAD', {
        encoding: 'utf8',
        cwd: frontRoot,
        stdio: ['ignore', 'pipe', 'ignore'],
      }).trim()
    } catch {
      sha = ''
    }
  }

  return sha ? `${packageVersion}+${sha}` : packageVersion
}
