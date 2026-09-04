<template>
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
      <slot></slot>
    </div>
  </template>
  
  <script setup>
  defineProps({
    title: {
      type: String,
      required: true
    },
    subtitle: {
      type: String,
      required: true
    }
  })
  </script> 
  