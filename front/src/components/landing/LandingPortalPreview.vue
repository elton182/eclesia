<script setup>
import { PORTAL_PREVIEW } from '@/utils/landing'

const preview = PORTAL_PREVIEW

function badgeStyle(badge) {
  if (badge === 'publicado') {
    return { color: '#2A6B4A', background: '#E7F1EA' }
  }
  return { color: '#B4703F', background: '#F6EDE4' }
}
</script>

<template>
  <div
    class="portal-preview relative"
    data-testid="landing-portal-preview"
    aria-label="Prévia do portal no desktop e no celular"
  >
    <!-- Desktop (browser) -->
    <div
      class="portal-preview__desktop relative overflow-hidden rounded-xl shadow-xl"
      style="background: #F7F4EF; border: 1px solid rgba(42, 20, 24, 0.12)"
      data-testid="landing-portal-desktop"
    >
      <!-- Chrome do browser -->
      <div
        class="flex items-center gap-2 px-3 h-9"
        style="background: #EFEAE3; border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
      >
        <span class="flex gap-1.5 shrink-0" aria-hidden="true">
          <span class="h-2.5 w-2.5 rounded-full" style="background: #E8A0A0"></span>
          <span class="h-2.5 w-2.5 rounded-full" style="background: #E8C98A"></span>
          <span class="h-2.5 w-2.5 rounded-full" style="background: #A8C9A0"></span>
        </span>
        <div
          class="flex-1 min-w-0 rounded-md px-2.5 py-1 text-[10px] truncate font-mono"
          style="background: #FFFDFA; color: rgba(42, 20, 24, 0.55)"
        >
          {{ preview.urlBar }}
        </div>
      </div>

      <!-- Top bar do launcher -->
      <div
        class="flex items-center justify-between px-3 h-10"
        style="background: #4E1220"
      >
        <div class="flex items-center gap-2 min-w-0">
          <span
            class="w-5 h-5 rounded-full border flex items-center justify-center font-serif text-[10px] shrink-0"
            style="border-color: #C88A5E; color: #F0D8C2"
            aria-hidden="true"
          >
            E
          </span>
          <span class="font-serif text-[12px] font-medium shrink-0" style="color: #FFFDFA">
            Eclesias
          </span>
          <span class="w-px h-3.5 shrink-0" style="background: rgba(255, 253, 250, 0.2)"></span>
          <span
            class="text-[10px] font-medium truncate rounded px-1.5 py-0.5"
            style="background: rgba(255, 253, 250, 0.09); color: #FFFDFA"
          >
            {{ preview.orgNome }}
          </span>
        </div>
        <span
          class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-medium shrink-0"
          style="background: #C88A5E; color: #4E1220"
          aria-hidden="true"
        >
          {{ preview.iniciais }}
        </span>
      </div>

      <!-- Corpo desktop -->
      <div class="px-3.5 pt-3.5 pb-4">
        <p
          class="text-[9px] font-semibold tracking-[0.14em] uppercase mb-1"
          style="color: #B4703F"
        >
          {{ preview.saudacao }}
        </p>
        <p class="font-serif text-[15px] leading-snug mb-0.5" style="color: #2A1418">
          {{ preview.tituloDesktop }}
        </p>
        <p class="text-[10px] mb-3" style="color: rgba(42, 20, 24, 0.55)">
          Você tem acesso a {{ preview.modulos.length }} módulos nesta paróquia.
        </p>

        <div class="grid grid-cols-3 gap-2">
          <div
            v-for="modulo in preview.modulos"
            :key="modulo.chave"
            class="rounded-lg p-2.5 flex flex-col gap-1.5"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
          >
            <div class="flex items-start justify-between gap-1">
              <span
                class="w-7 h-7 rounded-md flex items-center justify-center font-serif text-[12px] font-medium"
                :style="{ background: modulo.cor, color: '#F0D8C2' }"
                aria-hidden="true"
              >
                {{ modulo.letra }}
              </span>
              <span
                v-if="modulo.badge"
                class="text-[8px] font-medium px-1.5 py-0.5 rounded-full leading-none"
                :style="badgeStyle(modulo.badge)"
              >
                {{ modulo.badge }}
              </span>
            </div>
            <div>
              <p class="font-serif text-[12px] font-medium leading-tight" style="color: #2A1418">
                {{ modulo.nome }}
              </p>
              <p class="text-[9px] leading-snug mt-0.5 line-clamp-2" style="color: rgba(42, 20, 24, 0.55)">
                {{ modulo.descricao }}
              </p>
            </div>
            <p class="mt-auto text-[9px]" style="color: rgba(42, 20, 24, 0.55)">
              {{ modulo.meta }}
            </p>
          </div>
        </div>
      </div>

      <p class="sr-only">Print do portal no desktop: launcher de módulos.</p>
    </div>

    <!-- Mobile (telefone) -->
    <div
      class="portal-preview__mobile absolute z-10 overflow-hidden shadow-2xl"
      style="
        background: #F7F4EF;
        border: 2.5px solid #2A1418;
        border-radius: 22px;
        width: 148px;
      "
      data-testid="landing-portal-mobile"
    >
      <!-- Notch / status -->
      <div class="relative flex justify-center pt-1.5 pb-1" style="background: #4E1220">
        <span
          class="h-3 w-12 rounded-full"
          style="background: #1a0a0e"
          aria-hidden="true"
        ></span>
      </div>

      <!-- Header mobile -->
      <div class="px-2.5 pt-2 pb-2.5" style="background: #4E1220">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-1">
            <span
              class="w-4 h-4 rounded-full border flex items-center justify-center font-serif text-[8px]"
              style="border-color: #C88A5E; color: #F0D8C2"
              aria-hidden="true"
            >
              E
            </span>
            <span class="font-serif text-[10px] font-medium" style="color: #FFFDFA">Eclesias</span>
          </div>
          <span
            class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-medium"
            style="background: #C88A5E; color: #4E1220"
            aria-hidden="true"
          >
            {{ preview.iniciais }}
          </span>
        </div>
        <p class="font-serif text-[13px] leading-snug mb-2" style="color: #FFFDFA">
          {{ preview.tituloMobile }}
        </p>
        <div
          class="flex items-center gap-1.5 rounded-md px-1.5 py-1"
          style="background: rgba(255, 253, 250, 0.1)"
        >
          <span
            class="w-5 h-5 rounded flex items-center justify-center font-serif text-[9px] font-medium shrink-0"
            style="background: #C88A5E; color: #4E1220"
            aria-hidden="true"
          >
            {{ preview.orgNome[0] }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[9px] font-medium truncate" style="color: #FFFDFA">
              {{ preview.orgNome }}
            </p>
            <p class="text-[8px] truncate" style="color: rgba(255, 253, 250, 0.55)">
              {{ preview.comunidade }}
            </p>
          </div>
        </div>
      </div>

      <!-- Lista de módulos mobile -->
      <div class="px-2 py-2.5 flex flex-col gap-1.5">
        <p
          class="text-[8px] font-medium tracking-wider uppercase px-0.5"
          style="color: #B4703F"
        >
          Módulos
        </p>
        <div
          v-for="modulo in preview.modulos"
          :key="`m-${modulo.chave}`"
          class="flex items-center gap-2 rounded-lg p-2"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <span
            class="w-6 h-6 rounded-md flex items-center justify-center font-serif text-[11px] font-medium shrink-0"
            :style="{ background: modulo.cor, color: '#F0D8C2' }"
            aria-hidden="true"
          >
            {{ modulo.letra }}
          </span>
          <div class="min-w-0">
            <p class="font-serif text-[11px] font-medium leading-tight" style="color: #2A1418">
              {{ modulo.nome }}
            </p>
            <p class="text-[8px] truncate" style="color: rgba(42, 20, 24, 0.55)">
              {{ modulo.meta }}
            </p>
          </div>
        </div>
      </div>

      <!-- Home indicator -->
      <div class="flex justify-center pb-1.5 pt-0.5" aria-hidden="true">
        <span class="h-1 w-10 rounded-full" style="background: rgba(42, 20, 24, 0.25)"></span>
      </div>

      <p class="sr-only">Print do portal no celular: launcher de módulos.</p>
    </div>
  </div>
</template>

<style scoped>
.portal-preview {
  min-height: 260px;
  padding-bottom: 2.25rem;
  padding-right: 3.5rem;
}

.portal-preview__mobile {
  right: 0.25rem;
  bottom: 0;
}

@media (min-width: 640px) {
  .portal-preview {
    min-height: 280px;
    padding-right: 4.5rem;
    padding-bottom: 1.75rem;
  }

  .portal-preview__mobile {
    right: 0;
    bottom: 0;
    transform: translate(6%, 4%);
  }
}

@media (min-width: 1024px) {
  .portal-preview {
    min-height: 320px;
    padding-right: 5rem;
  }

  .portal-preview__mobile {
    transform: translate(10%, 6%);
  }
}
</style>
