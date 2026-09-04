import { useToastStore } from '@/stores/toast';

export const innovToast = (type, title, message, timeout = 5000) => {
  const toastStore = useToastStore();
  toastStore.showToast(type, title, message, timeout);
}; 