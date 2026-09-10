<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { normalizeBlocks, resolveMenuLinks, resolveSeo } from '@/utils/siteBlocks'
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
const brandTitle = computed(() => settings.value?.titulo || 'Site')
const heroBg = computed(() => settings.value?.cores?.primary || '#1e3a5f')

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
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: #f7f4ef; color: #1a1a1a" data-testid="public-site">
    <header class="border-b border-black/10" :style="{ background: heroBg, color: '#f7f4ef' }">
      <div class="max-w-5xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-3">
        <RouterLink
          :to="`/site/${tenantSlug}`"
          class="font-semibold text-xl tracking-tight"
          data-testid="site-brand"
        >
          {{ brandTitle }}
        </RouterLink>
        <nav class="flex flex-wrap gap-4 text-sm">
          <RouterLink
            v-for="link in menuLinks"
            :key="link.href"
            :to="link.href"
            class="opacity-90 hover:opacity-100 underline-offset-4 hover:underline"
          >
            {{ link.label }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <div v-if="loading" class="max-w-5xl mx-auto px-4 py-16 text-center text-black/60">
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
          class="max-w-5xl mx-auto px-4 py-16 text-center text-black/50"
        >
          Nenhum conteúdo publicado nesta página.
        </div>
      </div>
    </main>

    <footer class="border-t border-black/10 py-6 text-center text-sm text-black/50">
      {{ brandTitle }}
      <span v-if="settings?.subtitulo"> · {{ settings.subtitulo }}</span>
    </footer>
  </div>
</template>
