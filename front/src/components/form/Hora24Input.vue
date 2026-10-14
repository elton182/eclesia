<script setup>
import { ref, watch } from 'vue'
import { maskHora24, normalizarHora24 } from '@/utils/hora24'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  /** Passo dos minutos. 60 grava só a hora cheia. */
  stepMinutos: { type: Number, default: 1 },
})

const emit = defineEmits(['update:modelValue', 'change'])

const texto = ref(props.modelValue ? normalizarHora24(props.modelValue) : '')
const focado = ref(false)

watch(
  () => props.modelValue,
  (valor) => {
    if (focado.value) return
    texto.value = valor ? normalizarHora24(valor) : ''
  },
)

function publicar(valor) {
  if (valor === (props.modelValue || '')) return
  emit('update:modelValue', valor)
  emit('change', valor)
}

function onInput(event) {
  const masked = maskHora24(event.target.value)
  texto.value = masked
  if (masked === '') {
    publicar('')
    return
  }
  if (/^\d{2}:\d{2}$/.test(masked)) {
    const completo = normalizarHora24(masked, props.stepMinutos)
    if (completo) publicar(completo)
  }
}

function onBlur() {
  focado.value = false
  const normalizado = normalizarHora24(texto.value, props.stepMinutos)
  texto.value = normalizado
  publicar(normalizado)
}
</script>

<template>
  <input
    :id="id"
    :value="texto"
    type="text"
    inputmode="numeric"
    autocomplete="off"
    maxlength="5"
    placeholder="19:30"
    class="input"
    :required="required"
    :disabled="disabled"
    aria-label="Horário, 24 horas"
    data-testid="hora24"
    v-bind="$attrs"
    @focus="focado = true"
    @input="onInput"
    @blur="onBlur"
  />
</template>
