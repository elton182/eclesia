<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { normalizeBlocks, resolveMenuLinks, resolveSeo } from '@/utils/siteBlocks'
import { rememberTenantForLogin } from '@/utils/tenantAuth'
import { applyBrandCores } from '@/utils/branding'
import { useBrandingStore } from '@/stores/branding'
import SiteBlockRenderer from '@/components/site/SiteBlockRenderer.vue'

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()
const brandingStore = useBrandingStore()

const loading = ref(true)
const error = ref('')
const settings = ref(null)
const page = ref(null)

const tenantSlug = computed(() => String(route.params.tenantSlug || ''))
const pageSlug = computed(() => {
  const s = route.params.pageSlug
  return s ? String(s) : null
})

const menuLinks = computed(() => resolveMenuLinks(settings.value?.menu, tenantSlug.value))
const blocks = computed(() => normalizeBlocks(page.value?.blocks))
const brandTitle = computed(() => settings.value?.titulo || 'Paróquia')
const brandInitial = computed(() => (brandTitle.value || 'P')[0].toUpperCase())
const brandLogo = computed(() => settings.value?.logo_url || null)

async function load() {
  loading.value = true
  error.value = ''
  if (!tenantSlug.value) {
    error.value = 'Organização não informada.'
    loading.value = false
    return
  }

  tenantStore.select({ slug: tenantSlug.value, name: tenantSlug.value })

  try {
    if (!pageSlug.value) {
      const { data } = await api.get('/public/site')
      settings.value = data.data?.settings
      page.value = data.data?.page
    } else {
      const [homeRes, pageRes] = await Promise.all([
        api.get('/public/site'),
        api.get(`/public/site/pages/${pageSlug.value}`),
      ])
      settings.value = homeRes.data.data?.settings
      page.value = pageRes.data.data
    }

    if (settings.value?.titulo) {
      tenantStore.select({ slug: tenantSlug.value, name: settings.value.titulo })
    }

    const seo = resolveSeo(
      page.value?.seo || settings.value?.seo,
      page.value?.titulo || brandTitle.value,
    )
    document.title = seo.title
    applyBrandCores(settings.value?.cores)
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Site não publicado ou página não encontrada.'
      : (e.response?.data?.message || 'Falha ao carregar o site.')
    settings.value = null
    page.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => [route.params.tenantSlug, route.params.pageSlug], load)
onUnmounted(() => {
  // Não apagar cores do app se a sessão já carregou branding (evita vinho default pós-login)
  brandingStore.reapply()
})

function onSiteLogin() {
  rememberTenantForLogin(tenantSlug.value)
}
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    style="background: var(--color-surface); color: var(--color-ink)"
    data-testid="public-site"
  >
    <header
      class="sticky top-0 z-20 px-6 md:px-8 h-16 flex items-center justify-between gap-4"
      style="background: color-mix(in srgb, var(--color-surface) 94%, transparent); border-bottom: 1px solid color-mix(in srgb, var(--color-ink) 9%, transparent)"
    >
      <RouterLink
        :to="`/site/${tenantSlug}`"
        class="flex items-center gap-2.5 no-underline min-w-0"
        data-testid="site-brand"
      >
        <img
          v-if="brandLogo"
          :src="brandLogo"
          :alt="brandTitle"
          class="w-9 h-9 rounded-full object-cover shrink-0"
          data-testid="site-brand-logo"
        />
        <div
          v-else
          class="w-9 h-9 rounded-full flex items-center justify-center font-serif text-[14px] font-medium shrink-0"
          style="background: var(--color-primary-soft); color: var(--color-on-primary)"
          data-testid="site-brand-initial"
        >
          {{ brandInitial }}
        </div>
        <div class="min-w-0">
          <div
            class="font-serif text-[15px] font-medium leading-tight truncate"
            style="color: var(--color-ink)"
          >
            {{ brandTitle }}
          </div>
        </div>
      </RouterLink>

      <div class="flex items-center gap-3 shrink-0">
        <nav class="hidden md:flex items-center gap-5 text-[13px]" style="color: color-mix(in srgb, var(--color-ink) 70%, transparent)">
          <template v-for="link in menuLinks" :key="link.href">
            <a
              v-if="link.href.startsWith('#')"
              :href="link.href"
              class="no-underline hover:opacity-80"
            >{{ link.label }}</a>
            <RouterLink
              v-else
              :to="link.href"
              class="no-underline hover:opacity-80"
            >{{ link.label }}</RouterLink>
          </template>
          <a
            href="#contato"
            class="no-underline px-3.5 py-2 rounded-md font-medium"
            style="background: var(--color-primary-soft); color: var(--color-surface)"
          >Fale conosco</a>
        </nav>

        <RouterLink
          to="/entrar"
          class="no-underline px-3.5 py-2 rounded-md font-medium text-[13px] shrink-0"
          style="border: 1px solid var(--color-primary-soft); color: var(--color-primary-soft)"
          data-testid="site-login"
          @click="onSiteLogin"
        >
          Entrar
        </RouterLink>
      </div>
    </header>

    <main class="flex-1">
      <div v-if="loading" class="max-w-5xl mx-auto px-4 py-16 text-center" style="color: var(--color-muted)">
        Carregando…
      </div>
      <div v-else-if="error" class="max-w-5xl mx-auto px-4 py-16 text-center" data-testid="site-error">
        <p class="text-lg mb-4">{{ error }}</p>
        <button class="underline" type="button" @click="router.push('/')">Voltar</button>
      </div>
      <div v-else>
        <SiteBlockRenderer
          v-for="(block, idx) in blocks"
          :key="block.id || idx"
          :block="block"
          :tenant-slug="tenantSlug"
        />
        <div
          v-if="!blocks.length"
          class="max-w-5xl mx-auto px-4 py-16 text-center"
          style="color: var(--color-muted)"
        >
          Nenhum conteúdo publicado nesta página.
        </div>
      </div>
    </main>

    <footer
      class="px-6 md:px-8 py-7 flex flex-wrap items-center justify-between gap-5"
      style="background: var(--color-ink)"
    >
      <div class="text-[12.5px] leading-relaxed" style="color: color-mix(in srgb, var(--color-surface) 60%, transparent)">
        {{ brandTitle }} · site publicado com Eclesia
      </div>
      <div class="flex gap-2">
        <span
          v-for="net in ['ig', 'fb', 'yt', 'wa']"
          :key="net"
          class="w-8 h-8 rounded-full border flex items-center justify-center text-[11.5px] font-medium"
          style="border-color: color-mix(in srgb, var(--color-surface) 28%, transparent); color: color-mix(in srgb, var(--color-surface) 80%, transparent)"
        >{{ net }}</span>
      </div>
    </footer>
  </div>
</template>
