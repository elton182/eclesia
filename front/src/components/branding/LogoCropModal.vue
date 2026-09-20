<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import InnovModal from '@/components/base/InnovModal.vue'
import {
  LOGO_MAX_ZOOM,
  LOGO_MIN_ZOOM,
  LOGO_OUTPUT_SIZE,
  canvasToPngFile,
  clampLogoOffsets,
  loadImageFromFile,
  renderLogoCrop,
} from '@/utils/logoCrop'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  file: { type: File, default: null },
  title: { type: String, default: 'Ajustar logo' },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const VIEWPORT = 280

const loading = ref(false)
const error = ref('')
const exporting = ref(false)
const image = ref(null)
const zoom = ref(1)
const offsetX = ref(0)
const offsetY = ref(0)
const previewCanvas = ref(null)
const thumbUrl = ref('')

const dragging = ref(false)
const dragStart = ref({ x: 0, y: 0, ox: 0, oy: 0 })

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function resetState() {
  image.value = null
  zoom.value = 1
  offsetX.value = 0
  offsetY.value = 0
  error.value = ''
  loading.value = false
  exporting.value = false
  thumbUrl.value = ''
}

function imgSize() {
  const img = image.value
  return {
    imgW: img.naturalWidth || img.width,
    imgH: img.naturalHeight || img.height,
  }
}

function applyClamp() {
  if (!image.value) return
  const { imgW, imgH } = imgSize()
  const c = clampLogoOffsets({
    imgW,
    imgH,
    viewport: VIEWPORT,
    zoom: zoom.value,
    offsetX: offsetX.value,
    offsetY: offsetY.value,
  })
  offsetX.value = c.offsetX
  offsetY.value = c.offsetY
}

function paintPreview() {
  if (!image.value || !previewCanvas.value) return
  const { imgW, imgH } = imgSize()
  applyClamp()
  renderLogoCrop(image.value, {
    imgW,
    imgH,
    viewport: VIEWPORT,
    zoom: zoom.value,
    offsetX: offsetX.value,
    offsetY: offsetY.value,
    outputSize: VIEWPORT,
    canvas: previewCanvas.value,
  })
  thumbUrl.value = previewCanvas.value.toDataURL('image/png')
}

async function loadFile(file) {
  if (!file) {
    resetState()
    return
  }
  loading.value = true
  error.value = ''
  try {
    image.value = await loadImageFromFile(file)
    zoom.value = 1
    offsetX.value = 0
    offsetY.value = 0
    await nextTick()
    paintPreview()
  } catch (e) {
    error.value = e?.message || 'Falha ao carregar a imagem.'
    image.value = null
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.file],
  ([isOpen, file]) => {
    if (isOpen && file) {
      loadFile(file)
    }
    if (!isOpen) {
      resetState()
    }
  },
  { immediate: true },
)

watch([zoom, offsetX, offsetY], () => {
  if (image.value) paintPreview()
})

function onZoomInput() {
  applyClamp()
  paintPreview()
}

function onPointerDown(e) {
  if (!image.value) return
  dragging.value = true
  dragStart.value = {
    x: e.clientX,
    y: e.clientY,
    ox: offsetX.value,
    oy: offsetY.value,
  }
  e.currentTarget.setPointerCapture?.(e.pointerId)
}

function onPointerMove(e) {
  if (!dragging.value) return
  offsetX.value = dragStart.value.ox + (e.clientX - dragStart.value.x)
  offsetY.value = dragStart.value.oy + (e.clientY - dragStart.value.y)
  applyClamp()
}

function onPointerUp() {
  dragging.value = false
}

function close() {
  open.value = false
  emit('cancel')
}

async function confirm() {
  if (!image.value || exporting.value) return
  exporting.value = true
  error.value = ''
  try {
    const { imgW, imgH } = imgSize()
    applyClamp()
    const canvas = renderLogoCrop(image.value, {
      imgW,
      imgH,
      viewport: VIEWPORT,
      zoom: zoom.value,
      offsetX: offsetX.value,
      offsetY: offsetY.value,
      outputSize: LOGO_OUTPUT_SIZE,
    })
    const base = (props.file?.name || 'logo').replace(/\.[^.]+$/, '')
    const out = await canvasToPngFile(canvas, `${base}-logo.png`)
    emit('confirm', out)
    open.value = false
  } catch (e) {
    error.value = e?.message || 'Não foi possível gerar o recorte.'
  } finally {
    exporting.value = false
  }
}

onUnmounted(() => {
  image.value = null
})
</script>

<template>
  <InnovModal v-model="open" size="lg">
    <div data-testid="logo-crop-modal">
      <h2 id="modal-title" class="text-lg font-semibold" style="color: var(--color-ink)">
        {{ title }}
      </h2>
      <p class="mt-1 text-sm" style="color: var(--color-muted)">
        Arraste para enquadrar e use o zoom. A imagem será salva em {{ LOGO_OUTPUT_SIZE }}×{{ LOGO_OUTPUT_SIZE }} px.
      </p>

      <div v-if="loading" class="mt-6 text-sm" style="color: var(--color-muted)">Carregando…</div>
      <p v-else-if="error" class="mt-4 text-sm" style="color: var(--color-danger)" data-testid="logo-crop-error">
        {{ error }}
      </p>

      <div v-else-if="image" class="mt-5 space-y-4">
        <div class="flex flex-col sm:flex-row gap-5 items-start">
          <div
            class="relative rounded-xl overflow-hidden border touch-none select-none cursor-grab active:cursor-grabbing"
            style="width: 280px; height: 280px; border-color: var(--color-line); background: var(--color-surface-2)"
            data-testid="logo-crop-stage"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
          >
            <canvas
              ref="previewCanvas"
              :width="VIEWPORT"
              :height="VIEWPORT"
              class="block w-full h-full"
            />
          </div>

          <div class="flex flex-col items-center gap-3">
            <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-muted)">
              Como fica
            </span>
            <div
              class="h-24 w-24 rounded-xl overflow-hidden border bg-white"
              style="border-color: var(--color-line)"
            >
              <img
                v-if="thumbUrl"
                :src="thumbUrl"
                alt=""
                class="h-full w-full object-cover"
                data-testid="logo-crop-square-preview"
              />
            </div>
            <div
              class="h-16 w-16 rounded-full overflow-hidden border bg-white"
              style="border-color: var(--color-line)"
            >
              <img
                v-if="thumbUrl"
                :src="thumbUrl"
                alt=""
                class="h-full w-full object-cover"
                data-testid="logo-crop-circle-preview"
              />
            </div>
          </div>
        </div>

        <label class="block">
          <span class="text-sm font-medium" style="color: var(--color-ink)">Zoom</span>
          <input
            v-model.number="zoom"
            type="range"
            :min="LOGO_MIN_ZOOM"
            :max="LOGO_MAX_ZOOM"
            step="0.05"
            class="mt-2 w-full accent-[var(--color-primary)]"
            data-testid="logo-crop-zoom"
            @input="onZoomInput"
          />
        </label>
      </div>

      <div class="mt-6 flex flex-wrap justify-end gap-2">
        <button type="button" class="btn btn-ghost" data-testid="logo-crop-cancel" @click="close">
          Cancelar
        </button>
        <button
          type="button"
          class="btn btn-primary"
          data-testid="logo-crop-confirm"
          :disabled="!image || exporting || loading"
          @click="confirm"
        >
          {{ exporting ? 'Gerando…' : 'Usar recorte' }}
        </button>
      </div>
    </div>
  </InnovModal>
</template>
