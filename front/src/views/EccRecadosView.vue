<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'
import { markRecadosSeen } from '@/utils/liderRecados'
import EccInteracoesBlock from '@/components/ecc/EccInteracoesBlock.vue'
import EccComentariosModeracao from '@/components/ecc/EccComentariosModeracao.vue'

const emit = defineEmits(['recados-seen'])

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const items = ref([])
const equipes = ref([])
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)

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

async function onInteracaoUpdated(itemId, patch) {
  items.value = items.value.map((it) => {
    if (String(it.id) !== String(itemId)) return it
    return {
      ...it,
      ...(patch.likes_count !== undefined ? { likes_count: patch.likes_count } : {}),
      ...(patch.liked_by_me !== undefined ? { liked_by_me: patch.liked_by_me } : {}),
    }
  })
  try {
    const { data } = await api.get(`/ecc/recados/${itemId}`)
    const fresh = data?.data
    if (fresh) {
      items.value = items.value.map((it) => (String(it.id) === String(itemId) ? fresh : it))
    }
  } catch {
    /* mantém patch local */
  }
}

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
    if (!canManage.value) {
      markRecadosSeen(localStorage)
      emit('recados-seen')
    }
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

function openNew() {
  editingId.value = null
  form.value = blankForm()
  showForm.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    titulo: item.titulo,
    corpo: item.corpo,
    urgente: !!item.urgente,
    fixado: !!item.fixado,
    escopo: item.escopo || 'paroquia',
    equipe_id: item.equipe_id || '',
    etapa: item.etapa || 1,
    valido_ate: item.valido_ate ? item.valido_ate.slice(0, 16) : '',
    status: item.status,
  }
  showForm.value = true
}

async function save() {
  saving.value = true
  try {
    const body = {
      titulo: form.value.titulo,
      corpo: form.value.corpo,
      urgente: form.value.urgente,
      fixado: form.value.fixado,
      escopo: form.value.escopo,
      status: form.value.status,
      valido_ate: form.value.valido_ate
        ? new Date(form.value.valido_ate).toISOString()
        : null,
    }
    if (form.value.escopo === 'equipe') body.equipe_id = form.value.equipe_id || null
    if (form.value.escopo === 'etapa') body.etapa = Number(form.value.etapa)

    if (editingId.value) {
      await api.put(`/ecc/recados/${editingId.value}`, body)
      innovToast('success', 'Recados', 'Recado atualizado')
    } else {
      await api.post('/ecc/recados', body)
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
    await load()
  } catch (e) {
    innovToast('error', 'Recados', e.response?.data?.message || 'Falha ao remover')
  }
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

    <div v-if="loading" class="text-[13.5px]" style="color: var(--color-muted)">Carregando…</div>
    <div
      v-else-if="!items.length"
      class="rounded-[12px] border border-dashed p-10 text-center"
      style="border-color: var(--color-line)"
      data-testid="recados-vazio"
    >
      <p class="font-serif text-[20px]" style="color: var(--color-ink)">Nenhum recado</p>
      <p class="text-[13.5px] mt-1" style="color: var(--color-muted)">
        Quando a secretaria publicar, aparece aqui.
      </p>
    </div>
    <ul v-else class="flex flex-col gap-3" data-testid="recados-lista">
      <li
        v-for="item in items"
        :key="item.id"
        class="card p-4"
        data-testid="recado-item"
      >
        <div class="flex flex-wrap items-center gap-2 mb-2">
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
        </div>
        <h3 class="text-[17px] font-medium" style="color: var(--color-ink)">{{ item.titulo }}</h3>
        <p class="text-[14px] mt-2 whitespace-pre-wrap leading-relaxed" style="color: var(--color-ink)">
          {{ item.corpo }}
        </p>
        <p class="text-[12px] mt-3" style="color: var(--color-muted)">
          {{ formatDate(item.publicado_em) }}
          <template v-if="item.valido_ate"> · até {{ formatDate(item.valido_ate) }}</template>
        </p>
        <div v-if="canManage" class="flex gap-2 mt-3">
          <button type="button" class="btn btn-ghost text-sm" @click="openEdit(item)">Editar</button>
          <button type="button" class="btn btn-ghost text-sm" style="color: var(--color-danger)" @click="remove(item)">
            Remover
          </button>
        </div>
        <EccInteracoesBlock
          v-if="item.status === 'publicado'"
          alvo-tipo="recado"
          :alvo-id="item.id"
          :likes-count="item.likes_count || 0"
          :liked-by-me="!!item.liked_by_me"
          :comentarios-count="item.comentarios_count || 0"
          @updated="(p) => onInteracaoUpdated(item.id, p)"
        />
      </li>
    </ul>
  </div>
</template>
