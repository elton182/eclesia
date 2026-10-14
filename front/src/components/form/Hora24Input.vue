<script setup>
import { computed } from 'vue'
import { horas24, joinHora24, minutos24, splitHora24 } from '@/utils/hora24'

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  /** Passo dos minutos. 60 deixa só a hora cheia. */
  stepMinutos: { type: Number, default: 5 },
})

const emit = defineEmits(['update:modelValue', 'change'])

const horas = horas24()
const partes = computed(() => splitHora24(props.modelValue))
const minutos = computed(() => minutos24(props.stepMinutos, partes.value.minuto))
const soHora = computed(() => props.stepMinutos >= 60)

function emitir(valor) {
  emit('update:modelValue', valor)
  emit('change', valor)
}

function onHora(event) {
  const hora = event.target.value
  if (!hora) {
    emitir('')
    return
  }
  emitir(joinHora24(hora, soHora.value ? '00' : partes.value.minuto || '00'))
}

function onMinuto(event) {
  if (!partes.value.hora) return
  emitir(joinHora24(partes.value.hora, event.target.value))
}
</script>

<template>
  <div class="flex items-center gap-1.5" data-testid="hora24">
    <select
      :id="id"
      class="input"
      :value="partes.hora"
      :required="required"
      :disabled="disabled"
      aria-label="Hora (0 a 23)"
      data-testid="hora24-hora"
      @change="onHora"
    >
      <option value="">—</option>
      <option v-for="h in horas" :key="h" :value="h">{{ h }}</option>
    </select>
    <template v-if="!soHora">
      <span aria-hidden="true" style="color: var(--color-muted)">:</span>
      <select
        class="input"
        :value="partes.minuto || '00'"
        :disabled="disabled || !partes.hora"
        aria-label="Minutos"
        data-testid="hora24-minuto"
        @change="onMinuto"
      >
        <option v-for="m in minutos" :key="m" :value="m">{{ m }}</option>
      </select>
    </template>
  </div>
</template>
