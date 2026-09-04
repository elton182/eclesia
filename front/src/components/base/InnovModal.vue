<template>
  <Transition name="fade">
    <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <!-- Overlay com backdrop blur -->
      <div class="fixed inset-0 bg-gray-500/30 backdrop-blur-sm transition-opacity" @click="$emit('update:modelValue', false)"></div>

      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
      >
        <div :class="[modalSize, 'relative transform max-h-[90vh] overflow-y-auto rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all w-full']">
          <slot />
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', '8xl', '9xl', '10xl'].includes(value)
  }
});

const emit = defineEmits(['update:modelValue']);

const modalSize = computed(() => {
  const sizes = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
    '6xl': 'sm:max-w-6xl',
    '7xl': 'sm:max-w-7xl',
    '8xl': 'sm:max-w-8xl',
    '9xl': 'sm:max-w-9xl',
    '10xl': 'sm:max-w-10xl'
  };
  return sizes[props.size] || sizes.md;
});

// Controlar scroll do body quando modal estiver aberto
const preventBodyScroll = () => {
  document.body.style.overflow = 'hidden';
};

const restoreBodyScroll = () => {
  document.body.style.overflow = '';
};

// Observar mudanças no modelValue para controlar o scroll do body
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    preventBodyScroll();
  } else {
    restoreBodyScroll();
  }
}, { immediate: true });

// Limpar overflow ao desmontar o componente
onUnmounted(() => {
  restoreBodyScroll();
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style> 