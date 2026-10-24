<script setup>
defineProps({
  active: {
    type: String,
    required: true,
    validator: (v) => ['equipe', 'recados', 'reflexoes'].includes(v),
  },
  recadosBadge: {
    type: String,
    default: null,
  },
})

defineEmits(['navigate'])
</script>

<template>
  <nav
    class="fixed bottom-0 inset-x-0 z-30 md:hidden"
    style="background: var(--color-surface); border-top: 1px solid var(--color-line); padding-bottom: env(safe-area-inset-bottom, 0)"
    data-testid="lider-bottom-nav"
    aria-label="Áreas do líder"
  >
    <div class="grid grid-cols-3 h-[64px] max-w-lg mx-auto">
      <button
        type="button"
        class="relative flex flex-col items-center justify-center gap-0.5 border-0 bg-transparent cursor-pointer"
        :style="{ color: active === 'equipe' ? 'var(--color-primary-soft)' : 'var(--color-muted)' }"
        data-testid="lider-nav-equipe"
        @click="$emit('navigate', 'equipe')"
      >
        <span
          v-if="active === 'equipe'"
          class="absolute top-0 left-1/2 -translate-x-1/2 h-[3px] w-10 rounded-b-full"
          style="background: var(--color-accent-dark)"
        />
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M16 11a3 3 0 1 0-2.83-4M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM4.5 19a4.5 4.5 0 0 1 9 0M13 15.5a4.5 4.5 0 0 1 6.5 3.5"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span class="text-[11px] font-medium">Minha equipe</span>
      </button>

      <button
        type="button"
        class="relative flex flex-col items-center justify-center gap-0.5 border-0 bg-transparent cursor-pointer"
        :style="{ color: active === 'recados' ? 'var(--color-primary-soft)' : 'var(--color-muted)' }"
        data-testid="lider-nav-recados"
        @click="$emit('navigate', 'recados')"
      >
        <span
          v-if="active === 'recados'"
          class="absolute top-0 left-1/2 -translate-x-1/2 h-[3px] w-10 rounded-b-full"
          style="background: var(--color-accent-dark)"
        />
        <span class="relative inline-flex">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M4 10v2a8 8 0 0 0 8 8h0a8 8 0 0 0 8-8v-2M4 10l2.5-5.5A2 2 0 0 1 8.3 3.5h7.4a2 2 0 0 1 1.8 1L20 10M9 14h6"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span
            v-if="recadosBadge"
            class="absolute -top-1 -right-2 min-w-[14px] h-[14px] px-0.5 rounded-full text-[9px] font-semibold flex items-center justify-center leading-none"
            style="background: var(--color-accent-dark); color: #fff"
            data-testid="lider-nav-recados-badge"
          >
            {{ recadosBadge === '1' ? '' : recadosBadge }}
            <span v-if="recadosBadge === '1'" class="sr-only">1 novo</span>
            <span
              v-if="recadosBadge === '1'"
              class="w-1.5 h-1.5 rounded-full bg-white"
              aria-hidden="true"
            />
          </span>
        </span>
        <span class="text-[11px] font-medium">Recados</span>
      </button>

      <button
        type="button"
        class="relative flex flex-col items-center justify-center gap-0.5 border-0 bg-transparent cursor-pointer"
        :style="{ color: active === 'reflexoes' ? 'var(--color-primary-soft)' : 'var(--color-muted)' }"
        data-testid="lider-nav-reflexoes"
        @click="$emit('navigate', 'reflexoes')"
      >
        <span
          v-if="active === 'reflexoes'"
          class="absolute top-0 left-1/2 -translate-x-1/2 h-[3px] w-10 rounded-b-full"
          style="background: var(--color-accent-dark)"
        />
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M5 4h9a3 3 0 0 1 3 3v13l-4.5-2.5L8 20V7a3 3 0 0 0-3-3Z"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <path d="M8 7h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
        <span class="text-[11px] font-medium">Reflexões</span>
      </button>
    </div>
  </nav>
</template>
