<template>
    <div :class="columnClasses">
      <slot></slot>
    </div>
  </template>
  
  <script setup>
  import { computed } from 'vue';
  
  const props = defineProps({
    span: {
      type: [String, Number],
      default: '1'
    },
    sm: {
      type: [String, Number],
      default: null
    },
    md: {
      type: [String, Number],
      default: null
    },
    lg: {
      type: [String, Number],
      default: null
    },
    xl: {
      type: [String, Number],
      default: null
    }
  });
  
  const columnClasses = computed(() => {
    const classes = {
      [`col-span-${props.span}`]: true
    };
  
    if (props.sm) classes[`sm:col-span-${props.sm}`] = true;
    if (props.md) classes[`md:col-span-${props.md}`] = true;
    if (props.lg) classes[`lg:col-span-${props.lg}`] = true;
    if (props.xl) classes[`xl:col-span-${props.xl}`] = true;
  
    return classes;
  });
  </script> 
  