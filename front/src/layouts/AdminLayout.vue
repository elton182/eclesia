<script setup>
import { RouterView } from 'vue-router'
import { ref } from 'vue'
import TopNavbar from '@/components/layout/TopNavbar.vue'
import Sidebar from '@/components/layout/Sidebar.vue'

const isSidebarOpen = ref(true)

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}
</script>

<template>
  <div class="flex flex-col min-h-screen overflow-x-auto" style="background: var(--color-bg)">
    <TopNavbar
      class="fixed top-0 left-0 right-0 z-50"
      @toggle-sidebar="toggleSidebar"
    />

    <div class="flex pt-16">
      <Sidebar
        :is-open="isSidebarOpen"
        class="fixed left-0 top-16 h-[calc(100vh-4rem)] z-40"
      />

      <main
        :class="[
          'flex-1 transition-all duration-300 flex flex-col min-h-[calc(100vh-4rem)] px-4 py-6 md:px-8',
          isSidebarOpen ? 'md:ml-60' : 'md:ml-20',
        ]"
      >
        <div class="flex-1 max-w-6xl w-full mx-auto">
          <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </main>
    </div>
  </div>
</template>
