import { fileURLToPath, URL } from 'node:url'
import { resolve } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

const pwaPlugin = VitePWA({
  registerType: 'autoUpdate',
  includeAssets: ['favicon.ico', 'icons/apple-touch-icon.png'],
  manifest: {
    id: '/',
    name: 'Eclésias',
    short_name: 'Eclésias',
    description:
      'Plataforma modular de gestão eclesial: pessoas, equipes, eventos, calendário e finanças da sua paróquia.',
    lang: 'pt-BR',
    dir: 'ltr',
    start_url: '/',
    scope: '/',
    display: 'standalone',
    orientation: 'any',
    theme_color: '#4E1220',
    background_color: '#F7F4EF',
    categories: ['productivity', 'business'],
    icons: [
      {
        src: '/icons/icon-192x192.png',
        sizes: '192x192',
        type: 'image/png',
        purpose: 'any',
      },
      {
        src: '/icons/icon-512x512.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'any',
      },
      {
        src: '/icons/maskable-192x192.png',
        sizes: '192x192',
        type: 'image/png',
        purpose: 'maskable',
      },
      {
        src: '/icons/maskable-512x512.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'maskable',
      },
    ],
  },
  workbox: {
    globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,webp}'],
    navigateFallback: '/index.html',
    navigateFallbackDenylist: [/^\/api\//],
    runtimeCaching: [
      {
        urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
        handler: 'NetworkOnly',
      },
      {
        urlPattern: ({ request }) => request.destination === 'font',
        handler: 'CacheFirst',
        options: {
          cacheName: 'eclesia-fonts',
          expiration: {
            maxEntries: 20,
            maxAgeSeconds: 60 * 60 * 24 * 365,
          },
        },
      },
    ],
  },
  devOptions: {
    enabled: true,
    type: 'module',
  },
})

// https://vite.dev/config/
export default defineConfig(() => {
  const isLibrary = process.env.BUILD_MODE === 'library'

  if (isLibrary) {
    // Configuração para build de biblioteca
    return {
      plugins: [
        vue(),
        tailwindcss(),
      ],
      resolve: {
        alias: {
          '@': fileURLToPath(new URL('./src', import.meta.url))
        },
      },
      build: {
        lib: {
          entry: resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/index.js'),
          name: 'InnovFront',
          fileName: (format) => `innov-front.${format}.js`,
          formats: ['es', 'umd']
        },
        rollupOptions: {
          // Externalizar dependências que não devem ser incluídas no bundle
          external: ['vue', 'vue-router', 'pinia', 'axios'],
          output: {
            globals: {
              vue: 'Vue',
              'vue-router': 'VueRouter',
              pinia: 'Pinia',
              axios: 'axios'
            },
            // Preservar nomes de exportação
            exports: 'named'
          }
        },
        cssCodeSplit: false,
        sourcemap: true,
        minify: 'terser',
        // Incluir CSS no bundle
        css: {
          extract: true,
        },
      },
    }
  }

  // Configuração padrão para desenvolvimento / SPA
  return {
    plugins: [
      vue(),
      vueDevTools(),
      tailwindcss(),
      pwaPlugin,
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
  }
})
