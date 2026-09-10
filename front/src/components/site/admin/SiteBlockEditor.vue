<script setup>
import { computed } from 'vue'
import { blockMeta } from '@/utils/siteBlocks'
import RichTextEditor from '@/components/form/RichTextEditor.vue'
import SiteBlockIcon from './SiteBlockIcon.vue'

const props = defineProps({
  block: { type: Object, required: true },
  forms: { type: Array, default: () => [] },
})

const meta = computed(() => blockMeta(props.block.tipo))
const payload = computed(() => props.block.payload)
const uid = computed(() => `blk-${props.block.tipo}`)
</script>

<template>
  <div class="space-y-5" data-testid="site-block-editor">
    <header class="flex items-start gap-3">
      <span
        class="h-10 w-10 shrink-0 grid place-items-center rounded-xl"
        style="background: var(--color-surface-2); color: var(--color-primary)"
      >
        <SiteBlockIcon :tipo="block.tipo" />
      </span>
      <div>
        <h3 class="text-lg leading-tight">{{ meta.label }}</h3>
        <p class="text-[13px] mt-0.5" style="color: var(--color-muted)">{{ meta.descricao }}</p>
      </div>
    </header>

    <template v-if="block.tipo === 'hero'">
      <div>
        <label class="fld" :for="`${uid}-headline`">Título principal</label>
        <input
          :id="`${uid}-headline`"
          v-model="payload.headline"
          class="input"
          placeholder="Ex.: Bem-vindos à nossa comunidade"
          data-testid="block-hero-headline"
        />
      </div>
      <div>
        <label class="fld" :for="`${uid}-texto`">Texto de apoio</label>
        <textarea :id="`${uid}-texto`" v-model="payload.texto" class="input" rows="3" />
      </div>
      <div>
        <label class="fld" :for="`${uid}-banner`">Imagem de fundo (URL)</label>
        <input :id="`${uid}-banner`" v-model="payload.banner_url" class="input" placeholder="https://…" />
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="fld" :for="`${uid}-cta-label`">Texto do botão</label>
          <input :id="`${uid}-cta-label`" v-model="payload.cta_label" class="input" placeholder="Saiba mais" />
        </div>
        <div>
          <label class="fld" :for="`${uid}-cta-href`">Link do botão</label>
          <input :id="`${uid}-cta-href`" v-model="payload.cta_href" class="input" placeholder="/site/…" />
        </div>
      </div>
    </template>

    <template v-else-if="block.tipo === 'banner'">
      <div>
        <label class="fld" :for="`${uid}-image`">Imagem (URL)</label>
        <input :id="`${uid}-image`" v-model="payload.image_url" class="input" placeholder="https://…" />
      </div>
      <div>
        <label class="fld" :for="`${uid}-alt`">Texto alternativo</label>
        <input
          :id="`${uid}-alt`"
          v-model="payload.alt"
          class="input"
          placeholder="Descrição da imagem para leitores de tela"
        />
      </div>
    </template>

    <template v-else-if="block.tipo === 'richtext'">
      <div data-testid="block-richtext-content">
        <span class="fld">Conteúdo</span>
        <RichTextEditor v-model="payload.html" />
      </div>
    </template>

    <template v-else-if="block.tipo === 'html'">
      <div>
        <label class="fld" :for="`${uid}-html`">HTML</label>
        <textarea
          :id="`${uid}-html`"
          v-model="payload.html"
          class="input font-mono text-[13px] leading-relaxed"
          rows="12"
          data-testid="block-html-content"
        />
        <p class="text-xs mt-1.5" style="color: var(--color-muted)">
          Use apenas para recursos que os demais blocos não cobrem.
        </p>
      </div>
    </template>

    <template v-else-if="block.tipo === 'form'">
      <div>
        <label class="fld" :for="`${uid}-titulo`">Título da seção</label>
        <input :id="`${uid}-titulo`" v-model="payload.titulo" class="input" />
      </div>
      <div>
        <label class="fld" :for="`${uid}-form`">Formulário</label>
        <select :id="`${uid}-form`" v-model="payload.form_slug" class="input" data-testid="block-form-slug">
          <option value="">Selecione…</option>
          <option v-for="f in forms" :key="f.id" :value="f.slug">{{ f.nome }} (/{{ f.slug }})</option>
        </select>
        <p v-if="!forms.length" class="text-xs mt-1.5" style="color: var(--color-muted)">
          Nenhum formulário criado ainda — use a aba Formulários.
        </p>
      </div>
    </template>

    <template v-else>
      <div>
        <label class="fld" :for="`${uid}-titulo`">Título da seção</label>
        <input :id="`${uid}-titulo`" v-model="payload.titulo" class="input" />
      </div>
      <p
        class="text-[13px] rounded-xl px-3.5 py-3"
        style="background: var(--color-surface-2); color: var(--color-muted)"
      >
        O conteúdo desta seção vem dos cadastros publicados no site — não precisa ser digitado aqui.
      </p>
    </template>
  </div>
</template>
