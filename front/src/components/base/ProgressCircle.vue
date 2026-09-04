<template>
  <div class="relative inline-flex flex-col items-center">
    <!-- Círculo de Progresso -->
    <svg :width="size" :height="size" class="transform -rotate-90 drop-shadow-sm">
      <!-- Círculo de fundo -->
      <circle
        :cx="size/2"
        :cy="size/2"
        :r="radius"
        stroke="#e5e7eb"  
        fill="none"
        :stroke-width="strokeWidth"
      />
      <!-- Círculo de progresso -->
      <circle
        v-if="total > 0"
        :cx="size/2"
        :cy="size/2"
        :r="radius"
        :stroke="progressColor"
        fill="none"
        :stroke-width="strokeWidth"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        stroke-linecap="round"
        style="transition: stroke-dashoffset 0.7s cubic-bezier(.4,2,.6,1); filter: drop-shadow(0 2px 6px #3b82f680);"
      />
    </svg>

    <!-- Conteúdo Central -->
    <div class="absolute inset-0 flex flex-col items-center justify-center select-none">
      <span class="flex items-end justify-center text-4xl font-extrabold text-gray-900 dark:text-white">
        {{ value }}<span class="ml-1">/{{ total }}</span>
      </span>
      <span class="text-base text-gray-500 dark:text-gray-300 mt-1">
        {{ label }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  value: {
    type: Number,
    required: true
  },
  total: {
    type: Number,
    required: true
  },
  label: {
    type: String,
    default: 'Participantes'
  },
  size: {
    type: Number,
    default: 200
  },
  strokeWidth: {
    type: Number,
    default: 12
  },
  color: {
    type: String,
    default: '#2563eb' // azul-600
  }
});

const radius = computed(() => props.size / 2 - props.strokeWidth);
const circumference = computed(() => 2 * Math.PI * radius.value);

const progressColor = computed(() => {
  // Gradiente SVG (futuro: pode ser prop)
  return props.color;
});

const dashOffset = computed(() => {
  if (!props.total) return circumference.value;
  const progress = Math.max(0, Math.min(1, props.value / props.total));
  return circumference.value * (1 - progress);
});
</script>

<style scoped>
.drop-shadow-sm {
  filter: drop-shadow(0 2px 8px rgba(59,130,235,0.08));
}
</style> 