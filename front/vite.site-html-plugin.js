/**
 * Em dev/preview, GET /site/:tenantSlug[/:pageSlug] devolve o HTML SEO da API
 * (SPEC-005 / ADR-0003) com o entry do Vue injetado.
 *
 * Produção com dist/ estático: proxy equivalente
 *   /site/* → {API}/api/v1/public/site/html/{tenantSlug}[/{pageSlug}]
 */

import { readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

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
