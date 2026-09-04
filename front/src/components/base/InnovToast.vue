<template>
  <div class="fixed top-4 right-4 z-50">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="mb-3 p-4 rounded-lg shadow-lg max-w-md transform transition-all duration-300 hover:scale-105 relative"
        :class="getToastClasses(toast.type)"
      >
        <div class="flex items-start">
          <div class="flex-shrink-0 ">
            <i :class="getIconClasses(toast.type)" class="text-xl"></i>
          </div>
          <div class="ml-3 flex-1">
            <div class="flex justify-between items-start">
              <h3 class="text-sm font-bold tracking-wide leading-tight">{{ toast.title }}</h3>
              <button 
                @click="removeToast(toast.id)"
                style="margin-top: -5px;"
                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                title="Fechar"
              >
                <FontAwesomeIcon :icon="faTimes" class="text-base" />
              </button>
            </div>
            <div class="mt-1.5">
              <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ toast.message }}</p>
            </div>
            <div class="mt-3 h-0.5 bg-opacity-20 rounded-full overflow-hidden">
              <div 
                class="h-full transition-all duration-100"
                :class="getProgressBarClasses(toast.type)"
                :style="{ width: `${toast.progress}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToastStore } from '@/stores/toast';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faTimes } from '@fortawesome/free-solid-svg-icons';


const toastStore = useToastStore();

const getToastClasses = (type) => {
  const baseClasses = 'transform transition-all duration-300';
  switch (type) {
    case 'success':
      return `${baseClasses} bg-green-50 dark:bg-green-900/90 text-green-800 dark:text-green-100 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-800/90 backdrop-blur-sm`;
    case 'error':
      return `${baseClasses} bg-red-50 dark:bg-red-900/90 text-red-800 dark:text-red-100 border border-red-200 dark:border-red-800 hover:bg-red-100 dark:hover:bg-red-800/90 backdrop-blur-sm`;
    case 'warning':
      return `${baseClasses} bg-yellow-50 dark:bg-yellow-900/90 text-yellow-800 dark:text-yellow-100 border border-yellow-200 dark:border-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-800/90 backdrop-blur-sm`;
    case 'info':
      return `${baseClasses} bg-blue-50 dark:bg-blue-900/90 text-blue-800 dark:text-blue-100 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-800/90 backdrop-blur-sm`;
    default:
      return `${baseClasses} bg-gray-50 dark:bg-gray-900/90 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800/90 backdrop-blur-sm`;
  }
};

const getIconClasses = (type) => {
  switch (type) {
    case 'success':
      return 'fas fa-check-circle text-green-500 dark:text-green-400';
    case 'error':
      return 'fas fa-exclamation-circle text-red-500 dark:text-red-400';
    case 'warning':
      return 'fas fa-exclamation-triangle text-yellow-500 dark:text-yellow-400';
    case 'info':
      return 'fas fa-info-circle text-blue-500 dark:text-blue-400';
    default:
      return 'fas fa-info-circle text-gray-500 dark:text-gray-400';
  }
};

const getProgressBarClasses = (type) => {
  switch (type) {
    case 'success':
      return 'bg-green-500';
    case 'error':
      return 'bg-red-500';
    case 'warning':
      return 'bg-yellow-500';
    case 'info':
      return 'bg-blue-500';
    default:
      return 'bg-gray-500';
  }
};

const removeToast = (id) => {
  toastStore.removeToast(id);
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(30px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(30px);
}
</style> 