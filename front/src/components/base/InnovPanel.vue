<template>
  <div :class="panelClasses" class="mt-6 rounded-lg shadow overflow-hidden">    
    <div v-if="$slots.header" class="px-8 py-4 border-b dark:border-gray-700 border-b-2 border-blue-500">
      <slot name="header"></slot>
    </div>
    <div class="p-8">
      <slot></slot>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  height: {
    type: String,
    default: 'auto' 
  },
  bgTransparent: {
    type: Boolean,
    default: false
  }
});

const panelClasses = computed(() => {
  return {
    'h-full': props.height === 'full',
    'bg-transparent': props.bgTransparent,
    'bg-white dark:bg-gray-800': !props.bgTransparent
  };
});
</script> 
  