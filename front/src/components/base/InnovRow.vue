<template>
    <div :class="containerClasses">
      <slot></slot>
    </div>
  </template>
  
  <script setup>
  import { computed } from 'vue';
  
  const props = defineProps({
    cols: {
      type: [String, Number],
      default: '1'
    },
    gap: {
      type: [String, Number],
      default: '4'
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
  
  const containerClasses = computed(() => {
    const classes = {
      'grid': true,
      [`grid-cols-${props.cols}`]: true,
      [`gap-${props.gap}`]: true
    };
  
    if (props.sm) classes[`sm:grid-cols-${props.sm}`] = true;
    if (props.md) classes[`md:grid-cols-${props.md}`] = true;
    if (props.lg) classes[`lg:grid-cols-${props.lg}`] = true;
    if (props.xl) classes[`xl:grid-cols-${props.xl}`] = true;
  
    return classes;
  });
  </script> 
  