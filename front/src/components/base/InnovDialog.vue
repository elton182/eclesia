<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useDialogStore } from '@/stores/dialog'

const store = useDialogStore()
const inputEl = ref(null)

const showCancel = computed(() => store.type !== 'alert')
const confirmClass = computed(() =>
  store.danger ? 'btn btn-danger' : 'btn btn-primary',
)

watch(
  () => store.open,
  async (isOpen) => {
    if (!isOpen) return
    await nextTick()
    if (store.type === 'prompt' && inputEl.value) {
      inputEl.value.focus()
      inputEl.value.select?.()
    }
  },
)

function onKeydown(e) {
  if (!store.open) return
  if (e.key === 'Escape') {
    e.preventDefault()
    store.cancel()
  }
  if (e.key === 'Enter' && store.type !== 'prompt') {
    e.preventDefault()
    store.confirm()
  }
}

watch(
  () => store.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown)
      document.body.style.overflow = 'hidden'
    } else {
      document.removeEventListener('keydown', onKeydown)
      document.body.style.overflow = ''
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <Transition name="dialog-fade">
      <div
        v-if="store.open"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'innov-dialog-title'"
        data-testid="innov-dialog"
      >
        <div
          class="fixed inset-0"
          style="background: rgba(42, 20, 24, 0.35); backdrop-filter: blur(2px)"
          data-testid="innov-dialog-backdrop"
          @click="store.cancel()"
        />

        <div
          class="relative w-full max-w-md rounded-xl p-5 md:p-6 shadow-xl"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
        >
          <h2
            id="innov-dialog-title"
            class="font-serif text-[20px] font-medium"
            style="color: var(--color-ink)"
            data-testid="innov-dialog-title"
          >
            {{ store.title }}
          </h2>
          <p
            v-if="store.message"
            class="mt-2 text-[14px] leading-relaxed"
            style="color: var(--color-muted)"
            data-testid="innov-dialog-message"
          >
            {{ store.message }}
          </p>

          <div v-if="store.type === 'prompt'" class="mt-4">
            <label class="fld" for="innov-dialog-input">{{ store.label }}</label>
            <input
              id="innov-dialog-input"
              ref="inputEl"
              v-model="store.inputValue"
              type="text"
              class="input"
              :placeholder="store.placeholder"
              data-testid="innov-dialog-input"
              @keydown.enter.prevent="store.confirm()"
            />
          </div>

          <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
            <button
              v-if="showCancel"
              type="button"
              class="btn btn-ghost"
              data-testid="innov-dialog-cancel"
              @click="store.cancel()"
            >
              {{ store.cancelText }}
            </button>
            <button
              type="button"
              :class="confirmClass"
              data-testid="innov-dialog-confirm"
              @click="store.confirm()"
            >
              {{ store.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dialog-fade-enter-active,
.dialog-fade-leave-active {
  transition: opacity 0.2s ease;
}
.dialog-fade-enter-from,
.dialog-fade-leave-to {
  opacity: 0;
}
</style>
