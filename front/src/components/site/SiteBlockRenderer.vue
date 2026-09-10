<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import SitePublicForm from '@/components/site/SitePublicForm.vue'
import { blockAnchor } from '@/utils/siteBlocks'

const props = defineProps({
  block: { type: Object, required: true },
  tenantSlug: { type: String, required: true },
  highlight: { type: Boolean, default: false },
})

const payload = computed(() => props.block?.payload || {})
const tipo = computed(() => props.block?.tipo || '')
const sectionId = computed(() => blockAnchor(tipo.value) || undefined)

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
const missas = computed(() => (Array.isArray(payload.value.items) ? payload.value.items : []))
const agenda = computed(() => (Array.isArray(payload.value.items) ? payload.value.items : []))
const equipe = computed(() => (Array.isArray(payload.value.items) ? payload.value.items : []))
const stats = computed(() => (Array.isArray(payload.value.stats) ? payload.value.stats : []))

function formatComDate(item) {
  if (item?.publicado_em) {
    try {
      const d = new Date(item.publicado_em)
      return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }).toUpperCase()
    } catch {
      /* fallthrough */
    }
  }
  return item?.data || ''
}
</script>

<template>
  <section
    :id="sectionId"
    class="relative"
    :class="{ 'site-block--editing': highlight }"
    :data-testid="`block-${tipo}`"
  >
    <span
      v-if="highlight"
      class="absolute top-0 left-3 z-10 text-[9px] font-medium px-2 py-0.5 rounded-full -translate-y-1/2"
      style="background: #8A2436; color: #FFFDFA"
    >editando</span>

    <!-- HERO 1g -->
    <div
      v-if="tipo === 'hero'"
      class="px-6 md:px-8 py-16 md:py-[74px]"
      style="background: #4E1220"
    >
      <div class="max-w-6xl mx-auto grid md:grid-cols-[1.15fr_.85fr] gap-10 items-center">
        <div>
          <div
            class="text-[11px] font-medium tracking-[0.14em] uppercase mb-4"
            style="color: #C88A5E"
          >
            {{ payload.eyebrow || 'Bem-vindo à nossa casa' }}
          </div>
          <h1
            class="font-serif font-normal text-[36px] md:text-[46px] leading-[1.14] text-pretty mb-4"
            style="color: #FFFDFA"
          >
            {{ payload.headline || 'Uma comunidade de fé a serviço das famílias.' }}
          </h1>
          <p
            v-if="payload.texto"
            class="text-[15px] leading-[1.7] max-w-[460px] mb-7"
            style="color: rgba(255, 253, 250, 0.75)"
          >
            {{ payload.texto }}
          </p>
          <div class="flex flex-wrap gap-2.5">
            <a
              v-if="payload.cta_label"
              :href="payload.cta_href || '#missas'"
              class="inline-block px-5 py-3 rounded-lg text-[13.5px] font-medium no-underline"
              style="background: #FFFDFA; color: #4E1220"
            >{{ payload.cta_label }}</a>
            <a
              v-if="payload.cta2_label"
              :href="payload.cta2_href || '#pastorais'"
              class="inline-block px-5 py-3 rounded-lg text-[13.5px] font-medium no-underline"
              style="border: 1px solid rgba(255, 253, 250, 0.35); color: #FFFDFA"
            >{{ payload.cta2_label }}</a>
          </div>
        </div>
        <div
          class="h-[240px] md:h-[300px] rounded-xl border flex items-end p-4 bg-cover bg-center"
          :style="{
            borderColor: 'rgba(255,253,250,0.22)',
            backgroundImage: bannerUrl
              ? `url(${bannerUrl})`
              : 'repeating-linear-gradient(135deg, rgba(255,253,250,.09) 0 8px, transparent 8px 16px)',
          }"
        >
          <span
            v-if="!bannerUrl"
            class="font-mono text-[11px]"
            style="color: rgba(255, 253, 250, 0.6)"
          >foto da fachada da igreja</span>
        </div>
      </div>
    </div>

    <!-- MISSAS -->
    <div
      v-else-if="tipo === 'missas_horarios'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto grid md:grid-cols-[280px_1fr] gap-9">
        <div>
          <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
            {{ payload.eyebrow || 'Missas' }}
          </div>
          <h2 class="font-serif text-[28px] leading-tight mb-3" style="color: #2A1418">
            {{ payload.titulo || 'Horários da semana' }}
          </h2>
          <p class="text-[13.5px] leading-relaxed" style="color: rgba(42, 20, 24, 0.62)">
            {{ payload.texto }}
          </p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <div
            v-for="(m, i) in missas"
            :key="i"
            class="rounded-[10px] p-4"
            style="border: 1px solid rgba(42, 20, 24, 0.1); background: #FBF8F4"
          >
            <div class="text-[12.5px] font-medium mb-2" style="color: #8A2436">{{ m.dia }}</div>
            <div class="font-serif text-[22px] leading-none" style="color: #2A1418">{{ m.hora }}</div>
            <div class="text-[12px] mt-2" style="color: rgba(42, 20, 24, 0.62)">{{ m.local }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- SOBRE -->
    <div
      v-else-if="tipo === 'sobre_paroquia'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 items-center">
        <div
          class="h-[220px] md:h-[280px] rounded-xl border flex items-end p-4 bg-cover bg-center"
          :style="{
            borderColor: 'rgba(42,20,24,0.14)',
            backgroundImage: bannerUrl
              ? `url(${bannerUrl})`
              : 'repeating-linear-gradient(135deg, rgba(42,20,24,.06) 0 8px, transparent 8px 16px)',
          }"
        >
          <span v-if="!bannerUrl" class="font-mono text-[11px]" style="color: rgba(42, 20, 24, 0.62)">
            foto histórica da comunidade
          </span>
        </div>
        <div>
          <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
            {{ payload.eyebrow || 'Sobre nós' }}
          </div>
          <h2 class="font-serif text-[28px] leading-snug mb-4" style="color: #2A1418">
            {{ payload.titulo || 'Nossa história' }}
          </h2>
          <p class="text-[14px] leading-[1.75] mb-5" style="color: rgba(42, 20, 24, 0.7)">
            {{ payload.texto }}
          </p>
          <div class="flex gap-8">
            <div v-for="(s, i) in stats" :key="i">
              <div class="font-serif text-[25px]" style="color: #6B1C2B">{{ s.valor }}</div>
              <div class="text-[12px] mt-1" style="color: rgba(42, 20, 24, 0.62)">{{ s.rotulo }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PASTORAIS -->
    <div
      v-else-if="tipo === 'pastorais_list'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="background: #F7F4EF; border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto">
        <div class="flex items-end justify-between mb-6 gap-4">
          <div>
            <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
              {{ payload.eyebrow || 'Pastorais e movimentos' }}
            </div>
            <h2 class="font-serif text-[28px]" style="color: #2A1418">
              {{ payload.titulo || 'Onde servir' }}
            </h2>
          </div>
        </div>
        <p v-if="listLoading" class="text-sm" style="color: rgba(42, 20, 24, 0.5)">Carregando…</p>
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
          <div
            v-for="item in listItems"
            :key="item.id"
            class="rounded-[10px] p-[18px] flex flex-col gap-2"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
          >
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center font-serif text-[14px] font-medium"
              style="background: #F3EDE6; color: #6B1C2B"
            >
              {{ (item.nome || '?')[0] }}
            </div>
            <div class="text-[14px] font-medium" style="color: #2A1418">{{ item.nome }}</div>
            <div class="text-[12px] leading-relaxed" style="color: rgba(42, 20, 24, 0.62)">
              {{ item.descricao_publica || item.contato_publico || '' }}
            </div>
          </div>
          <div
            v-if="!listItems.length"
            class="col-span-full text-sm"
            style="color: rgba(42, 20, 24, 0.5)"
          >
            Nenhuma pastoral publicada ainda.
          </div>
        </div>
      </div>
    </div>

    <!-- COMUNICADOS -->
    <div
      v-else-if="tipo === 'comunicados_list'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto">
        <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
          {{ payload.eyebrow || 'Comunicados' }}
        </div>
        <h2 class="font-serif text-[28px] mb-5" style="color: #2A1418">
          {{ payload.titulo || 'Avisos da paróquia' }}
        </h2>
        <p v-if="listLoading" class="text-sm" style="color: rgba(42, 20, 24, 0.5)">Carregando…</p>
        <div v-else>
          <div
            v-for="item in listItems"
            :key="item.id"
            class="py-[18px]"
            style="border-top: 1px solid rgba(42, 20, 24, 0.1)"
          >
            <div class="flex items-center gap-2.5 mb-1.5">
              <span class="font-mono text-[11.5px]" style="color: rgba(42, 20, 24, 0.62)">
                {{ formatComDate(item) }}
              </span>
              <span
                v-if="item.categoria || item.tag"
                class="text-[10.5px] font-medium tracking-wide uppercase px-2 py-0.5 rounded-full"
                style="color: #8A2436; background: #F6E9EB"
              >{{ item.categoria || item.tag }}</span>
            </div>
            <div class="font-serif text-[16px] font-medium mb-1" style="color: #2A1418">
              {{ item.titulo }}
            </div>
            <div class="text-[13.5px] leading-relaxed" style="color: rgba(42, 20, 24, 0.65)">
              {{ item.resumo }}
            </div>
          </div>
          <div v-if="!listItems.length" class="text-sm" style="color: rgba(42, 20, 24, 0.5)">
            Nenhum comunicado publicado.
          </div>
        </div>
      </div>
    </div>

    <!-- AGENDA -->
    <div
      v-else-if="tipo === 'agenda_eventos'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto">
        <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
          {{ payload.eyebrow || 'Agenda' }}
        </div>
        <h2 class="font-serif text-[28px] mb-5" style="color: #2A1418">
          {{ payload.titulo || 'Próximos eventos' }}
        </h2>
        <div class="flex flex-col gap-2.5 max-w-lg">
          <div
            v-for="(a, i) in agenda"
            :key="i"
            class="flex gap-3.5 items-center rounded-[10px] px-4 py-3"
            style="background: #FBF8F4; border: 1px solid rgba(42, 20, 24, 0.09)"
          >
            <div class="w-12 text-center shrink-0">
              <div class="font-serif text-[21px] leading-none" style="color: #6B1C2B">{{ a.dia }}</div>
              <div class="text-[10.5px] font-medium tracking-wide uppercase mt-1" style="color: rgba(42, 20, 24, 0.62)">
                {{ a.mes }}
              </div>
            </div>
            <div class="w-px self-stretch" style="background: rgba(42, 20, 24, 0.1)" />
            <div class="min-w-0">
              <div class="text-[13.5px] font-medium" style="color: #2A1418">{{ a.titulo }}</div>
              <div class="text-[12px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">{{ a.info }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CLERO -->
    <div
      v-else-if="tipo === 'equipe_clero'"
      class="px-6 md:px-8 py-12 md:py-14"
      style="background: #F7F4EF; border-bottom: 1px solid rgba(42, 20, 24, 0.08)"
    >
      <div class="max-w-6xl mx-auto">
        <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
          {{ payload.eyebrow || 'Clero e equipe' }}
        </div>
        <h2 class="font-serif text-[28px] mb-6" style="color: #2A1418">
          {{ payload.titulo || 'Quem caminha com você' }}
        </h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="(q, i) in equipe"
            :key="i"
            class="rounded-[10px] overflow-hidden"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
          >
            <div
              class="h-[150px] flex items-end p-2.5 bg-cover bg-center"
              :style="{
                borderBottom: '1px solid rgba(42,20,24,0.08)',
                backgroundImage: q.foto_url
                  ? `url(${q.foto_url})`
                  : 'repeating-linear-gradient(135deg, rgba(42,20,24,.06) 0 8px, transparent 8px 16px)',
              }"
            >
              <span v-if="!q.foto_url" class="font-mono text-[10px]" style="color: rgba(42, 20, 24, 0.62)">retrato</span>
            </div>
            <div class="px-4 py-3.5">
              <div class="font-serif text-[14.5px] font-medium" style="color: #2A1418">{{ q.nome || '—' }}</div>
              <div class="text-[12px] mt-1" style="color: rgba(42, 20, 24, 0.58)">{{ q.papel }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CONTATO -->
    <div
      v-else-if="tipo === 'contato_local'"
      class="px-6 md:px-8 py-12 md:py-14"
    >
      <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-9">
        <div>
          <div class="text-[11px] font-medium tracking-[0.14em] uppercase mb-3" style="color: #B4703F">
            {{ payload.eyebrow || 'Onde estamos' }}
          </div>
          <h2 class="font-serif text-[28px] leading-tight mb-4" style="color: #2A1418">
            {{ payload.titulo || payload.endereco || 'Endereço da paróquia' }}
          </h2>
          <div
            class="h-[230px] rounded-xl border flex items-end p-3.5 mb-4"
            style="
              border-color: rgba(42, 20, 24, 0.14);
              background-image: repeating-linear-gradient(
                135deg,
                rgba(42, 20, 24, 0.06) 0 8px,
                transparent 8px 16px
              );
            "
          >
            <span class="font-mono text-[11px]" style="color: rgba(42, 20, 24, 0.62)">mapa incorporado</span>
          </div>
          <div class="flex flex-col gap-1.5 text-[13.5px]" style="color: rgba(42, 20, 24, 0.7)">
            <div v-if="payload.horario_secretaria">{{ payload.horario_secretaria }}</div>
            <div v-if="payload.telefone || payload.email">
              <span v-if="payload.telefone">{{ payload.telefone }}</span>
              <span v-if="payload.telefone && payload.email"> · </span>
              <span v-if="payload.email">{{ payload.email }}</span>
            </div>
          </div>
        </div>
        <div class="rounded-xl p-7" style="background: #4E1220">
          <div class="font-serif text-[24px] mb-2" style="color: #FFFDFA">
            {{ payload.form_titulo || 'Fale conosco' }}
          </div>
          <p class="text-[13px] leading-relaxed mb-5" style="color: rgba(255, 253, 250, 0.7)">
            {{ payload.form_texto }}
          </p>
          <SitePublicForm
            v-if="payload.form_slug"
            :slug="payload.form_slug"
            :tenant-slug="tenantSlug"
          />
          <div v-else class="space-y-2.5 opacity-80">
            <input
              disabled
              placeholder="Seu nome"
              class="w-full rounded-lg px-3 py-3 text-[13.5px] border"
              style="border-color: rgba(255,253,250,.25); background: rgba(255,253,250,.07); color: #FFFDFA"
            >
            <input
              disabled
              placeholder="E-mail ou telefone"
              class="w-full rounded-lg px-3 py-3 text-[13.5px] border"
              style="border-color: rgba(255,253,250,.25); background: rgba(255,253,250,.07); color: #FFFDFA"
            >
            <textarea
              disabled
              rows="4"
              placeholder="Como podemos ajudar?"
              class="w-full rounded-lg px-3 py-3 text-[13.5px] border resize-y"
              style="border-color: rgba(255,253,250,.25); background: rgba(255,253,250,.07); color: #FFFDFA"
            />
            <p class="text-[12px]" style="color: rgba(255,253,250,0.55)">
              Vincule um formulário no editor para ativar o envio.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- LEGACY / EXTRAS -->
    <div v-else-if="tipo === 'banner'" class="w-full">
      <img v-if="bannerUrl" :src="bannerUrl" :alt="payload.alt || ''" class="w-full max-h-[420px] object-cover">
      <div v-else class="h-40" style="background: #F3EDE6" />
    </div>

    <div
      v-else-if="tipo === 'richtext'"
      class="max-w-5xl mx-auto px-6 py-12 prose prose-neutral"
    >
      <div v-html="payload.html || payload.texto || ''" />
    </div>

    <div
      v-else-if="tipo === 'html'"
      class="max-w-5xl mx-auto px-6 py-8"
    >
      <div v-html="payload.html || ''" />
    </div>

    <div v-else-if="tipo === 'igrejas_list'" class="max-w-5xl mx-auto px-6 py-12">
      <h2 class="font-serif text-2xl mb-6" style="color: #2A1418">{{ payload.titulo || 'Nossas igrejas' }}</h2>
      <ul class="space-y-4">
        <li v-for="item in listItems" :key="item.id" class="pb-4" style="border-bottom: 1px solid rgba(42,20,24,0.1)">
          <div class="font-medium text-lg">{{ item.nome }}</div>
          <p v-if="item.descricao_publica" class="mt-1" style="color: rgba(42,20,24,0.7)">{{ item.descricao_publica }}</p>
        </li>
      </ul>
    </div>

    <div v-else-if="tipo === 'form'" class="max-w-xl mx-auto px-6 py-12">
      <h2 class="font-serif text-2xl mb-4" style="color: #2A1418">{{ payload.titulo || 'Fale conosco' }}</h2>
      <SitePublicForm v-if="payload.form_slug" :slug="payload.form_slug" :tenant-slug="tenantSlug" />
    </div>
  </section>
</template>

<style scoped>
.site-block--editing {
  outline: 2px solid #8A2436;
  outline-offset: -2px;
}
</style>
