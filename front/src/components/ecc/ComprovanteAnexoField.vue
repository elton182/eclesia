<script setup>
import { computed, ref } from 'vue'
import { fileFromInputEvent, openFilePicker } from '@/utils/pessoaFoto'
import { innovToast } from '@/plugins/toast'
import {
  COMPROVANTE_ACCEPT_ARQUIVO,
  labelComprovanteSelecionado,
} from '@/utils/eccFinanceiroDepositos'

const props = defineProps({
  modelValue: { type: Object, default: null },
  /** Aceita PDF e imagens na escolha de arquivo (galeria / disco). */
  acceptArquivo: {
    type: String,
    default: COMPROVANTE_ACCEPT_ARQUIVO,
  },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  id: { type: String, default: 'comprovante-anexo' },
})

const emit = defineEmits(['update:modelValue'])

const arquivoInput = ref(null)
const cameraInput = ref(null)

const nomeArquivo = computed(() => props.modelValue?.name || '')
const textoAjuda = computed(() => labelComprovanteSelecionado(props.modelValue))

function onPick(event) {
  const file = fileFromInputEvent(event)
  emit('update:modelValue', file)
}

function limpar() {
  if (props.disabled) return
  emit('update:modelValue', null)
}

async function pick(kind) {
  if (props.disabled) return
  const input = kind === 'camera' ? cameraInput.value : arquivoInput.value
  const result = await openFilePicker(input)
  if (!result.ok) {
    innovToast('error', 'Comprovante', result.message)
  }
}

async function abrirArquivo() {
  await pick('arquivo')
}
</script>

<template>
  <div data-testid="comprovante-anexo">
    <span class="fld">Comprovante</span>
    <div class="flex flex-wrap gap-2 items-center">
      <button
        type="button"
        class="btn btn-ghost"
        :disabled="disabled"
        :data-testid="`${id}-arquivo`"
        @click="pick('arquivo')"
      >
        Arquivo
      </button>
      <button
        type="button"
        class="btn btn-ghost"
        :disabled="disabled"
        :data-testid="`${id}-camera`"
        @click="pick('camera')"
      >
        Tirar foto
      </button>
      <button
        v-if="nomeArquivo"
        type="button"
        class="btn btn-ghost"
        :disabled="disabled"
        :data-testid="`${id}-limpar`"
        @click="limpar"
      >
        Remover
      </button>
    </div>
    <p
      class="mt-1.5 text-[12.5px] m-0"
      style="color: var(--color-muted)"
      :data-testid="`${id}-nome`"
    >
      {{ textoAjuda }}
    </p>

    <!-- input “required” oculto para validação nativa do formulário -->
    <input
      :id="id"
      type="text"
      class="sr-only"
      tabindex="-1"
      aria-hidden="true"
      :value="nomeArquivo"
      :required="required"
      :data-testid="`${id}-required`"
      @focus="abrirArquivo"
    >

    <input
      :id="`${id}-input-arquivo`"
      ref="arquivoInput"
      type="file"
      :accept="acceptArquivo"
      class="file-input-native"
      tabindex="-1"
      aria-hidden="true"
      :disabled="disabled"
      :data-testid="`${id}-input-arquivo`"
      @change="onPick"
    >
    <input
      :id="`${id}-input-camera`"
      ref="cameraInput"
      type="file"
      accept="image/*"
      capture="environment"
      class="file-input-native"
      tabindex="-1"
      aria-hidden="true"
      :disabled="disabled"
      :data-testid="`${id}-input-camera`"
      @change="onPick"
    >
  </div>
</template>
