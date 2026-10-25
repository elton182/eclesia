<script setup>
import { RouterView } from 'vue-router'
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import TopNavbar from '@/components/layout/TopNavbar.vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useIgrejaStore } from '@/stores/igreja'

const route = useRoute()
const isSidebarOpen = ref(false)
const igrejaStore = useIgrejaStore()

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const closeSidebarMobile = () => {
  if (typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches) {
    isSidebarOpen.value = false
  }
}

function onKeydown(e) {
  if (e.key === 'Escape') closeSidebarMobile()
}

onMounted(() => {
  if (window.matchMedia('(min-width: 768px)').matches) {
    isSidebarOpen.value = true
  }
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

watch(
  () => route.fullPath,
  () => closeSidebarMobile(),
)
</script>

<template>
  <div class="flex flex-col min-h-screen overflow-x-auto" style="background: var(--color-bg)">
    <TopNavbar
      class="fixed top-0 left-0 right-0 z-50"
      @toggle-sidebar="toggleSidebar"
    />

    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 top-16 z-30 md:hidden"
      style="background: rgba(42, 20, 24, 0.45)"
      data-testid="admin-nav-backdrop"
      @click="isSidebarOpen = false"
    />

    <div class="flex pt-16">
      <Sidebar :is-open="isSidebarOpen" />

      <main
        :class="[
          'flex-1 transition-all duration-300 flex flex-col min-h-[calc(100vh-4rem)] px-4 py-6 md:px-8',
          isSidebarOpen ? 'md:ml-60' : 'md:ml-20',
        ]"
      >
        <div class="flex-1 max-w-6xl w-full mx-auto">
          <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" :key="igrejaStore.currentId || 'no-igreja'" />
            </transition>
          </router-view>
        </div>
      </main>
    </div>
  </div>
</template>
