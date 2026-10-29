<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovAlert, innovConfirm } from '@/plugins/dialog'
import { useMediaPermissionGuard } from '@/composables/useMediaPermissionGuard'
import { useFilePickerFallback } from '@/composables/useFilePickerFallback'
import FilePickerFallback from '@/components/ecc/FilePickerFallback.vue'
import { trackDiagnostic } from '@/utils/clientDiagnostics'
import {
  CAMERA_PERMISSION_HELP,
  GALLERY_PERMISSION_HELP,
} from '@/utils/mediaPermissions'
import {
  deletePessoaFoto,
  fileFromInputEvent,
  PESSOA_FOTO_ACCEPT,
  uploadPessoaFoto,
  validatePessoaFotoFile,
} from '@/utils/pessoaFoto'

const props = defineProps({
  pessoaId: { type: String, default: null },
  fotoUrl: { type: String, default: null },
  label: { type: String, default: 'Foto' },
  editable: { type: Boolean, default: true },
  /** Arquivo pendente (create: upload após salvar casal) */
  pendingFile: { type: Object, default: null },
})

const emit = defineEmits(['update:fotoUrl', 'update:pendingFile'])

const busy = ref(false)
const localPreview = ref(null)
const { guardCameraClick, guardGalleryClick } = useMediaPermissionGuard()
let silentCameraAlertShown = false

const {
  showFallback,
  revealFallback,
  onPrimaryPickerInteract,
  onFilePicked,
} = useFilePickerFallback({
  source: 'foto',
  onSilentFail: ({ kind }) => {
    trackDiagnostic('foto.picker.silent_fail', { kind })
    if (kind === 'camera') {
      if (silentCameraAlertShown) return
      silentCameraAlertShown = true
      void innovAlert({
        title: 'Câmera não abriu',
        message:
          'O aparelho não liberou a câmera (às vezes a permissão aparece como “perguntar” mesmo após negar).\n\n'
          + 'Use “Escolher arquivo” abaixo ou a Galeria, ou ative a câmera nos ajustes:\n\n'
          + CAMERA_PERMISSION_HELP,
        confirmText: 'Entendi',
      })
      return
    }
    innovToast('info', 'Galeria', GALLERY_PERMISSION_HELP.split('\n')[0])
  },
})

const previewUrl = computed(() => localPreview.value || props.fotoUrl || null)

function onGalleryClick(event) {
  guardGalleryClick(event)
  onPrimaryPickerInteract('gallery')
}

function onCameraClick(event) {
  guardCameraClick(event)
  if (!event.defaultPrevented) onPrimaryPickerInteract('camera')
}

const hitClass =
  'relative inline-flex items-center overflow-hidden text-[12.5px] font-medium px-2.5 py-1.5 rounded-lg cursor-pointer'
const hitStyle = 'border: 1px solid rgba(42,20,24,0.18); background: #FFFDFA; color: #2A1418'

watch(
  () => props.fotoUrl,
  () => {
    if (props.fotoUrl) localPreview.value = null
  },
)

watch(
  () => props.pendingFile,
  (file) => {
    if (file && !props.pessoaId) {
      localPreview.value = URL.createObjectURL(file)
    }
    if (!file && !props.fotoUrl) {
      localPreview.value = null
    }
  },
)

async function onFile(event) {
  const file = fileFromInputEvent(event)
  if (!file) {
    trackDiagnostic('foto.file.empty')
    return
  }

  onFilePicked()
  trackDiagnostic('foto.file.selected', {
    mime: file.type || '',
    size: Number(file.size) || 0,
    ext: String(file.name || '').split('.').pop() || '',
  })

  const check = validatePessoaFotoFile(file)
  if (!check.ok) {
    trackDiagnostic('foto.file.rejected', { message: check.message })
    innovToast('error', 'Foto', check.message)
    return
  }

  if (!props.pessoaId) {
    emit('update:pendingFile', file)
    localPreview.value = URL.createObjectURL(file)
    trackDiagnostic('foto.file.pending')
    return
  }

  busy.value = true
  try {
    const pessoa = await uploadPessoaFoto(api, props.pessoaId, file)
    emit('update:fotoUrl', pessoa.foto_url || null)
    emit('update:pendingFile', null)
    localPreview.value = null
    trackDiagnostic('foto.upload.ok')
    innovToast('success', 'OK', 'Foto salva')
  } catch (e) {
    trackDiagnostic('foto.upload.fail', {
      status: e.response?.status || 0,
    })
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao enviar foto')
  } finally {
    busy.value = false
  }
}

async function removeFoto() {
  if (!props.editable || busy.value) return

  if (!props.pessoaId) {
    emit('update:pendingFile', null)
    localPreview.value = null
    return
  }

  const ok = await innovConfirm({
    title: 'Remover',
    message: 'Remover esta foto?',
    confirmText: 'Remover',
    danger: true,
  })
  if (!ok) return

  busy.value = true
  try {
    await deletePessoaFoto(api, props.pessoaId)
    emit('update:fotoUrl', null)
    localPreview.value = null
    innovToast('success', 'OK', 'Foto removida')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover foto')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="pessoa-foto" data-testid="pessoa-foto">
    <div class="text-[11px] font-medium tracking-wider uppercase mb-2" style="color: #B4703F">
      {{ label }}
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <div
        class="w-10 h-10 rounded-full overflow-hidden shrink-0 flex items-center justify-center"
        style="background: #C88A5E; color: #4E1220; border: 1px solid rgba(42,20,24,0.12)"
      >
        <img
          v-if="previewUrl"
          :src="previewUrl"
          :alt="label"
          class="w-full h-full object-cover"
          data-testid="pessoa-foto-preview"
        >
        <span v-else class="font-serif text-sm font-medium" aria-hidden="true">·</span>
      </div>

      <div v-if="editable" class="flex flex-col gap-1.5">
        <div class="flex flex-wrap gap-2 items-center">
          <!--
            PWA Android: toque no próprio input file.
            Se a câmera estiver negada no SO, guardCameraClick avisa em vez do silêncio.
          -->
          <span
            :class="[hitClass, { 'opacity-50 pointer-events-none': busy }]"
            :style="hitStyle"
            data-testid="pessoa-foto-gallery"
          >
            <span class="pointer-events-none select-none">Galeria</span>
            <input
              type="file"
              :accept="PESSOA_FOTO_ACCEPT"
              class="file-input-hit"
              :disabled="busy"
              data-testid="pessoa-foto-input-gallery"
              @click="onGalleryClick"
              @change="onFile"
            >
          </span>
          <span
            :class="[hitClass, { 'opacity-50 pointer-events-none': busy }]"
            :style="hitStyle"
            data-testid="pessoa-foto-camera"
          >
            <span class="pointer-events-none select-none">Tirar foto</span>
            <input
              type="file"
              :accept="PESSOA_FOTO_ACCEPT"
              capture="user"
              class="file-input-hit"
              :disabled="busy"
              data-testid="pessoa-foto-input-camera"
              @click="onCameraClick"
              @change="onFile"
            >
          </span>
          <button
            v-if="previewUrl"
            type="button"
            class="text-[12.5px] px-2.5 py-1.5 rounded-lg cursor-pointer bg-transparent"
            style="color: rgba(42,20,24,0.65)"
            :disabled="busy"
            data-testid="pessoa-foto-remove"
            @click="removeFoto"
          >
            Remover
          </button>
        </div>
        <FilePickerFallback
          class="mt-1"
          :accept="PESSOA_FOTO_ACCEPT"
          :disabled="busy"
          :show="showFallback"
          test-id-prefix="pessoa-foto-fallback"
          @reveal="revealFallback('manual')"
          @change="onFile"
        />
        <p class="text-[11px] m-0" style="color: rgba(42,20,24,0.5)">
          JPG, PNG ou WebP · até 5&nbsp;MB
          <span v-if="!pessoaId"> · enviada ao salvar o casal</span>
        </p>
      </div>
    </div>
  </div>
</template>
