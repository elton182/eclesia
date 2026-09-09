<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { MARCA, NAV_PUBLICA } from '@/utils/landing'
import logoUrl from '@/assets/logo.png'

const isScrolled = ref(false)
const isMenuOpen = ref(false)
const ano = new Date().getFullYear()

const onScroll = () => {
  isScrolled.value = window.scrollY > 8
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
})
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg)">
    <header
      :class="[
        'sticky top-0 z-50 transition-all duration-300 border-b',
        isScrolled
          ? 'bg-white/90 backdrop-blur border-[var(--color-line)]'
          : 'bg-transparent border-transparent',
      ]"
      data-testid="public-header"
    >
      <div class="mx-auto max-w-6xl px-4 md:px-8 h-16 flex items-center gap-6">
        <RouterLink to="/" class="flex items-center gap-2 shrink-0" aria-label="Início">
          <img :src="logoUrl" :alt="MARCA.nome" class="h-9 w-auto object-contain" />
        </RouterLink>

        <nav class="hidden md:flex items-center gap-6 ml-auto" aria-label="Navegação principal">
          <a
            v-for="item in NAV_PUBLICA"
            :key="item.href"
            :href="item.href"
            class="text-[14.5px] font-medium transition-colors hover:opacity-70"
            style="color: var(--color-muted)"
          >
            {{ item.label }}
          </a>
        </nav>

        <RouterLink
          to="/entrar"
          class="btn btn-primary ml-auto md:ml-0"
          data-testid="header-login"
        >
          Entrar
        </RouterLink>

        <button
          type="button"
          class="md:hidden p-2 rounded-xl"
          style="color: var(--color-primary)"
          :aria-expanded="isMenuOpen"
          aria-controls="menu-publico"
          aria-label="Abrir menu"
          data-testid="public-menu-toggle"
          @click="isMenuOpen = !isMenuOpen"
        >
          <span class="block w-5 border-t-2 border-current"></span>
          <span class="block w-5 border-t-2 border-current mt-1"></span>
          <span class="block w-5 border-t-2 border-current mt-1"></span>
        </button>
      </div>

      <nav
        v-if="isMenuOpen"
        id="menu-publico"
        class="md:hidden border-t px-4 py-3 bg-white"
        style="border-color: var(--color-line)"
        aria-label="Navegação principal (mobile)"
      >
        <a
          v-for="item in NAV_PUBLICA"
          :key="item.href"
          :href="item.href"
          class="block py-2 text-[15px] font-medium"
          style="color: var(--color-ink)"
          @click="isMenuOpen = false"
        >
          {{ item.label }}
        </a>
      </nav>
    </header>

    <main class="flex-1">
      <RouterView />
    </main>

    <footer class="border-t mt-8" style="border-color: var(--color-line); background: var(--color-surface)">
      <div
        class="mx-auto max-w-6xl px-4 md:px-8 py-8 flex flex-col md:flex-row md:items-center gap-4 md:gap-8"
      >
        <div class="flex-1">
          <img :src="logoUrl" :alt="MARCA.nome" class="h-10 w-auto object-contain" />
          <p class="mt-2 text-[13px]" style="color: var(--color-muted)">
            {{ MARCA.tagline }}.
          </p>
        </div>

        <nav class="flex flex-wrap gap-x-6 gap-y-2" aria-label="Navegação do rodapé">
          <a
            v-for="item in NAV_PUBLICA"
            :key="item.href"
            :href="item.href"
            class="text-[13.5px] hover:underline"
            style="color: var(--color-muted)"
          >
            {{ item.label }}
          </a>
          <RouterLink to="/entrar" class="text-[13.5px] hover:underline" style="color: var(--color-muted)">
            Entrar
          </RouterLink>
        </nav>
      </div>

      <div
        class="border-t py-4 text-center text-[12.5px]"
        style="border-color: var(--color-line); color: var(--color-muted)"
      >
        © {{ ano }} {{ MARCA.nome }}. Todos os direitos reservados.
      </div>
    </footer>
  </div>
</template>
