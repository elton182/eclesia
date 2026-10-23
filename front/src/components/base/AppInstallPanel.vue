<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDownload, faShareFromSquare, faTimes } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { usePwaInstall } from '@/composables/usePwaInstall'
import { isRunningStandalone } from '@/utils/pwa'
import { detectInstallPlatform } from '@/utils/tenantAccessManifest'
import {
  clearPendingPwaInstall,
  shouldOfferAppInstall,
} from '@/utils/siteAccessInstall'
import { PLATFORM_BRAND } from '@/utils/brand'

library.add(faDownload, faShareFromSquare, faTimes)

const route = useRoute()
const router = useRouter()
const { canInstall, installing, isInstalled, install } = usePwaInstall()

const open = ref(false)
const message = ref('')
const showManual = ref(false)
const platform = ref(detectInstallPlatform())

const standalone = computed(() => isInstalled.value || isRunningStandalone())
const isIos = computed(() => platform.value === 'ios')
/** Só no app autenticado — não em /site/*, login ou troca de senha. */
const onAppShell = computed(() => {
  if (String(route.path || '').startsWith('/site/')) return false
  if (route.name === 'change-password') return false
  if (String(route.path || '').startsWith('/entrar')) return false
  if (String(route.path || '').startsWith('/admin')) return false
  return route.matched.some(
    (r) => r.meta?.requiresAuthTenant || r.meta?.requiresAuthTenantOrAdmin,
  )
})
const visible = computed(() => open.value && onAppShell.value && !standalone.value)

const guidanceSteps = computed(() => {
  if (platform.value === 'ios') {
    return [
      'Toque em Compartilhar (□↑) na barra do Safari.',
      'Escolha “Adicionar à Tela de Início”.',
      'Confirme para abrir o Apostolus como app.',
    ]
  }
  return [
    'Toque em “Instalar agora” para abrir o popup do navegador.',
    'Se não abrir, use o menu ⋮ → “Instalar app”.',
  ]
})

function syncFromRoute() {
  platform.value = detectInstallPlatform()
  if (standalone.value) {
    open.value = false
    clearPendingPwaInstall()
    return
  }
  if (!onAppShell.value) {
    open.value = false
    return
  }
  open.value = shouldOfferAppInstall(route.query)
  if (open.value && isIos.value) {
    showManual.value = true
  }
}

function dismiss() {
  open.value = false
  clearPendingPwaInstall()
  if (route.query.instalar) {
    const q = { ...route.query }
    delete q.instalar
    router.replace({ path: route.path, query: q })
  }
}

async function onInstall() {
  message.value = ''
  if (isIos.value) {
    showManual.value = true
    message.value = 'No iPhone use Compartilhar → Adicionar à Tela de Início.'
    return
  }
  const outcome = await install()
  if (outcome === 'accepted') {
    message.value = 'Pronto! Use o ícone na tela inicial.'
    clearPendingPwaInstall()
    return
  }
  if (outcome === 'dismissed') {
    message.value = 'Instalação cancelada.'
    return
  }
  showManual.value = true
  message.value = 'O navegador ainda não liberou o popup. Use o menu ⋮ → Instalar app.'
}

onMounted(syncFromRoute)
watch(() => route.fullPath, syncFromRoute)
watch(canInstall, (ok) => {
  if (ok && open.value) {
    showManual.value = isIos.value
  }
})
</script>

<template>
  <div
    v-if="visible"
    class="fixed top-0 left-0 right-0 z-50 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3 md:px-6"
    style="background: var(--color-primary); color: var(--color-on-primary); box-shadow: 0 8px 24px rgba(0,0,0,0.18)"
    data-testid="app-install-panel"
    role="region"
    aria-label="Instalar aplicativo"
  >
    <div class="mx-auto max-w-3xl flex items-start gap-3">
      <div class="min-w-0 flex-1 space-y-2">
        <p class="text-[15px] font-semibold leading-snug">
          Instalar {{ PLATFORM_BRAND.nome }}
        </p>
        <p class="text-[13px] leading-relaxed opacity-85">
          Você já entrou. Instale o atalho na tela inicial para abrir mais rápido da próxima vez.
        </p>
        <div class="flex flex-wrap items-center gap-2 pt-1">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-[13.5px] font-medium disabled:opacity-60"
            style="background: var(--color-accent); color: var(--color-primary)"
            data-testid="app-install-panel-button"
            :disabled="installing"
            @click="onInstall"
          >
            <FontAwesomeIcon :icon="isIos ? faShareFromSquare : faDownload" />
            {{ installing ? 'Abrindo…' : canInstall || isIos ? 'Instalar agora' : 'Tentar instalar' }}
          </button>
          <button
            type="button"
            class="text-[12.5px] underline opacity-80 hover:opacity-100"
            data-testid="app-install-panel-dismiss"
            @click="dismiss"
          >
            Agora não
          </button>
        </div>
        <ol
          v-if="showManual"
          class="list-decimal pl-5 space-y-1 text-[12.5px] opacity-90 pt-1"
          data-testid="app-install-panel-manual"
        >
          <li v-for="(step, i) in guidanceSteps" :key="i">{{ step }}</li>
        </ol>
        <p
          v-if="message"
          class="text-[12.5px] opacity-90"
          data-testid="app-install-panel-message"
        >
          {{ message }}
        </p>
      </div>
      <button
        type="button"
        class="shrink-0 p-1.5 opacity-80 hover:opacity-100"
        aria-label="Fechar"
        @click="dismiss"
      >
        <FontAwesomeIcon :icon="faTimes" />
      </button>
    </div>
  </div>
</template>
