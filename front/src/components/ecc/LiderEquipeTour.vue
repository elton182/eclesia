<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  steps: { type: Array, default: () => [] },
  runId: { type: Number, default: 0 },
})

const emit = defineEmits(['close'])

const index = ref(0)
const spot = ref(null)

const step = computed(() => props.steps[index.value] || null)
const isFirst = computed(() => index.value <= 0)
const isLast = computed(() => index.value >= props.steps.length - 1)
const progress = computed(() => {
  const total = props.steps.length
  if (!total) return ''
  return `${index.value + 1} de ${total}`
})

const cardStyle = computed(() => {
  if (!spot.value) {
    return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }
  }
  const gap = 12
  const cardHeight = 220
  const belowTop = spot.value.top + spot.value.height + gap
  const fitsBelow = belowTop + cardHeight < window.innerHeight
  const top = fitsBelow ? belowTop : Math.max(16, spot.value.top - cardHeight - gap)
  const left = Math.min(Math.max(16, spot.value.left), Math.max(16, window.innerWidth - 360))
  return { top: `${top}px`, left: `${left}px` }
})

function measure() {
  const target = step.value?.target
  const el = target ? document.querySelector(`[data-tour="${target}"]`) : null
  if (!el) {
    spot.value = null
    return
  }
  el.scrollIntoView({ block: 'nearest', inline: 'nearest' })
  const rect = el.getBoundingClientRect()
  spot.value = {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  }
}

function reset() {
  index.value = 0
  nextTick(measure)
}

watch(() => props.open, (open) => {
  if (open) reset()
})

watch(() => props.runId, () => {
  if (props.open) reset()
})

watch(index, () => nextTick(measure))

function close() {
  emit('close')
}

function nextStep() {
  if (isLast.value) {
    close()
    return
  }
  index.value += 1
}

function prevStep() {
  if (index.value > 0) index.value -= 1
}

function onResize() {
  if (props.open) measure()
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  if (props.open) reset()
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
})
</script>

<template>
  <div v-if="open && step" class="fixed inset-0 z-50" data-testid="lider-tour">
    <div class="absolute inset-0" style="background: transparent" />
    <div
      v-if="spot"
      class="fixed rounded-xl pointer-events-none"
      :style="{
        top: `${spot.top - 6}px`,
        left: `${spot.left - 6}px`,
        width: `${spot.width + 12}px`,
        height: `${spot.height + 12}px`,
        boxShadow: '0 0 0 9999px rgba(42, 20, 24, 0.55)',
      }"
    />
    <div
      v-else
      class="absolute inset-0"
      style="background: rgba(42, 20, 24, 0.55)"
    />

    <div
      class="fixed z-10 w-[min(22rem,calc(100vw-2rem))] rounded-xl p-4 shadow-lg"
      :style="{ ...cardStyle, background: 'var(--color-surface)', color: 'var(--color-ink)' }"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="'lider-tour-title'"
      data-testid="lider-tour-card"
    >
      <div class="flex items-start justify-between gap-3">
        <p class="text-[12px] font-medium" style="color: var(--color-accent-dark)">
          {{ progress }}
        </p>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="btn btn-ghost px-2 py-1 text-sm"
            aria-label="Fechar tour"
            data-testid="lider-tour-fechar-x"
            @click="close"
          >
            ×
          </button>
          <button
            type="button"
            class="btn btn-ghost px-2 py-1 text-sm"
            data-testid="lider-tour-fechar"
            @click="close"
          >
            Fechar
          </button>
        </div>
      </div>
      <h3 id="lider-tour-title" class="font-serif text-xl mt-1">
        {{ step.title }}
      </h3>
      <p class="text-[13.5px] mt-2 leading-relaxed" style="color: var(--color-muted)">
        {{ step.body }}
      </p>
      <div class="flex flex-wrap gap-2 mt-4">
        <button
          type="button"
          class="btn btn-ghost"
          :disabled="isFirst"
          data-testid="lider-tour-anterior"
          @click="prevStep"
        >
          Anterior
        </button>
        <button
          type="button"
          class="btn btn-primary"
          data-testid="lider-tour-proximo"
          @click="nextStep"
        >
          {{ isLast ? 'Concluir' : 'Próximo' }}
        </button>
      </div>
    </div>
  </div>
</template>
