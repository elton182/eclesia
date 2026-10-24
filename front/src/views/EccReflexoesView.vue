<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const items = ref([])
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)
const selected = ref(null)

const form = ref(blankForm())
const imagemFile = ref(null)
const removerImagem = ref(false)

function blankForm() {
  return {
    titulo: '',
    passagem_biblica: '',
    texto: '',
    publicado_em: new Date().toISOString().slice(0, 10),
    status: 'publicado',
  }
}

const canManage = computed(() =>
  userHasPermission(auth.user, 'ecc.reflexoes.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }),
)

function formatDate(iso) {
  if (!iso) return ''
  try {
    const d = iso.length <= 10 ? `${iso}T12:00:00` : iso
    return new Date(d).toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return iso
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ecc/reflexoes')
    items.value = data?.data || []
  } catch (e) {
    innovToast('error', 'Reflexões', e.response?.data?.message || 'Falha ao carregar')
    items.value = []
  } finally {
    loading.value = false
  }
}

function openNew() {
  editingId.value = null
  form.value = blankForm()
  imagemFile.value = null
  removerImagem.value = false
  showForm.value = true
  selected.value = null
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    titulo: item.titulo,
    passagem_biblica: item.passagem_biblica || '',
    texto: item.texto,
    publicado_em: item.publicado_em || blankForm().publicado_em,
    status: item.status,
  }
  imagemFile.value = null
  removerImagem.value = false
  showForm.value = true
  selected.value = null
}

function openDetail(item) {
  if (canManage.value) return
  selected.value = item
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('titulo', form.value.titulo)
    fd.append('texto', form.value.texto)
    fd.append('status', form.value.status)
    if (form.value.passagem_biblica) fd.append('passagem_biblica', form.value.passagem_biblica)
    if (form.value.publicado_em) fd.append('publicado_em', form.value.publicado_em)
    if (imagemFile.value) fd.append('imagem', imagemFile.value)
    if (editingId.value && removerImagem.value) fd.append('remover_imagem', '1')

    if (editingId.value) {
      await api.post(`/ecc/reflexoes/${editingId.value}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      innovToast('success', 'Reflexões', 'Reflexão atualizada')
    } else {
      await api.post('/ecc/reflexoes', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      innovToast('success', 'Reflexões', 'Reflexão publicada')
    }
    showForm.value = false
    await load()
  } catch (e) {
    innovToast('error', 'Reflexões', e.response?.data?.message || 'Não foi possível salvar')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  const ok = await innovConfirm({
    title: 'Remover reflexão',
    message: `Remover “${item.titulo}”?`,
  })
  if (!ok) return
  try {
    await api.delete(`/ecc/reflexoes/${item.id}`)
    innovToast('success', 'Reflexões', 'Removida')
    await load()
  } catch (e) {
    innovToast('error', 'Reflexões', e.response?.data?.message || 'Falha ao remover')
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 py-5 md:py-8 w-full max-w-2xl mx-auto" data-testid="ecc-reflexoes-page">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-eyebrow">ECC</p>
        <h1 class="font-serif text-[28px] font-normal leading-tight mt-1" style="color: var(--color-ink)">
          Reflexões
        </h1>
        <p class="mt-2 text-[14px]" style="color: var(--color-muted)">
          Palavra do diácono — leia e revisit o histórico.
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn btn-primary"
        data-testid="reflexoes-nova"
        @click="openNew"
      >
        Nova reflexão
      </button>
    </header>

    <div v-if="showForm && canManage" class="card p-5 mb-6" data-testid="reflexoes-form">
      <h2 class="font-serif text-[20px] mb-4" style="color: var(--color-ink)">
        {{ editingId ? 'Editar reflexão' : 'Nova reflexão' }}
      </h2>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="ref-titulo">Título</label>
          <input id="ref-titulo" v-model="form.titulo" class="input" required maxlength="180" />
        </div>
        <div>
          <label class="fld" for="ref-passagem">Passagem bíblica</label>
          <input id="ref-passagem" v-model="form.passagem_biblica" class="input" maxlength="255" placeholder="Ex.: Jo 15,1-8" />
        </div>
        <div>
          <label class="fld" for="ref-texto">Texto</label>
          <textarea id="ref-texto" v-model="form.texto" class="input min-h-[160px]" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="fld" for="ref-data">Data</label>
            <input id="ref-data" v-model="form.publicado_em" type="date" class="input" />
          </div>
          <div>
            <label class="fld" for="ref-status">Status</label>
            <select id="ref-status" v-model="form.status" class="input">
              <option value="publicado">Publicado</option>
              <option value="rascunho">Rascunho</option>
            </select>
          </div>
        </div>
        <div>
          <label class="fld" for="ref-imagem">Imagem (opcional)</label>
          <input
            id="ref-imagem"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="input"
            @change="imagemFile = $event.target.files?.[0] || null"
          />
          <label v-if="editingId" class="inline-flex items-center gap-2 mt-2 text-[13px]">
            <input v-model="removerImagem" type="checkbox" /> Remover imagem atual
          </label>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancelar</button>
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="reflexoes-salvar">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
        </div>
      </form>
    </div>

    <article
      v-if="selected"
      class="card p-5 mb-6"
      data-testid="reflexao-detalhe"
    >
      <button
        type="button"
        class="text-[13px] mb-3 border-0 bg-transparent cursor-pointer p-0"
        style="color: var(--color-primary-hover)"
        @click="selected = null"
      >
        ← Voltar ao histórico
      </button>
      <p class="text-[12px]" style="color: var(--color-muted)">{{ formatDate(selected.publicado_em) }}</p>
      <h2 class="font-serif text-[26px] mt-1 font-normal" style="color: var(--color-ink)">
        {{ selected.titulo }}
      </h2>
      <p
        v-if="selected.passagem_biblica"
        class="mt-2 text-[14px] italic"
        style="color: var(--color-accent-dark)"
      >
        {{ selected.passagem_biblica }}
      </p>
      <img
        v-if="selected.tem_imagem && selected.imagem_url"
        :src="selected.imagem_url"
        :alt="selected.titulo"
        class="mt-4 w-full rounded-[12px] object-cover max-h-72"
      />
      <div class="mt-4 text-[15px] leading-relaxed whitespace-pre-wrap" style="color: var(--color-ink)">
        {{ selected.texto }}
      </div>
    </article>

    <div v-if="loading" class="text-[13.5px]" style="color: var(--color-muted)">Carregando…</div>
    <div
      v-else-if="!items.length"
      class="rounded-[12px] border border-dashed p-10 text-center"
      style="border-color: var(--color-line)"
      data-testid="reflexoes-vazio"
    >
      <p class="font-serif text-[20px]" style="color: var(--color-ink)">Nenhuma reflexão</p>
      <p class="text-[13.5px] mt-1" style="color: var(--color-muted)">
        Em breve o diácono publicará aqui.
      </p>
    </div>
    <ul v-else class="flex flex-col gap-3" data-testid="reflexoes-lista">
      <li
        v-for="item in items"
        :key="item.id"
        class="card p-4 text-left w-full"
        data-testid="reflexao-item"
      >
        <button
          v-if="!canManage"
          type="button"
          class="w-full text-left border-0 bg-transparent cursor-pointer p-0"
          @click="openDetail(item)"
        >
          <p class="text-[12px]" style="color: var(--color-muted)">{{ formatDate(item.publicado_em) }}</p>
          <h3 class="font-serif text-[20px] mt-1 font-normal" style="color: var(--color-ink)">
            {{ item.titulo }}
          </h3>
          <p
            v-if="item.passagem_biblica"
            class="text-[13px] mt-1 italic"
            style="color: var(--color-accent-dark)"
          >
            {{ item.passagem_biblica }}
          </p>
          <p class="text-[13.5px] mt-2 line-clamp-2" style="color: var(--color-muted)">
            {{ item.texto }}
          </p>
        </button>
        <template v-else>
          <p class="text-[12px]" style="color: var(--color-muted)">{{ formatDate(item.publicado_em) }}</p>
          <h3 class="font-serif text-[20px] mt-1 font-normal" style="color: var(--color-ink)">
            {{ item.titulo }}
          </h3>
          <p
            v-if="item.passagem_biblica"
            class="text-[13px] mt-1 italic"
            style="color: var(--color-accent-dark)"
          >
            {{ item.passagem_biblica }}
          </p>
          <div class="flex gap-2 mt-3">
            <button type="button" class="btn btn-ghost text-sm" @click="openEdit(item)">Editar</button>
            <button type="button" class="btn btn-ghost text-sm" style="color: var(--color-danger)" @click="remove(item)">
              Remover
            </button>
          </div>
        </template>
      </li>
    </ul>
  </div>
</template>
