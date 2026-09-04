<script setup>
import { ref, watch } from 'vue'
import { isValidBrDate, maskBrDateInput } from '@/utils/dateBr'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'Data' },
  name: { type: String, required: true },
  placeholder: { type: String, default: 'dd/mm/aaaa' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  errors: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const localValue = ref(props.modelValue || '')
const localError = ref('')

watch(
  () => props.modelValue,
  (v) => {
    localValue.value = v || ''
  },
)

const onInput = (event) => {
  const masked = maskBrDateInput(event.target.value)
  localValue.value = masked
  localError.value = ''
  emit('update:modelValue', masked)
}

const onBlur = () => {
  if (!localValue.value) {
    localError.value = ''
    return
  }
  if (localValue.value.length < 10 || !isValidBrDate(localValue.value)) {
    localError.value = 'Use o formato dd/mm/aaaa'
  }
}
</script>

<template>
  <div>
    <label :for="name" class="fld">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <input
      :id="name"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      maxlength="10"
      class="input"
      :name="name"
      :value="localValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :aria-invalid="Boolean(errors || localError)"
      data-testid="date-br-input"
      @input="onInput"
      @blur="onBlur"
    />
    <p v-if="errors || localError" class="mt-1 text-sm text-red-600">
      {{ errors || localError }}
    </p>
  </div>
</template>
