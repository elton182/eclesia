import { fileURLToPath, URL } from 'node:url'
import { resolve } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
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

  // Configuração padrão para desenvolvimento
  return {
    plugins: [
      vue(),
      vueDevTools(),
      tailwindcss(),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
  }
})
