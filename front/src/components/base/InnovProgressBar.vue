<template>
    <div>
      <div class="flex justify-between mb-1">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label }}</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ value }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
        <div 
          class="h-2.5 rounded-full transition-all duration-300"
          :class="progressColor"
          :style="{ width: `${value}%` }"
        ></div>
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
    label: {
      type: String,
      required: true
    },
    color: {
      type: String,
      default: 'primary'
    }
  })
  
  const progressColor = computed(() => {
    const colors = {
      primary: 'bg-primary-600',
      green: 'bg-green-600',
      yellow: 'bg-yellow-600',
      red: 'bg-red-600'
    }
    return colors[props.color] || colors.primary
  })
  </script> 
  