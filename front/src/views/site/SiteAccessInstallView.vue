<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowLeft, faDownload, faShareFromSquare } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useBrandingStore } from '@/stores/branding'
import { applyBrandCores } from '@/utils/branding'
import { rememberTenantForLogin } from '@/utils/tenantAuth'
import { usePwaInstall } from '@/composables/usePwaInstall'
import { isRunningStandalone } from '@/utils/pwa'
import {
  detectInstallPlatform,
  ensureTenantAccessManifest,
  tenantAccessInstallPath,
  tenantAccessStartUrl,
} from '@/utils/tenantAccessManifest'

library.add(faArrowLeft, faDownload, faShareFromSquare)

const MANIFEST_RELOAD_PREFIX = 'eclesia_pwa_tenant_manifest:'

const route = useRoute()
const tenantStore = useTenantStore()
const brandingStore = useBrandingStore()
const { canInstall, installing, isInstalled, install } = usePwaInstall()

const loading = ref(true)
const error = ref('')
const settings = ref(null)
const installMessage = ref('')
const platform = ref(detectInstallPlatform())

const tenantSlug = computed(() => String(route.params.tenantSlug || ''))
const brandTitle = computed(() => settings.value?.titulo || tenantStore.name || 'Paróquia')
const brandInitial = computed(() => (brandTitle.value || 'P')[0].toUpperCase())
const brandLogo = computed(() => settings.value?.logo_url || null)
const siteHome = computed(() => (tenantSlug.value ? `/site/${tenantSlug.value}` : '/'))
const loginHref = computed(() => tenantAccessStartUrl(tenantSlug.value))
const standalone = computed(() => isInstalled.value || isRunningStandalone())

const guidanceSteps = computed(() => {
  if (platform.value === 'ios') {
    return [
      'Toque em Compartilhar (□↑) na barra do Safari.',
      'Role e escolha “Adicionar à Tela de Início”.',
      'Confirme — o ícone abre o acesso desta paróquia.',
    ]
  }
  if (platform.value === 'android') {
    return [
      'Toque no menu ⋮ do Chrome.',
      'Escolha “Instalar app” ou “Adicionar à tela inicial”.',
      'Abra o ícone para entrar sem digitar o código.',
    ]
  }
  return [
    'No menu do navegador, use “Instalar app” / “Instalar Eclésias”.',
    'Ou adicione esta página aos favoritos e abra pelo atalho.',
    'O login já virá com a organização preenchida.',
  ]
})

async function loadSite() {
  loading.value = true
  error.value = ''
  if (!tenantSlug.value) {
    error.value = 'Organização não informada.'
    loading.value = false
    return
  }

  tenantStore.select({ slug: tenantSlug.value, name: tenantSlug.value })
  rememberTenantForLogin(tenantSlug.value)

  const { changed } = ensureTenantAccessManifest(tenantSlug.value)
  const reloadKey = MANIFEST_RELOAD_PREFIX + tenantSlug.value
  if (
    changed &&
    typeof sessionStorage !== 'undefined' &&
    !sessionStorage.getItem(reloadKey) &&
    !isRunningStandalone()
  ) {
    sessionStorage.setItem(reloadKey, '1')
    window.location.replace(tenantAccessInstallPath(tenantSlug.value))
    return
  }

  try {
    const { data } = await api.get('/public/site')
    settings.value = data.data?.settings
    if (settings.value?.titulo) {
      tenantStore.select({ slug: tenantSlug.value, name: settings.value.titulo })
    }
    applyBrandCores(settings.value?.cores)
    document.title = `Instalar acesso — ${brandTitle.value}`
    ensureTenantAccessManifest(tenantSlug.value)
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Site não publicado.'
      : (e.response?.data?.message || 'Falha ao carregar.')
    settings.value = null
  } finally {
    loading.value = false
  }
}

async function onInstall() {
  installMessage.value = ''
  if (!canInstall.value) {
    installMessage.value = 'Use o passo a passo abaixo para adicionar à tela inicial.'
    return
  }
  const outcome = await install()
  if (outcome === 'accepted') {
    installMessage.value = 'Pronto! Abra o ícone na tela inicial.'
  } else if (outcome === 'dismissed') {
    installMessage.value = 'Instalação cancelada. Você pode tentar de novo quando quiser.'
  } else {
    installMessage.value = 'O navegador não liberou o popup. Siga o passo a passo abaixo.'
  }
}

onMounted(() => {
  platform.value = detectInstallPlatform()
  loadSite()
})

onUnmounted(() => {
  brandingStore.reapply()
})
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    style="background: var(--color-bg); color: var(--color-ink)"
    data-testid="site-access-install-page"
  >
    <header
      class="px-5 md:px-8 h-14 flex items-center justify-between gap-3"
      style="border-bottom: 1px solid var(--color-line); background: var(--color-surface)"
    >
      <RouterLink
        :to="siteHome"
        class="inline-flex items-center gap-2 no-underline text-[13px] font-medium"
        style="color: var(--color-muted)"
        data-testid="site-access-install-back"
      >
        <FontAwesomeIcon :icon="faArrowLeft" class="text-[12px]" />
        Voltar ao site
      </RouterLink>
      <RouterLink
        :to="loginHref"
        class="btn btn-ghost text-[13px]"
        data-testid="site-access-install-login"
        @click="rememberTenantForLogin(tenantSlug)"
      >
        Entrar
      </RouterLink>
    </header>

    <main class="flex-1 flex items-start justify-center px-5 py-10 md:py-16">
      <div class="w-full max-w-md">
        <div v-if="loading" class="text-center text-[14px]" style="color: var(--color-muted)">
          Carregando…
        </div>
        <div v-else-if="error" class="text-center" data-testid="site-access-install-error">
          <p class="text-[15px] mb-4">{{ error }}</p>
          <RouterLink :to="siteHome" class="btn btn-primary">Voltar</RouterLink>
        </div>
        <div v-else class="space-y-8" data-testid="site-access-install-content">
          <div class="text-center space-y-3">
            <img
              v-if="brandLogo"
              :src="brandLogo"
              :alt="brandTitle"
              class="w-16 h-16 rounded-full object-cover mx-auto"
            >
            <div
              v-else
              class="w-16 h-16 rounded-full mx-auto flex items-center justify-center font-serif text-[22px] font-medium"
              style="background: var(--color-primary-soft); color: var(--color-on-primary)"
            >
              {{ brandInitial }}
            </div>
            <p class="page-eyebrow">Acesso rápido</p>
            <h1 class="font-serif text-[28px] font-normal leading-tight text-pretty">
              Instalar {{ brandTitle }}
            </h1>
            <p class="text-[14px] leading-relaxed" style="color: var(--color-muted)">
              Um ícone na tela inicial abre o login já com esta organização — sem digitar o código.
            </p>
          </div>

          <div
            v-if="standalone"
            class="rounded-[var(--radius)] px-4 py-3 text-[13.5px] text-center"
            style="background: var(--color-accent-soft); color: var(--color-ink)"
            data-testid="site-access-install-standalone"
          >
            Este acesso já está instalado neste dispositivo.
          </div>

          <template v-else>
            <button
              v-if="canInstall"
              type="button"
              class="btn btn-primary w-full justify-center text-[15px] py-3.5"
              data-testid="site-access-install-prompt"
              :disabled="installing"
              @click="onInstall"
            >
              <FontAwesomeIcon :icon="faDownload" class="mr-2" />
              {{ installing ? 'Abrindo…' : 'Instalar agora' }}
            </button>

            <div
              v-else
              class="space-y-4"
              data-testid="site-access-install-manual"
            >
              <button
                type="button"
                class="btn btn-primary w-full justify-center text-[15px] py-3.5"
                data-testid="site-access-install-manual-cta"
                @click="onInstall"
              >
                <FontAwesomeIcon
                  :icon="platform === 'ios' ? faShareFromSquare : faDownload"
                  class="mr-2"
                />
                Como instalar
              </button>
              <ol class="space-y-2.5 text-[13.5px] leading-relaxed pl-5 list-decimal" style="color: var(--color-ink)">
                <li v-for="(step, i) in guidanceSteps" :key="i">{{ step }}</li>
              </ol>
            </div>
          </template>

          <p
            v-if="installMessage"
            class="text-[13px] text-center"
            style="color: var(--color-muted)"
            data-testid="site-access-install-message"
          >
            {{ installMessage }}
          </p>

          <p class="text-center text-[13px]">
            <RouterLink
              :to="loginHref"
              class="no-underline font-medium"
              style="color: var(--color-primary-soft)"
              data-testid="site-access-install-go-login"
              @click="rememberTenantForLogin(tenantSlug)"
            >
              Ir para o login
            </RouterLink>
          </p>
        </div>
      </div>
    </main>
  </div>
</template>
