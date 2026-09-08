<script setup>
import { RouterView } from 'vue-router'
import InnovToast from '@/components/base/InnovToast.vue'
</script>

<template>
  <RouterView v-slot="{ Component }">
    <transition name="page" mode="out-in">
      <component :is="Component" />
    </transition>
  </RouterView>
  <InnovToast />
</template>

<style>
/* Transições globais */
.page-enter-active,
.page-leave-active {
  transition: all 0.3s ease;
}

.page-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.page-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

/* Estilo global para cards e painéis */
.card, 
.bg-white, 
.dark\:bg-gray-800 {
  transition: all 0.3s ease;
}
</style>
