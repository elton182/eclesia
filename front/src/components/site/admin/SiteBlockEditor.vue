<script setup>
import { computed, watch } from 'vue'
import { blockMeta } from '@/utils/siteBlocks'
import RichTextEditor from '@/components/form/RichTextEditor.vue'

const props = defineProps({
  block: { type: Object, required: true },
  forms: { type: Array, default: () => [] },
  comunicados: { type: Array, default: () => [] },
  embedded: { type: Boolean, default: false },
})

const emit = defineEmits(['dirty', 'new-comunicado', 'edit-comunicado'])

const meta = computed(() => blockMeta(props.block.tipo))
const payload = computed(() => props.block.payload || {})
const uid = computed(() => `blk-${props.block.tipo}`)

const publicados = computed(() =>
  (props.comunicados || []).filter((c) => c.status === 'publicado'),
)

watch(
  () => props.block.payload,
  () => emit('dirty'),
  { deep: true },
)

function formatComDate(item) {
  const raw = item?.publicado_em || item?.updated_at || item?.created_at
  if (!raw) return '—'
  try {
    const d = new Date(raw)
    return d
      .toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
      .replace('.', '')
      .toUpperCase()
  } catch {
    return '—'
  }
}

function comTag(item) {
  if (item?.destaque) return 'destaque'
  if (item?.status === 'rascunho') return 'rascunho'
  return 'aviso'
}

function ensureItems(key, blank) {
  if (!Array.isArray(payload.value[key])) payload.value[key] = []
  payload.value[key] = [...payload.value[key], blank]
  emit('dirty')
}
</script>

<template>
  <div class="space-y-4" data-testid="site-block-editor" :data-embedded="embedded ? '1' : '0'">
    <header v-if="!embedded" class="flex items-start gap-3 mb-1">
      <div>
        <h3 class="text-lg leading-tight" style="color: #2A1418">{{ meta.label }}</h3>
        <p class="text-[13px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">{{ meta.descricao }}</p>
      </div>
    </header>

    <template v-if="block.tipo === 'hero'">
      <div>
        <label class="se-fld" :for="`${uid}-eyebrow`">Eyebrow</label>
        <input :id="`${uid}-eyebrow`" v-model="payload.eyebrow" class="se-input" />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-headline`">Título principal</label>
        <input
          :id="`${uid}-headline`"
          v-model="payload.headline"
          class="se-input"
          placeholder="Ex.: Bem-vindos à nossa comunidade"
          data-testid="block-hero-headline"
        />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-texto`">Texto de apoio</label>
        <textarea :id="`${uid}-texto`" v-model="payload.texto" class="se-input" rows="3" />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-banner`">Imagem (URL)</label>
        <input :id="`${uid}-banner`" v-model="payload.banner_url" class="se-input" placeholder="https://…" />
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="se-fld" :for="`${uid}-cta-label`">Texto do botão 1</label>
          <input :id="`${uid}-cta-label`" v-model="payload.cta_label" class="se-input" />
        </div>
        <div>
          <label class="se-fld" :for="`${uid}-cta-href`">Link do botão 1</label>
          <input :id="`${uid}-cta-href`" v-model="payload.cta_href" class="se-input" placeholder="#missas" />
        </div>
        <div>
          <label class="se-fld" :for="`${uid}-cta2-label`">Texto do botão 2</label>
          <input :id="`${uid}-cta2-label`" v-model="payload.cta2_label" class="se-input" />
        </div>
        <div>
          <label class="se-fld" :for="`${uid}-cta2-href`">Link do botão 2</label>
          <input :id="`${uid}-cta2-href`" v-model="payload.cta2_href" class="se-input" />
        </div>
      </div>
    </template>

    <template v-else-if="block.tipo === 'banner'">
      <div>
        <label class="se-fld" :for="`${uid}-image`">Imagem (URL)</label>
        <input :id="`${uid}-image`" v-model="payload.image_url" class="se-input" />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-alt`">Texto alternativo</label>
        <input :id="`${uid}-alt`" v-model="payload.alt" class="se-input" />
      </div>
    </template>

    <template v-else-if="block.tipo === 'richtext'">
      <div data-testid="block-richtext-content">
        <span class="se-fld">Conteúdo</span>
        <RichTextEditor v-model="payload.html" />
      </div>
    </template>

    <template v-else-if="block.tipo === 'html'">
      <div>
        <label class="se-fld" :for="`${uid}-html`">HTML</label>
        <textarea
          :id="`${uid}-html`"
          v-model="payload.html"
          class="se-input font-mono text-[13px]"
          rows="10"
          data-testid="block-html-content"
        />
      </div>
    </template>

    <template v-else-if="block.tipo === 'form'">
      <div>
        <label class="se-fld" :for="`${uid}-titulo`">Título da seção</label>
        <input :id="`${uid}-titulo`" v-model="payload.titulo" class="se-input" />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-form`">Formulário</label>
        <select :id="`${uid}-form`" v-model="payload.form_slug" class="se-input" data-testid="block-form-slug">
          <option value="">Selecione…</option>
          <option v-for="f in forms" :key="f.id" :value="f.slug">{{ f.nome }} (/{{ f.slug }})</option>
        </select>
      </div>
    </template>

    <template v-else-if="block.tipo === 'missas_horarios'">
      <div>
        <label class="se-fld">Eyebrow</label>
        <input v-model="payload.eyebrow" class="se-input" />
      </div>
      <div>
        <label class="se-fld">Título</label>
        <input v-model="payload.titulo" class="se-input" />
      </div>
      <div>
        <label class="se-fld">Texto</label>
        <textarea v-model="payload.texto" class="se-input" rows="2" />
      </div>
      <div class="space-y-2">
        <div class="se-fld">Horários</div>
        <div v-for="(item, i) in payload.items || []" :key="i" class="grid grid-cols-3 gap-2">
          <input v-model="item.dia" class="se-input" placeholder="Dia">
          <input v-model="item.hora" class="se-input" placeholder="Hora">
          <input v-model="item.local" class="se-input" placeholder="Local">
        </div>
        <button
          type="button"
          class="se-link"
          @click="ensureItems('items', { dia: '', hora: '', local: '' })"
        >+ horário</button>
      </div>
    </template>

    <template v-else-if="block.tipo === 'sobre_paroquia'">
      <div>
        <label class="se-fld">Eyebrow</label>
        <input v-model="payload.eyebrow" class="se-input" />
      </div>
      <div>
        <label class="se-fld">Título</label>
        <input v-model="payload.titulo" class="se-input" />
      </div>
      <div>
        <label class="se-fld">Texto</label>
        <textarea v-model="payload.texto" class="se-input" rows="4" />
      </div>
      <div>
        <label class="se-fld">Foto (URL)</label>
        <input v-model="payload.image_url" class="se-input" />
      </div>
    </template>

    <template v-else-if="block.tipo === 'agenda_eventos'">
      <div>
        <label class="se-fld">Título</label>
        <input v-model="payload.titulo" class="se-input" />
      </div>
      <div v-for="(item, i) in payload.items || []" :key="i" class="grid grid-cols-2 gap-2 mb-2">
        <input v-model="item.dia" class="se-input" placeholder="Dia">
        <input v-model="item.mes" class="se-input" placeholder="Mês">
        <input v-model="item.titulo" class="se-input col-span-2" placeholder="Título">
        <input v-model="item.info" class="se-input col-span-2" placeholder="Info">
      </div>
      <button
        type="button"
        class="se-link"
        @click="ensureItems('items', { dia: '', mes: '', titulo: '', info: '' })"
      >+ evento</button>
    </template>

    <template v-else-if="block.tipo === 'equipe_clero'">
      <div>
        <label class="se-fld">Título</label>
        <input v-model="payload.titulo" class="se-input" />
      </div>
      <div
        v-for="(item, i) in payload.items || []"
        :key="i"
        class="space-y-2 mb-3 p-3 rounded-[9px]"
        style="background: #FBF8F4"
      >
        <input v-model="item.nome" class="se-input" placeholder="Nome">
        <input v-model="item.papel" class="se-input" placeholder="Papel">
        <input v-model="item.foto_url" class="se-input" placeholder="Foto URL">
      </div>
      <button
        type="button"
        class="se-link"
        @click="ensureItems('items', { nome: '', papel: '', foto_url: '' })"
      >+ pessoa</button>
    </template>

    <template v-else-if="block.tipo === 'contato_local'">
      <div>
        <label class="se-fld">Título / endereço</label>
        <input v-model="payload.titulo" class="se-input" />
      </div>
      <div>
        <label class="se-fld">Horário da secretaria</label>
        <input v-model="payload.horario_secretaria" class="se-input" />
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="se-fld">Telefone</label>
          <input v-model="payload.telefone" class="se-input" />
        </div>
        <div>
          <label class="se-fld">E-mail</label>
          <input v-model="payload.email" class="se-input" />
        </div>
      </div>
      <div>
        <label class="se-fld">Formulário vinculado</label>
        <select v-model="payload.form_slug" class="se-input">
          <option value="">Nenhum</option>
          <option v-for="f in forms" :key="f.id" :value="f.slug">{{ f.nome }}</option>
        </select>
      </div>
    </template>

    <!-- Comunicados — fiel ao mock 2a -->
    <template v-else-if="block.tipo === 'comunicados_list'">
      <div>
        <label class="se-fld" :for="`${uid}-titulo`">Título da seção</label>
        <input
          :id="`${uid}-titulo`"
          v-model="payload.titulo"
          class="se-input"
          data-testid="block-comunicados-titulo"
        />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-eyebrow`">Eyebrow</label>
        <input :id="`${uid}-eyebrow`" v-model="payload.eyebrow" class="se-input" />
      </div>

      <div class="flex items-center justify-between mb-2.5 mt-1">
        <label class="se-fld mb-0">Comunicados publicados</label>
        <button
          type="button"
          class="se-link"
          data-testid="block-novo-comunicado"
          @click="emit('new-comunicado')"
        >+ novo comunicado</button>
      </div>

      <div class="flex flex-col gap-1.5" data-testid="block-comunicados-lista">
        <div
          v-if="!publicados.length"
          class="rounded-[9px] px-3.5 py-3 text-[13px]"
          style="background: #FBF8F4; color: rgba(42, 20, 24, 0.62); border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          Nenhum comunicado publicado ainda.
        </div>
        <div
          v-for="c in publicados"
          :key="c.id"
          class="flex items-center gap-3 rounded-[9px] px-3.5 py-2.5"
          style="background: #FBF8F4; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <span class="font-mono text-[11.5px] shrink-0" style="color: rgba(42, 20, 24, 0.62)">
            {{ formatComDate(c) }}
          </span>
          <span class="flex-1 min-w-0 text-[13px] font-medium truncate" style="color: #2A1418">
            {{ c.titulo }}
          </span>
          <span
            class="text-[10.5px] font-medium tracking-wide uppercase px-2 py-0.5 rounded-full shrink-0"
            style="color: #B4703F; background: #F6EDE4"
          >{{ comTag(c) }}</span>
          <button
            type="button"
            class="bg-transparent border-0 cursor-pointer text-[13px] shrink-0 px-1"
            style="color: rgba(42, 20, 24, 0.62)"
            aria-label="Editar comunicado"
            @click="emit('edit-comunicado', c)"
          >···</button>
        </div>
      </div>

      <div
        class="flex items-center gap-3 mt-4 pt-4"
        style="border-top: 1px solid rgba(42, 20, 24, 0.09)"
      >
        <button
          type="button"
          class="w-[38px] h-[22px] rounded-full flex items-center p-0.5 border-0 cursor-pointer shrink-0"
          :style="{
            background: payload.mostrar_nas_pastorais === false ? 'rgba(42,20,24,0.2)' : '#6B1C2B',
            justifyContent: payload.mostrar_nas_pastorais === false ? 'flex-start' : 'flex-end',
          }"
          data-testid="block-comunicados-toggle-pastorais"
          :aria-pressed="payload.mostrar_nas_pastorais !== false"
          @click="payload.mostrar_nas_pastorais = payload.mostrar_nas_pastorais === false; emit('dirty')"
        >
          <span class="w-[18px] h-[18px] rounded-full bg-white" />
        </button>
        <span class="text-[13px] leading-snug" style="color: rgba(42, 20, 24, 0.7)">
          Mostrar comunicados também na página de cada pastoral
        </span>
      </div>
    </template>

    <template v-else-if="block.tipo === 'pastorais_list' || block.tipo === 'igrejas_list'">
      <div>
        <label class="se-fld" :for="`${uid}-eyebrow`">Eyebrow</label>
        <input :id="`${uid}-eyebrow`" v-model="payload.eyebrow" class="se-input" />
      </div>
      <div>
        <label class="se-fld" :for="`${uid}-titulo`">Título da seção</label>
        <input :id="`${uid}-titulo`" v-model="payload.titulo" class="se-input" />
      </div>
      <p
        class="text-[13px] rounded-[9px] px-3.5 py-3"
        style="background: #FBF8F4; color: rgba(42, 20, 24, 0.62); border: 1px solid rgba(42, 20, 24, 0.1)"
      >
        O conteúdo desta seção vem dos cadastros publicados no site.
      </p>
    </template>

    <template v-else>
      <div>
        <label class="se-fld" :for="`${uid}-titulo`">Título da seção</label>
        <input :id="`${uid}-titulo`" v-model="payload.titulo" class="se-input" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.se-fld {
  display: block;
  font-size: 11.5px;
  font-weight: 500;
  color: rgba(42, 20, 24, 0.62);
  margin-bottom: 7px;
}
.se-input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid rgba(42, 20, 24, 0.15);
  border-radius: 8px;
  padding: 11px 13px;
  font-size: 13.5px;
  color: #2a1418;
  background: #fff;
  outline: none;
  margin-bottom: 4px;
}
.se-input:focus {
  border-color: rgba(106, 28, 43, 0.45);
}
.se-link {
  border: 0;
  background: transparent;
  padding: 0;
  font-size: 12.5px;
  font-weight: 500;
  color: #8a2436;
  cursor: pointer;
}
</style>
