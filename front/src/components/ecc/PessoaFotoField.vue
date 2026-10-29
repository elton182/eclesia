<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import {
  deletePessoaFoto,
  fileFromInputEvent,
  openFilePicker,
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
const galleryInput = ref(null)
const cameraInput = ref(null)

const previewUrl = computed(() => localPreview.value || props.fotoUrl || null)

const btnClass =
  'text-[12.5px] font-medium px-2.5 py-1.5 rounded-lg cursor-pointer inline-flex items-center'
const btnStyle = 'border: 1px solid rgba(42,20,24,0.18); background: #FFFDFA; color: #2A1418'

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

async function pick(kind) {
  if (busy.value) return
  const input = kind === 'camera' ? cameraInput.value : galleryInput.value
  const result = await openFilePicker(input)
  if (!result.ok) {
    innovToast('error', 'Foto', result.message)
  }
}

async function onFile(event) {
  const file = fileFromInputEvent(event)
  if (!file) return

  const check = validatePessoaFotoFile(file)
  if (!check.ok) {
    innovToast('error', 'Foto', check.message)
    return
  }

  if (!props.pessoaId) {
    emit('update:pendingFile', file)
    localPreview.value = URL.createObjectURL(file)
    return
  }

  busy.value = true
  try {
    const pessoa = await uploadPessoaFoto(api, props.pessoaId, file)
    emit('update:fotoUrl', pessoa.foto_url || null)
    emit('update:pendingFile', null)
    localPreview.value = null
    innovToast('success', 'OK', 'Foto salva')
  } catch (e) {
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
            Botão separado + input fora do botão: no Android, botão dentro de
            <label> engole o clique e o picker não abre. showPicker/click no
            gesto do usuário + input não-display:none.
          -->
          <button
            type="button"
            :class="btnClass"
            :style="btnStyle"
            :disabled="busy"
            data-testid="pessoa-foto-gallery"
            @click="pick('gallery')"
          >
            Galeria
          </button>
          <button
            type="button"
            :class="btnClass"
            :style="btnStyle"
            :disabled="busy"
            data-testid="pessoa-foto-camera"
            @click="pick('camera')"
          >
            Tirar foto
          </button>
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
        <p class="text-[11px] m-0" style="color: rgba(42,20,24,0.5)">
          JPG, PNG ou WebP · até 5&nbsp;MB
          <span v-if="!pessoaId"> · enviada ao salvar o casal</span>
        </p>
      </div>
    </div>

    <input
      ref="galleryInput"
      type="file"
      :accept="PESSOA_FOTO_ACCEPT"
      class="file-input-native"
      tabindex="-1"
      aria-hidden="true"
      :disabled="busy"
      data-testid="pessoa-foto-input-gallery"
      @change="onFile"
    >
    <input
      ref="cameraInput"
      type="file"
      :accept="PESSOA_FOTO_ACCEPT"
      capture="user"
      class="file-input-native"
      tabindex="-1"
      aria-hidden="true"
      :disabled="busy"
      data-testid="pessoa-foto-input-camera"
      @change="onFile"
    >
  </div>
</template>
