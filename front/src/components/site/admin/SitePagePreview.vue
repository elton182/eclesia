<script setup>
import { computed, ref } from 'vue'
import { normalizeBlocks, resolveMenuLinks } from '@/utils/siteBlocks'
import SiteBlockRenderer from '@/components/site/SiteBlockRenderer.vue'

const props = defineProps({
  blocks: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  tenantSlug: { type: String, default: '' },
  pageTitle: { type: String, default: '' },
  highlightIndex: { type: Number, default: -1 },
  compact: { type: Boolean, default: false },
  selectable: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

const viewport = ref('desktop')

const allBlocks = computed(() => {
  if (!Array.isArray(props.blocks)) return []
  return props.blocks
    .slice()
    .sort((a, b) => (Number(a.ordem) || 0) - (Number(b.ordem) || 0))
})

const visibleBlocks = computed(() => normalizeBlocks(props.blocks))
const menuLinks = computed(() => resolveMenuLinks(props.settings?.menu, props.tenantSlug))
const brandTitle = computed(() => props.settings?.titulo || 'Paróquia')
const brandInitial = computed(() => (brandTitle.value || 'P')[0].toUpperCase())

function onSelect(idx) {
  if (!props.selectable) return
  emit('select', idx)
}
</script>

<template>
  <div data-testid="site-page-preview">
    <div v-if="!compact" class="space-y-3 mb-3">
      <div class="flex gap-1 justify-end">
        <button type="button" class="text-xs px-2 py-1" @click="viewport = 'desktop'">desktop</button>
        <button type="button" class="text-xs px-2 py-1" @click="viewport = 'mobile'">celular</button>
      </div>
    </div>

    <div
      class="overflow-hidden"
      :class="!compact && viewport === 'mobile' ? 'max-w-[380px] mx-auto' : 'w-full'"
      style="background: #FFFDFA"
    >
      <div class="select-none" style="color: #2A1418">
        <header
          class="sticky top-0 z-10 px-4 h-12 flex items-center justify-between gap-2"
          style="background: rgba(255, 253, 250, 0.94); border-bottom: 1px solid rgba(42, 20, 24, 0.09)"
        >
          <div class="flex items-center gap-2 min-w-0">
            <div
              class="w-6 h-6 rounded-full flex items-center justify-center font-serif text-[11px] shrink-0"
              style="background: #6B1C2B; color: #F0D8C2"
            >{{ brandInitial }}</div>
            <span class="font-serif text-[13px] font-medium truncate">{{ brandTitle }}</span>
          </div>
          <nav class="hidden sm:flex gap-3 text-[10px]" style="color: rgba(42, 20, 24, 0.7)">
            <span v-for="link in menuLinks" :key="link.href">{{ link.label }}</span>
          </nav>
        </header>

        <div v-if="allBlocks.length">
          <div
            v-for="(block, idx) in allBlocks"
            :key="`${block.tipo}-${idx}`"
            class="relative"
            :class="selectable ? 'cursor-pointer group/preview-block' : ''"
            :data-testid="`preview-block-${idx}`"
            @click="onSelect(idx)"
          >
            <div
              v-if="selectable"
              class="absolute inset-0 z-[5] transition-opacity pointer-events-none"
              :class="idx === highlightIndex ? 'opacity-100' : 'opacity-0 group-hover/preview-block:opacity-100'"
              :style="idx === highlightIndex
                ? 'box-shadow: inset 0 0 0 2px #8A2436'
                : 'box-shadow: inset 0 0 0 2px rgba(138,36,54,0.35)'"
            />
            <span
              v-if="selectable && idx === highlightIndex"
              class="absolute top-2 right-2 z-10 text-[10px] font-medium px-2 py-0.5 rounded-full"
              style="background: #8A2436; color: #FFFDFA"
            >editando</span>
            <span
              v-else-if="selectable"
              class="absolute top-2 right-2 z-10 text-[10px] font-medium px-2 py-0.5 rounded-full opacity-0 group-hover/preview-block:opacity-100 transition-opacity"
              style="background: rgba(42,20,24,0.72); color: #FFFDFA"
            >editar</span>
            <SiteBlockRenderer
              :block="block"
              :tenant-slug="tenantSlug"
              :highlight="idx === highlightIndex"
            />
          </div>
        </div>
        <p
          v-else-if="!visibleBlocks.length"
          class="px-4 py-16 text-center text-sm"
          style="color: rgba(42, 20, 24, 0.5)"
        >
          Nenhum bloco nesta página.
        </p>

        <footer
          class="px-4 py-4 text-[10px] flex justify-between gap-2"
          style="background: #2A1418; color: rgba(255, 253, 250, 0.6)"
        >
          <span>{{ brandTitle }} · Eclesias</span>
        </footer>
      </div>
    </div>
  </div>
</template>
