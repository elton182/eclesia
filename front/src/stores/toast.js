import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([]);
  let toastId = 0;

  const showToast = (type, title, message, timeout = 5000) => {
    const id = toastId++;
    const startTime = Date.now();
    
    const toast = {
      id,
      type,
      title,
      message,
      progress: 100
    };
    
    toasts.value.push(toast);
    
    // Atualiza a barra de progresso a cada 50ms para melhor performance
    const progressInterval = setInterval(() => {
      const elapsed = Date.now() - startTime;
      const remaining = timeout - elapsed;
      const progress = Math.max(0, (remaining / timeout) * 100);
      
      if (progress <= 0) {
        clearInterval(progressInterval);
        removeToast(id);
      } else {
        const toastIndex = toasts.value.findIndex(t => t.id === id);
        if (toastIndex !== -1) {
          toasts.value[toastIndex].progress = progress;
        }
      }
    }, 50);
    
    // Remove o toast após o timeout
    setTimeout(() => {
      removeToast(id);
    }, timeout);
  };

  const removeToast = (id) => {
    toasts.value = toasts.value.filter(toast => toast.id !== id);
  };

  return {
    toasts,
    showToast,
    removeToast
  };
}); 