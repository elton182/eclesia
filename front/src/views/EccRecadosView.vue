<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'
import { formatAnexoTamanho } from '@/utils/eccMural'
import EccInteracoesBlock from '@/components/ecc/EccInteracoesBlock.vue'
import EccComentariosModeracao from '@/components/ecc/EccComentariosModeracao.vue'
import EccEngajamentoPanel from '@/components/ecc/EccEngajamentoPanel.vue'

const emit = defineEmits(['mural-seen', 'recados-seen'])

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const items = ref([])
const equipes = ref([])
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)
const selected = ref(null)
const detailLoading = ref(false)
const anexoFile = ref(null)
const removerAnexo = ref(false)
const showEngajamento = ref(false)

const form = ref(blankForm())

function blankForm() {
  return {
    titulo: '',
    corpo: '',
    urgente: false,
    fixado: false,
    escopo: 'paroquia',
    equipe_id: '',
    etapa: 1,
    valido_ate: '',
    status: 'publicado',
  }
}

const authOpts = () => ({
  isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
})

const canManage = computed(() => userHasPermission(auth.user, 'ecc.recados.manage', authOpts()))

const canModerar = computed(() =>
  userHasPermission(auth.user, 'ecc.interacoes.moderar', authOpts())
  || canManage.value,
)

function formatDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
  }
}

async function load() {
  loading.value = true
  try {
    const params = canManage.value ? {} : undefined
    const { data } = await api.get('/ecc/recados', { params })
    items.value = data?.data || []
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Falha ao carregar')
    items.value = []
  } finally {
    loading.value = false
  }
}

async function loadEquipes() {
  if (!canManage.value) return
  try {
    const { data } = await api.get('/ecc/equipes')
    equipes.value = data?.data || data || []
  } catch {
    equipes.value = []
  }
}

async function openDetail(item) {
  detailLoading.value = true
  showEngajamento.value = false
  try {
    const { data } = await api.get(`/ecc/recados/${item.id}`)
    selected.value = data?.data || null
    if (selected.value && !selected.value.lido_por_mim && selected.value.status === 'publicado') {
      const ler = await api.post(`/ecc/recados/${item.id}/ler`)
      selected.value = {
        ...selected.value,
        lido_por_mim: true,
        lido_em: ler.data?.data?.lido_em || selected.value.lido_em,
      }
      items.value = items.value.map((it) =>
        String(it.id) === String(item.id)
          ? { ...it, lido_por_mim: true, lido_em: selected.value.lido_em }
          : it,
      )
      emit('mural-seen')
      emit('recados-seen')
    }
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Não foi possível abrir')
    selected.value = null
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  selected.value = null
  showEngajamento.value = false
}

async function downloadAnexo(item) {
  try {
    const res = await api.get(`/ecc/recados/${item.id}/anexo`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = item.anexo_nome || 'anexo'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    innovToast('error', 'Anexo', e.response?.data?.message || 'Falha no download')
  }
}

function openNew() {
  editingId.value = null
  form.value = blankForm()
  anexoFile.value = null
  removerAnexo.value = false
  showForm.value = true
  selected.value = null
}

async function openEdit(item) {
  try {
    const { data } = await api.get(`/ecc/recados/${item.id}`)
    const full = data?.data || item
    editingId.value = full.id
    form.value = {
      titulo: full.titulo,
      corpo: full.corpo || '',
      urgente: !!full.urgente,
      fixado: !!full.fixado,
      escopo: full.escopo || 'paroquia',
      equipe_id: full.equipe_id || '',
      etapa: full.etapa || 1,
      valido_ate: full.valido_ate ? full.valido_ate.slice(0, 16) : '',
      status: full.status,
    }
    anexoFile.value = null
    removerAnexo.value = false
    showForm.value = true
    selected.value = null
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Falha ao abrir edição')
  }
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('titulo', form.value.titulo)
    fd.append('corpo', form.value.corpo)
    fd.append('urgente', form.value.urgente ? '1' : '0')
    fd.append('fixado', form.value.fixado ? '1' : '0')
    fd.append('escopo', form.value.escopo)
    fd.append('status', form.value.status)
    if (form.value.valido_ate) {
      fd.append('valido_ate', new Date(form.value.valido_ate).toISOString())
    }
    if (form.value.escopo === 'equipe') fd.append('equipe_id', form.value.equipe_id || '')
    if (form.value.escopo === 'etapa') fd.append('etapa', String(form.value.etapa))
    if (anexoFile.value) fd.append('anexo', anexoFile.value)
    if (editingId.value && removerAnexo.value) fd.append('remover_anexo', '1')

    if (editingId.value) {
      await api.post(`/ecc/recados/${editingId.value}`, fd)
      innovToast('success', 'Recados', 'Recado atualizado')
    } else {
      await api.post('/ecc/recados', fd)
      innovToast('success', 'Recados', 'Recado publicado')
    }
    showForm.value = false
    await load()
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Não foi possível salvar')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  const ok = await innovConfirm({
    title: 'Remover recado',
    message: `Remover “${item.titulo}”?`,
  })
  if (!ok) return
  try {
    await api.delete(`/ecc/recados/${item.id}`)
    innovToast('success', 'Recados', 'Removido')
    if (selected.value?.id === item.id) closeDetail()
    await load()
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Falha ao remover')
  }
}

async function onInteracaoUpdated(itemId, patch) {
  if (selected.value && String(selected.value.id) === String(itemId)) {
    selected.value = {
      ...selected.value,
      ...(patch.likes_count !== undefined ? { likes_count: patch.likes_count } : {}),
      ...(patch.liked_by_me !== undefined ? { liked_by_me: patch.liked_by_me } : {}),
    }
  }
  items.value = items.value.map((it) => {
    if (String(it.id) !== String(itemId)) return it
    return {
      ...it,
      ...(patch.likes_count !== undefined ? { likes_count: patch.likes_count } : {}),
      ...(patch.liked_by_me !== undefined ? { liked_by_me: patch.liked_by_me } : {}),
    }
  })
}

onMounted(async () => {
  await Promise.all([load(), loadEquipes()])
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 py-5 md:py-8 w-full max-w-2xl mx-auto" data-testid="ecc-recados-page">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-eyebrow">ECC</p>
        <h1 class="font-serif text-[28px] font-normal leading-tight mt-1" style="color: var(--color-ink)">
          Recados
        </h1>
        <p class="mt-2 text-[14px]" style="color: var(--color-muted)">
          Comunicados da secretaria com validade e prioridade.
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn btn-primary"
        data-testid="recados-novo"
        @click="openNew"
      >
        Novo recado
      </button>
    </header>

    <EccComentariosModeracao v-if="canModerar" class="mb-6" />

    <div v-if="showForm && canManage" class="card p-5 mb-6" data-testid="recados-form">
      <h2 class="font-serif text-[20px] mb-4" style="color: var(--color-ink)">
        {{ editingId ? 'Editar recado' : 'Novo recado' }}
      </h2>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="recado-titulo">Título</label>
          <input id="recado-titulo" v-model="form.titulo" class="input" required maxlength="180" />
        </div>
        <div>
          <label class="fld" for="recado-corpo">Mensagem</label>
          <textarea id="recado-corpo" v-model="form.corpo" class="input min-h-[120px]" required />
        </div>
        <div>
          <label class="fld" for="recado-anexo">Anexo (opcional)</label>
          <input
            id="recado-anexo"
            type="file"
            class="input"
            accept=".pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp"
            data-testid="recados-anexo"
            @change="anexoFile = $event.target.files?.[0] || null"
          />
          <label v-if="editingId" class="inline-flex items-center gap-2 mt-2 text-[13px]">
            <input v-model="removerAnexo" type="checkbox" /> Remover anexo atual
          </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="fld" for="recado-escopo">Escopo</label>
            <select id="recado-escopo" v-model="form.escopo" class="input">
              <option value="paroquia">Paróquia</option>
              <option value="etapa">Etapa ECC</option>
              <option value="equipe">Equipe</option>
            </select>
          </div>
          <div v-if="form.escopo === 'equipe'">
            <label class="fld" for="recado-equipe">Equipe</label>
            <select id="recado-equipe" v-model="form.equipe_id" class="input" required>
              <option value="" disabled>Selecione…</option>
              <option v-for="eq in equipes" :key="eq.id" :value="eq.id">{{ eq.nome }}</option>
            </select>
          </div>
          <div v-if="form.escopo === 'etapa'">
            <label class="fld" for="recado-etapa">Etapa</label>
            <select id="recado-etapa" v-model.number="form.etapa" class="input">
              <option :value="1">1ª etapa</option>
              <option :value="2">2ª etapa</option>
              <option :value="3">3ª etapa</option>
            </select>
          </div>
          <div>
            <label class="fld" for="recado-valido">Válido até (opcional)</label>
            <input id="recado-valido" v-model="form.valido_ate" type="datetime-local" class="input" />
          </div>
          <div>
            <label class="fld" for="recado-status">Status</label>
            <select id="recado-status" v-model="form.status" class="input">
              <option value="publicado">Publicado</option>
              <option value="rascunho">Rascunho</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-4 text-[13.5px]" style="color: var(--color-ink)">
          <label class="inline-flex items-center gap-2">
            <input v-model="form.urgente" type="checkbox" /> Urgente
          </label>
          <label class="inline-flex items-center gap-2">
            <input v-model="form.fixado" type="checkbox" /> Fixado
          </label>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancelar</button>
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="recados-salvar">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
        </div>
      </form>
    </div>

    <article
      v-if="selected"
      class="card p-5 mb-6"
      data-testid="recado-detalhe"
    >
      <button
        type="button"
        class="text-[13px] mb-3 border-0 bg-transparent cursor-pointer p-0"
        style="color: var(--color-primary-hover)"
        @click="closeDetail"
      >
        ← Voltar à lista
      </button>
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <span
          v-if="selected.fixado"
          class="text-[11px] px-2 py-0.5 rounded-full font-medium"
          style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
        >Fixado</span>
        <span
          v-if="selected.urgente"
          class="text-[11px] px-2 py-0.5 rounded-full font-medium"
          style="background: color-mix(in srgb, var(--color-danger) 14%, transparent); color: var(--color-danger)"
        >Urgente</span>
      </div>
      <h2 class="text-[22px] font-medium" style="color: var(--color-ink)">{{ selected.titulo }}</h2>
      <p class="text-[12px] mt-2" style="color: var(--color-muted)">
        {{ formatDate(selected.publicado_em) }}
        <template v-if="selected.valido_ate"> · até {{ formatDate(selected.valido_ate) }}</template>
      </p>
      <p class="text-[15px] mt-4 whitespace-pre-wrap leading-relaxed" style="color: var(--color-ink)">
        {{ selected.corpo }}
      </p>
      <button
        v-if="selected.tem_anexo"
        type="button"
        class="btn btn-ghost text-sm mt-4"
        data-testid="recado-baixar-anexo"
        @click="downloadAnexo(selected)"
      >
        Baixar {{ selected.anexo_nome || 'anexo' }}
        <span v-if="selected.anexo_tamanho" style="color: var(--color-muted)">
          ({{ formatAnexoTamanho(selected.anexo_tamanho) }})
        </span>
      </button>
      <div v-if="canManage" class="flex flex-wrap gap-2 mt-4">
        <button type="button" class="btn btn-ghost text-sm" @click="openEdit(selected)">Editar</button>
        <button type="button" class="btn btn-ghost text-sm" style="color: var(--color-danger)" @click="remove(selected)">
          Remover
        </button>
        <button
          type="button"
          class="btn btn-ghost text-sm"
          data-testid="recado-engajamento-btn"
          @click="showEngajamento = !showEngajamento"
        >
          {{ showEngajamento ? 'Ocultar engajamento' : 'Ver engajamento' }}
        </button>
      </div>
      <EccEngajamentoPanel
        v-if="canManage && showEngajamento"
        alvo-tipo="recado"
        :alvo-id="selected.id"
      />
      <EccInteracoesBlock
        v-if="selected.status === 'publicado'"
        alvo-tipo="recado"
        :alvo-id="selected.id"
        :likes-count="selected.likes_count || 0"
        :liked-by-me="!!selected.liked_by_me"
        :comentarios-count="selected.comentarios_count || 0"
        @updated="(p) => onInteracaoUpdated(selected.id, p)"
      />
    </article>

    <div v-if="loading || detailLoading" class="text-[13.5px]" style="color: var(--color-muted)">Carregando…</div>
    <div
      v-else-if="!selected && !items.length"
      class="rounded-[12px] border border-dashed p-10 text-center"
      style="border-color: var(--color-line)"
      data-testid="recados-vazio"
    >
      <p class="font-serif text-[20px]" style="color: var(--color-ink)">Nenhum recado</p>
      <p class="text-[13.5px] mt-1" style="color: var(--color-muted)">
        Quando a secretaria publicar, aparece aqui.
      </p>
    </div>
    <ul v-else-if="!selected" class="flex flex-col gap-3" data-testid="recados-lista">
      <li
        v-for="item in items"
        :key="item.id"
        class="card p-0 overflow-hidden"
        data-testid="recado-item"
      >
        <button
          type="button"
          class="w-full text-left border-0 bg-transparent cursor-pointer p-4"
          @click="openDetail(item)"
        >
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <span
              v-if="!item.lido_por_mim && item.status === 'publicado'"
              class="w-2 h-2 rounded-full shrink-0"
              style="background: var(--color-accent-dark)"
              title="Não lido"
              data-testid="recado-nao-lido"
            />
            <span
              v-if="item.fixado"
              class="text-[11px] px-2 py-0.5 rounded-full font-medium"
              style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
            >Fixado</span>
            <span
              v-if="item.urgente"
              class="text-[11px] px-2 py-0.5 rounded-full font-medium"
              style="background: color-mix(in srgb, var(--color-danger) 14%, transparent); color: var(--color-danger)"
            >Urgente</span>
            <span
              v-if="canManage && item.status === 'rascunho'"
              class="text-[11px] px-2 py-0.5 rounded-full"
              style="background: var(--color-line); color: var(--color-muted)"
            >Rascunho</span>
            <span
              v-if="item.tem_anexo"
              class="text-[11px] px-2 py-0.5 rounded-full"
              style="background: var(--color-line); color: var(--color-muted)"
            >Anexo</span>
          </div>
          <h3 class="text-[17px] font-medium" style="color: var(--color-ink)">{{ item.titulo }}</h3>
          <p class="text-[14px] mt-2 line-clamp-2 leading-relaxed" style="color: var(--color-muted)">
            {{ item.resumo }}
          </p>
          <p class="text-[12px] mt-3" style="color: var(--color-muted)">
            {{ formatDate(item.publicado_em) }}
          </p>
        </button>
      </li>
    </ul>
  </div>
</template>
