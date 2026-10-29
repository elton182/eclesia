<script setup>
/**
 * Plano B: input file nativo visível (sem overlay/opacity).
 * Usado quando Galeria/Tirar foto falham em PWA Android.
 */
defineProps({
  accept: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  show: { type: Boolean, default: false },
  testIdPrefix: { type: String, default: 'file-fallback' },
})

defineEmits(['reveal', 'change'])
</script>

<template>
  <div class="file-picker-fallback" :data-testid="`${testIdPrefix}-wrap`">
    <button
      v-if="!show"
      type="button"
      class="file-picker-fallback__link"
      :disabled="disabled"
      :data-testid="`${testIdPrefix}-link`"
      @click="$emit('reveal')"
    >
      Não abriu? Escolher arquivo
    </button>
    <div v-else class="file-picker-fallback__native">
      <label
        class="fld mb-1"
        :for="`${testIdPrefix}-input`"
      >
        Escolher arquivo
      </label>
      <input
        :id="`${testIdPrefix}-input`"
        type="file"
        :accept="accept"
        class="file-input-fallback"
        :disabled="disabled"
        :data-testid="`${testIdPrefix}-input`"
        @change="$emit('change', $event)"
      >
    </div>
  </div>
</template>
