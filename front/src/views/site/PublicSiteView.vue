<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { normalizeBlocks, resolveMenuLinks, resolveSeo } from '@/utils/siteBlocks'
import { rememberTenantForLogin } from '@/utils/tenantAuth'
import SiteBlockRenderer from '@/components/site/SiteBlockRenderer.vue'

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()

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
const brandSub = computed(() => settings.value?.subtitulo || '')

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

    const seo = resolveSeo(
      page.value?.seo || settings.value?.seo,
      page.value?.titulo || brandTitle.value,
    )
    document.title = seo.title
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

function onSiteLogin() {
  rememberTenantForLogin(tenantSlug.value)
}
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    style="background: #FFFDFA; color: #2A1418"
    data-testid="public-site"
  >
    <!-- Nav 1g sticky -->
    <header
      class="sticky top-0 z-20 px-6 md:px-8 h-16 flex items-center justify-between gap-4"
      style="background: rgba(255, 253, 250, 0.94); border-bottom: 1px solid rgba(42, 20, 24, 0.09)"
    >
      <RouterLink
        :to="`/site/${tenantSlug}`"
        class="flex items-center gap-2.5 no-underline min-w-0"
        data-testid="site-brand"
      >
        <div
          class="w-[30px] h-[30px] rounded-full flex items-center justify-center font-serif text-[14px] font-medium shrink-0"
          style="background: #6B1C2B; color: #F0D8C2"
        >
          {{ brandInitial }}
        </div>
        <div class="min-w-0">
          <div class="font-serif text-[15px] font-medium leading-tight truncate" style="color: #2A1418">
            {{ brandTitle }}
          </div>
          <div
            v-if="brandSub"
            class="text-[10.5px] truncate tracking-wide"
            style="color: rgba(42, 20, 24, 0.62)"
          >
            {{ brandSub }}
          </div>
        </div>
      </RouterLink>

      <div class="flex items-center gap-3 shrink-0">
        <nav class="hidden md:flex items-center gap-5 text-[13px]" style="color: rgba(42, 20, 24, 0.7)">
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
            style="background: #6B1C2B; color: #FFFDFA"
          >Fale conosco</a>
        </nav>

        <RouterLink
          to="/entrar"
          class="no-underline px-3.5 py-2 rounded-md font-medium text-[13px] shrink-0"
          style="border: 1px solid #6B1C2B; color: #6B1C2B"
          data-testid="site-login"
          @click="onSiteLogin"
        >
          Entrar
        </RouterLink>
      </div>
    </header>

    <main class="flex-1">
      <div v-if="loading" class="max-w-5xl mx-auto px-4 py-16 text-center" style="color: rgba(42,20,24,0.6)">
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
          style="color: rgba(42,20,24,0.5)"
        >
          Nenhum conteúdo publicado nesta página.
        </div>
      </div>
    </main>

    <footer
      class="px-6 md:px-8 py-7 flex flex-wrap items-center justify-between gap-5"
      style="background: #2A1418"
    >
      <div class="text-[12.5px] leading-relaxed" style="color: rgba(255, 253, 250, 0.6)">
        {{ brandTitle }} · site publicado com Eclesias
      </div>
      <div class="flex gap-2">
        <span
          v-for="net in ['ig', 'fb', 'yt', 'wa']"
          :key="net"
          class="w-8 h-8 rounded-full border flex items-center justify-center text-[11.5px] font-medium"
          style="border-color: rgba(255, 253, 250, 0.28); color: rgba(255, 253, 250, 0.8)"
        >{{ net }}</span>
      </div>
    </footer>
  </div>
</template>
