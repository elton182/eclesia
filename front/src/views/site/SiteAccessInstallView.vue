<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowLeft } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { useBrandingStore } from '@/stores/branding'
import { applyBrandCores } from '@/utils/branding'
import {
  loadLoginCredentials,
  persistLoginCredentials,
  rememberTenantForLogin,
  validateTenantLoginForm,
} from '@/utils/tenantAuth'
import { clearDeferredInstallPrompt, isRunningStandalone } from '@/utils/pwa'
import {
  ensureTenantAccessManifest,
  restoreDefaultManifest,
  tenantAccessInstallPath,
  tenantAccessStartUrl,
} from '@/utils/tenantAccessManifest'
import { homePathForUser } from '@/utils/userRoles'
import {
  appInstallRoute,
  markPendingPwaInstall,
  resolveInstallPageStep,
} from '@/utils/siteAccessInstall'

library.add(faArrowLeft)

const MANIFEST_RELOAD_PREFIX = 'eclesia_pwa_tenant_manifest:'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const brandingStore = useBrandingStore()

const loading = ref(true)
const error = ref('')
const settings = ref(null)
const redirecting = ref(false)

const remembered = loadLoginCredentials()
const loginId = ref(remembered.login || remembered.email || '')
const password = ref('')
const showPassword = ref(false)
const loginLoading = ref(false)
const loginError = ref('')

const tenantSlug = computed(() => String(route.params.tenantSlug || ''))
const brandTitle = computed(() => settings.value?.titulo || tenantStore.name || 'Paróquia')
const brandInitial = computed(() => (brandTitle.value || 'P')[0].toUpperCase())
const brandLogo = computed(() => settings.value?.logo_url || null)
const siteHome = computed(() => (tenantSlug.value ? `/site/${tenantSlug.value}` : '/'))
const loginHref = computed(() => tenantAccessStartUrl(tenantSlug.value))

/**
 * Após login válido: sai de /site/* (manifest da plataforma) e abre o app com CTA de instalar.
 * O beforeinstallprompt da plataforma não dispara de forma confiável em /site/{slug}/instalar.
 */
function goToAppInstall() {
  redirecting.value = true
  markPendingPwaInstall()
  restoreDefaultManifest()
  clearDeferredInstallPrompt()
  const dest = appInstallRoute(homePathForUser(authStore.user))
  return router.replace(dest)
}

function goToChangePassword() {
  markPendingPwaInstall()
  restoreDefaultManifest()
  return router.push({ name: 'change-password' })
}

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
  if (changed) {
    clearDeferredInstallPrompt()
  }

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

    const ok = await authStore.checkAuth()
    const nextStep = resolveInstallPageStep({
      authenticated: ok,
      mustChangePassword: !!authStore.user?.must_change_password,
    })
    if (nextStep === 'change-password') {
      await goToChangePassword()
      return
    }
    if (nextStep === 'go-app-install') {
      await goToAppInstall()
      return
    }
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Site não publicado.'
      : (e.response?.data?.message || 'Falha ao carregar.')
    settings.value = null
  } finally {
    loading.value = false
  }
}

async function onLoginSubmit() {
  loginError.value = ''
  const validation = validateTenantLoginForm({
    tenant: tenantSlug.value,
    login: loginId.value,
    password: password.value,
  })
  if (!validation.ok) {
    loginError.value = validation.error
    return
  }

  loginLoading.value = true
  const result = await authStore.login(tenantSlug.value, loginId.value, password.value)
  loginLoading.value = false

  if (!result.success) {
    loginError.value = result.error || 'Falha ao entrar.'
    return
  }

  persistLoginCredentials({ tenant: tenantSlug.value, login: loginId.value })
  password.value = ''

  const nextStep = resolveInstallPageStep({
    authenticated: true,
    mustChangePassword: !!authStore.user?.must_change_password,
  })
  if (nextStep === 'change-password') {
    await goToChangePassword()
    return
  }

  await goToAppInstall()
}

onMounted(() => {
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
        data-testid="site-access-install-login-link"
        @click="rememberTenantForLogin(tenantSlug)"
      >
        Entrar no site
      </RouterLink>
    </header>

    <main class="flex-1 flex items-start justify-center px-5 py-10 md:py-16">
      <div class="w-full max-w-md">
        <div v-if="loading || redirecting" class="text-center text-[14px]" style="color: var(--color-muted)">
          {{ redirecting ? 'Abrindo o app para instalar…' : 'Carregando…' }}
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
              Entre com seu usuário. Em seguida abrimos o app para instalar o atalho na tela inicial.
            </p>
          </div>

          <form
            class="space-y-0"
            data-testid="site-access-install-login-form"
            @submit.prevent="onLoginSubmit"
          >
            <div
              v-if="loginError"
              class="p-3 text-sm rounded-lg mb-4"
              style="background: #F6E9EB; color: #8A2436"
              role="alert"
              data-testid="site-access-install-login-error"
            >
              {{ loginError }}
            </div>

            <label class="fld">Organização</label>
            <div
              class="flex items-center overflow-hidden mb-[18px] px-3.5 py-3 font-mono text-[13px] font-medium"
              style="
                border: 1px solid var(--color-line);
                border-radius: 8px;
                background: var(--color-bg);
                color: var(--color-ink);
              "
              data-testid="site-access-install-org"
            >
              {{ tenantSlug }}
            </div>

            <label for="install-login" class="fld">E-mail ou usuário</label>
            <input
              id="install-login"
              v-model="loginId"
              type="text"
              class="input mb-[18px]"
              placeholder="seu@email.com ou equipe-a"
              required
              autocomplete="username"
              data-testid="site-access-install-login-id"
            >

            <label for="install-password" class="fld">Senha</label>
            <div
              class="flex items-center mb-[22px]"
              style="border: 1px solid var(--color-line); border-radius: 8px; background: #fff"
            >
              <input
                id="install-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                class="flex-1 min-w-0 border-0 outline-none bg-transparent py-3 px-3.5 text-[14px]"
                style="color: var(--color-ink)"
                placeholder="••••••••"
                required
                autocomplete="current-password"
                data-testid="site-access-install-password"
              >
              <button
                type="button"
                class="px-3.5 text-[12.5px] font-medium shrink-0"
                style="color: var(--color-primary-soft)"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? 'ocultar' : 'mostrar' }}
              </button>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-full justify-center text-[15px] py-3.5"
              :disabled="loginLoading"
              data-testid="site-access-install-login-submit"
            >
              {{ loginLoading ? 'Entrando…' : 'Entrar e instalar' }}
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>
</template>
