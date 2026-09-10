<script setup>
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDesktop, faMobileScreen } from '@fortawesome/free-solid-svg-icons'
import { normalizeBlocks, resolveMenuLinks } from '@/utils/siteBlocks'
import SiteBlockRenderer from '@/components/site/SiteBlockRenderer.vue'

library.add(faDesktop, faMobileScreen)

const props = defineProps({
  blocks: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  tenantSlug: { type: String, default: '' },
  pageTitle: { type: String, default: '' },
})

const viewport = ref('desktop')

const visibleBlocks = computed(() => normalizeBlocks(props.blocks))
const menuLinks = computed(() => resolveMenuLinks(props.settings?.menu, props.tenantSlug))
const brandTitle = computed(() => props.settings?.titulo || 'Site da organização')
const headerBg = computed(() => props.settings?.cores?.primary || '#00234E')
</script>

<template>
  <div class="space-y-3" data-testid="site-page-preview">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-xs" style="color: var(--color-muted)">
        Prévia aproximada — listas e formulários carregam apenas com o site publicado.
      </p>
      <div class="inline-flex rounded-lg p-0.5" style="background: var(--color-surface-2)">
        <button
          type="button"
          class="px-3 py-1.5 rounded-md text-xs font-semibold"
          :style="viewport === 'desktop'
            ? 'background: var(--color-surface); color: var(--color-primary)'
            : 'color: var(--color-muted)'"
          data-testid="preview-desktop"
          @click="viewport = 'desktop'"
        >
          <FontAwesomeIcon :icon="faDesktop" class="mr-1.5" />
          Computador
        </button>
        <button
          type="button"
          class="px-3 py-1.5 rounded-md text-xs font-semibold"
          :style="viewport === 'mobile'
            ? 'background: var(--color-surface); color: var(--color-primary)'
            : 'color: var(--color-muted)'"
          data-testid="preview-mobile"
          @click="viewport = 'mobile'"
        >
          <FontAwesomeIcon :icon="faMobileScreen" class="mr-1.5" />
          Celular
        </button>
      </div>
    </div>

    <div
      class="rounded-2xl overflow-hidden mx-auto transition-all duration-300"
      style="border: 1px solid var(--color-line); background: #f7f4ef"
      :class="viewport === 'mobile' ? 'max-w-[380px]' : 'w-full'"
    >
      <div
        class="flex items-center gap-2 px-3 py-2"
        style="background: var(--color-surface-2); border-bottom: 1px solid var(--color-line)"
      >
        <span class="h-2.5 w-2.5 rounded-full" style="background: #e06c5e" />
        <span class="h-2.5 w-2.5 rounded-full" style="background: #e8be5c" />
        <span class="h-2.5 w-2.5 rounded-full" style="background: #7cbf8e" />
        <span
          class="ml-2 flex-1 truncate rounded-md px-2.5 py-1 text-[11px] font-mono"
          style="background: var(--color-surface); color: var(--color-muted)"
        >
          /site/{{ tenantSlug || 'organizacao' }}
        </span>
      </div>

      <div class="max-h-[70vh] overflow-y-auto">
        <div class="select-none pointer-events-none" style="color: #1a1a1a">
          <header :style="{ background: headerBg, color: '#f7f4ef' }">
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
              <span class="font-semibold tracking-tight">{{ brandTitle }}</span>
              <nav class="flex flex-wrap gap-3 text-xs opacity-90">
                <span v-for="link in menuLinks" :key="link.href">{{ link.label }}</span>
              </nav>
            </div>
          </header>

          <div v-if="visibleBlocks.length">
            <SiteBlockRenderer
              v-for="(block, idx) in visibleBlocks"
              :key="`${block.tipo}-${idx}`"
              :block="block"
              :tenant-slug="tenantSlug"
            />
          </div>
          <p v-else class="px-4 py-16 text-center text-sm" style="color: var(--color-muted)">
            Nenhum bloco visível nesta página.
          </p>

          <footer class="px-4 py-5 text-center text-xs" style="color: rgba(0,0,0,0.45)">
            {{ brandTitle }}
            <span v-if="settings?.subtitulo"> · {{ settings.subtitulo }}</span>
          </footer>
        </div>
      </div>
    </div>

    <p v-if="pageTitle" class="text-center text-xs" style="color: var(--color-muted)">
      {{ pageTitle }}
    </p>
  </div>
</template>
