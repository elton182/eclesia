<script setup>
import { computed } from 'vue'
import { fileFromInputEvent } from '@/utils/pessoaFoto'
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
</script>

<template>
  <div data-testid="comprovante-anexo">
    <span class="fld">Comprovante</span>
    <div class="flex flex-wrap gap-2 items-center">
      <!-- Toque direto no input — necessário em PWA Android instalada. -->
      <span
        class="btn btn-ghost relative overflow-hidden cursor-pointer"
        :class="{ 'opacity-50 pointer-events-none': disabled }"
        :data-testid="`${id}-arquivo`"
      >
        <span class="pointer-events-none select-none">Arquivo</span>
        <input
          :id="`${id}-input-arquivo`"
          type="file"
          :accept="acceptArquivo"
          class="file-input-hit"
          :disabled="disabled"
          :data-testid="`${id}-input-arquivo`"
          @change="onPick"
        >
      </span>
      <span
        class="btn btn-ghost relative overflow-hidden cursor-pointer"
        :class="{ 'opacity-50 pointer-events-none': disabled }"
        :data-testid="`${id}-camera`"
      >
        <span class="pointer-events-none select-none">Tirar foto</span>
        <input
          :id="`${id}-input-camera`"
          type="file"
          accept="image/*"
          capture="environment"
          class="file-input-hit"
          :disabled="disabled"
          :data-testid="`${id}-input-camera`"
          @change="onPick"
        >
      </span>
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

    <input
      :id="id"
      type="text"
      class="sr-only"
      tabindex="-1"
      aria-hidden="true"
      :value="nomeArquivo"
      :required="required"
      :data-testid="`${id}-required`"
    >
  </div>
</template>
