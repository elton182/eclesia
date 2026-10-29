<script setup>
import { computed, ref } from 'vue'
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

const arquivoInput = ref(null)

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

function abrirArquivo() {
  if (props.disabled) return
  arquivoInput.value?.click()
}
</script>

<template>
  <div data-testid="comprovante-anexo">
    <label class="fld" :for="`${id}-input-arquivo`">Comprovante</label>
    <div class="flex flex-wrap gap-2 items-center">
      <!--
        label + sr-only: evita display:none, que em mobile impede o seletor de arquivo.
      -->
      <label
        class="btn btn-ghost cursor-pointer"
        :class="{ 'opacity-50 pointer-events-none': disabled }"
        :data-testid="`${id}-arquivo`"
      >
        Arquivo
        <input
          :id="`${id}-input-arquivo`"
          ref="arquivoInput"
          type="file"
          :accept="acceptArquivo"
          class="sr-only"
          :disabled="disabled"
          :data-testid="`${id}-input-arquivo`"
          @change="onPick"
        >
      </label>
      <label
        class="btn btn-ghost cursor-pointer"
        :class="{ 'opacity-50 pointer-events-none': disabled }"
        :data-testid="`${id}-camera`"
      >
        Tirar foto
        <input
          :id="`${id}-input-camera`"
          type="file"
          accept="image/*"
          capture="environment"
          class="sr-only"
          :disabled="disabled"
          :data-testid="`${id}-input-camera`"
          @change="onPick"
        >
      </label>
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
  </div>
</template>
