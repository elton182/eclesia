<template>
    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
      <div class="text-2xl font-bold" :class="valueColor">{{ value }}</div>
      <div class="text-sm text-gray-500 dark:text-gray-400">{{ label }}</div>
    </div>
  </template>
  
  <script setup>
  import { computed } from 'vue';
  
  const props = defineProps({
    value: {
      type: [String, Number],
      required: true
    },
    label: {
      type: String,
      required: true
    },
    color: {
      type: String,
      default: 'gray'
    }
  })
  
  const valueColor = computed(() => {
    const colors = {
      gray: 'text-gray-900 dark:text-white',
      green: 'text-green-600 dark:text-green-400',
      yellow: 'text-yellow-600 dark:text-yellow-400',
      red: 'text-red-600 dark:text-red-400'
    }
    return colors[props.color] || colors.gray
  })
  </script> 
  