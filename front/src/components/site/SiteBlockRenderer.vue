<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import SitePublicForm from '@/components/site/SitePublicForm.vue'

const props = defineProps({
  block: { type: Object, required: true },
  tenantSlug: { type: String, required: true },
})

const payload = computed(() => props.block?.payload || {})
const tipo = computed(() => props.block?.tipo || '')

const listItems = ref([])
const listLoading = ref(false)

async function loadList() {
  listItems.value = []
  if (!['igrejas_list', 'comunicados_list', 'pastorais_list'].includes(tipo.value)) return
  listLoading.value = true
  try {
    const path =
      tipo.value === 'igrejas_list'
        ? '/public/site/igrejas'
        : tipo.value === 'comunicados_list'
          ? '/public/site/comunicados'
          : '/public/site/pastorais'
    const { data } = await api.get(path)
    listItems.value = data.data || data || []
  } catch {
    listItems.value = []
  } finally {
    listLoading.value = false
  }
}

onMounted(loadList)
watch(tipo, loadList)

const bannerUrl = computed(() => payload.value.image_url || payload.value.banner_url || null)
</script>

<template>
  <section v-if="tipo === 'hero'" class="relative min-h-[70vh] flex items-end" data-testid="block-hero">
    <div
      class="absolute inset-0 bg-cover bg-center"
      :style="{
        backgroundImage: bannerUrl ? `url(${bannerUrl})` : 'linear-gradient(135deg, #1e3a5f, #0f2744)',
      }"
    />
    <div class="absolute inset-0 bg-black/40" />
    <div class="relative max-w-5xl mx-auto px-4 pb-16 pt-32 text-white w-full">
      <h1 class="text-4xl md:text-5xl font-semibold tracking-tight mb-3" style="font-family: Georgia, serif">
        {{ payload.headline || 'Bem-vindos' }}
      </h1>
      <p v-if="payload.texto" class="text-lg md:text-xl max-w-2xl opacity-95 mb-6">
        {{ payload.texto }}
      </p>
      <a
        v-if="payload.cta_label && payload.cta_href"
        :href="payload.cta_href"
        class="inline-block px-5 py-2.5 bg-white text-[#1e3a5f] font-medium rounded-sm"
      >
        {{ payload.cta_label }}
      </a>
    </div>
  </section>

  <section v-else-if="tipo === 'banner'" class="w-full" data-testid="block-banner">
    <img
      v-if="bannerUrl"
      :src="bannerUrl"
      :alt="payload.alt || ''"
      class="w-full max-h-[420px] object-cover"
    />
    <div v-else class="h-40 bg-[#d4c4a8]" />
  </section>

  <section v-else-if="tipo === 'richtext'" class="max-w-5xl mx-auto px-4 py-12 prose prose-neutral" data-testid="block-richtext">
    <div v-html="payload.html || payload.texto || ''" />
  </section>

  <section v-else-if="tipo === 'html'" class="max-w-5xl mx-auto px-4 py-8" data-testid="block-html">
    <div v-html="payload.html || ''" />
  </section>

  <section v-else-if="tipo === 'igrejas_list'" class="max-w-5xl mx-auto px-4 py-12" data-testid="block-igrejas">
    <h2 class="text-2xl font-semibold mb-6" style="font-family: Georgia, serif">
      {{ payload.titulo || 'Nossas igrejas' }}
    </h2>
    <p v-if="listLoading" class="text-black/50">Carregando…</p>
    <ul v-else class="space-y-4">
      <li v-for="item in listItems" :key="item.id" class="border-b border-black/10 pb-4">
        <div class="font-medium text-lg">{{ item.nome }}</div>
        <p v-if="item.descricao_publica" class="text-black/70 mt-1">{{ item.descricao_publica }}</p>
        <p v-if="item.horario_missas" class="text-sm text-black/60 mt-1">{{ item.horario_missas }}</p>
      </li>
    </ul>
  </section>

  <section v-else-if="tipo === 'comunicados_list'" class="max-w-5xl mx-auto px-4 py-12" data-testid="block-comunicados">
    <h2 class="text-2xl font-semibold mb-6" style="font-family: Georgia, serif">
      {{ payload.titulo || 'Comunicados' }}
    </h2>
    <p v-if="listLoading" class="text-black/50">Carregando…</p>
    <ul v-else class="space-y-6">
      <li v-for="item in listItems" :key="item.id">
        <div class="font-medium text-lg">{{ item.titulo }}</div>
        <p v-if="item.resumo" class="text-black/70 mt-1">{{ item.resumo }}</p>
      </li>
    </ul>
  </section>

  <section v-else-if="tipo === 'pastorais_list'" class="max-w-5xl mx-auto px-4 py-12" data-testid="block-pastorais">
    <h2 class="text-2xl font-semibold mb-6" style="font-family: Georgia, serif">
      {{ payload.titulo || 'Pastorais' }}
    </h2>
    <p v-if="listLoading" class="text-black/50">Carregando…</p>
    <ul v-else class="grid md:grid-cols-2 gap-6">
      <li v-for="item in listItems" :key="item.id" class="border border-black/10 p-4">
        <div class="font-medium">{{ item.nome }}</div>
        <p v-if="item.descricao_publica" class="text-sm text-black/70 mt-2">{{ item.descricao_publica }}</p>
        <p v-if="item.contato_publico" class="text-sm mt-2">{{ item.contato_publico }}</p>
      </li>
    </ul>
  </section>

  <section v-else-if="tipo === 'form'" class="max-w-xl mx-auto px-4 py-12" data-testid="block-form">
    <h2 class="text-2xl font-semibold mb-4" style="font-family: Georgia, serif">
      {{ payload.titulo || 'Fale conosco' }}
    </h2>
    <SitePublicForm
      v-if="payload.form_slug"
      :slug="payload.form_slug"
      :tenant-slug="tenantSlug"
    />
    <p v-else class="text-black/50 text-sm">Formulário não configurado.</p>
  </section>
</template>
