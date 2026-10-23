/**
 * Em dev/preview:
 * - GET /site/:tenantSlug/manifest.webmanifest → manifest de acesso (SPEC-023)
 * - GET /site/:tenantSlug/instalar → SPA com manifest do tenant (SPEC-023)
 * - GET /site/:tenantSlug[/:pageSlug] → HTML SEO da API + entry Vue (SPEC-005)
 *
 * Produção com dist/ estático: proxies equivalentes para manifest, instalar e HTML.
 */

import { readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

const RESERVED_SITE_SEGMENTS = new Set(['instalar', 'manifest.webmanifest'])

/**
 * @param {string} indexHtml
 * @returns {{ links: string[], scripts: string[] }}
 */
export function extractIndexAssets(indexHtml) {
  const links = [...indexHtml.matchAll(/<link\b[^>]*rel=["']stylesheet["'][^>]*>/gi)].map((m) => m[0])
  const scripts = [...indexHtml.matchAll(/<script\b[^>]*type=["']module["'][^>]*><\/script>|<script\b[^>]*type=["']module["'][^>]*>[\s\S]*?<\/script>/gi)].map(
    (m) => m[0],
  )
  return { links, scripts }
}

/**
 * @param {string} html
 * @param {{ links?: string[], scripts?: string[] }} assets
 */
export function injectSpaAssets(html, assets) {
  let out = html
  const linkBlock = (assets.links || []).join('\n  ')
  const scriptBlock = (assets.scripts || []).join('\n  ')
  if (linkBlock) {
    out = out.includes('</head>')
      ? out.replace('</head>', `  ${linkBlock}\n</head>`)
      : `${linkBlock}\n${out}`
  }
  if (scriptBlock) {
    out = out.includes('</body>')
      ? out.replace('</body>', `  ${scriptBlock}\n</body>`)
      : `${out}\n${scriptBlock}`
  }
  return out
}

/**
 * Garante <link rel="manifest"> do tenant (e remove o manifest genérico do index).
 * @param {string} html
 * @param {string} tenantSlug
 */
export function injectTenantAccessManifest(html, tenantSlug) {
  const href = `/site/${encodeURIComponent(tenantSlug)}/manifest.webmanifest`
  const tag = `<link rel="manifest" href="${href}">`
  let out = html.replace(/<link\b[^>]*rel=["']manifest["'][^>]*>\s*/gi, '')
  if (out.includes('</head>')) {
    return out.replace('</head>', `  ${tag}\n</head>`)
  }
  return `${tag}\n${out}`
}

/**
 * @param {{ apiBaseUrl?: string, root?: string }} [options]
 * @returns {import('vite').Plugin}
 */
export function siteHtmlPrerenderPlugin(options = {}) {
  const apiBase = (options.apiBaseUrl || process.env.VITE_API_URL || 'http://localhost:8000/').replace(
    /\/?$/,
    '/',
  )
  const root = options.root || process.cwd()

  /**
   * @param {'dev' | 'preview'} mode
   * @returns {string}
   */
  function readSpaShell(mode) {
    if (mode === 'dev') {
      const indexPath = join(root, 'index.html')
      if (existsSync(indexPath)) {
        return readFileSync(indexPath, 'utf8')
      }
      return '<!DOCTYPE html><html><head></head><body><div id="app"></div><script type="module" src="/src/main.js"></script></body></html>'
    }
    const distIndex = join(root, 'dist', 'index.html')
    if (existsSync(distIndex)) {
      return readFileSync(distIndex, 'utf8')
    }
    return '<!DOCTYPE html><html><head></head><body><div id="app"></div></body></html>'
  }

  /**
   * @param {import('vite').Connect.Server} middlewares
   * @param {'dev' | 'preview'} mode
   */
  function attach(middlewares, mode) {
    middlewares.use(async (req, res, next) => {
      try {
        if ((req.method || 'GET') !== 'GET') {
          next()
          return
        }

        const pathOnly = (req.url || '/').split('?')[0] || '/'

        const manifestMatch = pathOnly.match(/^\/site\/([^/]+)\/manifest\.webmanifest\/?$/)
        if (manifestMatch) {
          const tenantSlug = decodeURIComponent(manifestMatch[1])
          if (!tenantSlug || tenantSlug.includes('.')) {
            next()
            return
          }
          const upstream = new URL(
            `api/v1/public/site/manifest/${encodeURIComponent(tenantSlug)}`,
            apiBase,
          )
          const upstreamRes = await fetch(upstream)
          const body = await upstreamRes.text()
          res.statusCode = upstreamRes.status
          res.setHeader(
            'Content-Type',
            upstreamRes.headers.get('content-type') || 'application/manifest+json; charset=UTF-8',
          )
          res.setHeader('Cache-Control', 'public, max-age=60')
          res.end(body)
          return
        }

        const installMatch = pathOnly.match(/^\/site\/([^/]+)\/instalar\/?$/)
        if (installMatch) {
          const tenantSlug = decodeURIComponent(installMatch[1])
          if (!tenantSlug || tenantSlug.includes('.')) {
            next()
            return
          }
          let shell = readSpaShell(mode)
          if (mode === 'dev' && !shell.includes('/src/main.js')) {
            shell = injectSpaAssets(shell, {
              links: [],
              scripts: ['<script type="module" src="/src/main.js"></script>'],
            })
          }
          const out = injectTenantAccessManifest(shell, tenantSlug)
          res.statusCode = 200
          res.setHeader('Content-Type', 'text/html; charset=UTF-8')
          res.setHeader('Cache-Control', 'no-store')
          res.end(out)
          return
        }

        const match = pathOnly.match(/^\/site\/([^/]+)(?:\/([^/]+))?\/?$/)
        if (!match) {
          next()
          return
        }

        const tenantSlug = decodeURIComponent(match[1])
        const pageSlug = match[2] ? decodeURIComponent(match[2]) : null
        if (!tenantSlug || tenantSlug.includes('.')) {
          next()
          return
        }
        if (pageSlug && (pageSlug.includes('.') || RESERVED_SITE_SEGMENTS.has(pageSlug))) {
          next()
          return
        }

        const apiPath = pageSlug
          ? `api/v1/public/site/html/${encodeURIComponent(tenantSlug)}/${encodeURIComponent(pageSlug)}`
          : `api/v1/public/site/html/${encodeURIComponent(tenantSlug)}`
        const upstream = new URL(apiPath, apiBase)
        const upstreamRes = await fetch(upstream)
        const html = await upstreamRes.text()

        if (!upstreamRes.ok) {
          res.statusCode = upstreamRes.status
          res.setHeader('Content-Type', 'text/html; charset=UTF-8')
          res.end(
            html ||
              '<!DOCTYPE html><html lang="pt-BR"><body><p>Site não encontrado.</p></body></html>',
          )
          return
        }

        /** @type {{ links: string[], scripts: string[] }} */
        let assets = { links: [], scripts: [] }
        if (mode === 'dev') {
          assets = {
            links: [],
            scripts: ['<script type="module" src="/src/main.js"></script>'],
          }
        } else {
          const indexPath = join(root, 'dist', 'index.html')
          if (existsSync(indexPath)) {
            assets = extractIndexAssets(readFileSync(indexPath, 'utf8'))
          }
        }

        const out = injectSpaAssets(html, assets)
        res.statusCode = 200
        res.setHeader('Content-Type', 'text/html; charset=UTF-8')
        res.setHeader('Cache-Control', 'no-store')
        res.end(out)
      } catch (err) {
        next(err)
      }
    })
  }

  return {
    name: 'eclesia-site-html-prerender',
    configureServer(server) {
      attach(server.middlewares, 'dev')
    },
    configurePreviewServer(server) {
      attach(server.middlewares, 'preview')
    },
  }
}
