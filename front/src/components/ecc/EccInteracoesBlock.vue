<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import {
  applyLikeResult,
  comentariosPath,
  labelStatusComentario,
  likePath,
} from '@/utils/eccInteracoes'

const props = defineProps({
  alvoTipo: {
    type: String,
    required: true,
    validator: (v) => ['recado', 'reflexao'].includes(v),
  },
  alvoId: { type: String, required: true },
  likesCount: { type: Number, default: 0 },
  likedByMe: { type: Boolean, default: false },
  comentariosCount: { type: Number, default: 0 },
})

const emit = defineEmits(['updated'])

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const liked = ref(props.likedByMe)
const likes = ref(props.likesCount)
const comentarios = ref([])
const corpo = ref('')
const loadingComents = ref(false)
const saving = ref(false)
const liking = ref(false)
const open = ref(false)

const canModerar = computed(() =>
  userHasPermission(auth.user, 'ecc.interacoes.moderar', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }) ||
  userHasPermission(auth.user, 'ecc.recados.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }) ||
  userHasPermission(auth.user, 'ecc.reflexoes.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }),
)

watch(
  () => [props.likedByMe, props.likesCount],
  ([lb, lc]) => {
    liked.value = !!lb
    likes.value = Number(lc) || 0
  },
)

async function toggleLike() {
  liking.value = true
  try {
    const path = likePath(props.alvoTipo, props.alvoId)
    const { data } = liked.value
      ? await api.delete(path)
      : await api.post(path)
    const result = data?.data || data
    liked.value = !!result.liked
    likes.value = Number(result.likes_count) || 0
    emit('updated', applyLikeResult({}, result))
  } catch (e) {
    innovToast('error', 'Curtida', e.response?.data?.message || 'Não foi possível curtir')
  } finally {
    liking.value = false
  }
}

async function loadComentarios() {
  loadingComents.value = true
  try {
    const { data } = await api.get(comentariosPath(props.alvoTipo, props.alvoId))
    comentarios.value = data?.data || []
  } catch (e) {
    comentarios.value = []
    innovToast('error', 'Comentários', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loadingComents.value = false
  }
}

async function enviar() {
  if (!corpo.value.trim()) return
  saving.value = true
  try {
    const { data } = await api.post(comentariosPath(props.alvoTipo, props.alvoId), {
      corpo: corpo.value.trim(),
    })
    const created = data?.data
    corpo.value = ''
    if (created) {
      comentarios.value = [...comentarios.value, created]
      if (created.status === 'pendente') {
        innovToast('success', 'Comentário', 'Enviado para revisão da secretaria.')
      }
    } else {
      await loadComentarios()
    }
    emit('updated', {})
  } catch (e) {
    innovToast('error', 'Comentário', e.response?.data?.message || 'Não foi possível comentar')
  } finally {
    saving.value = false
  }
}

async function aprovar(c) {
  await api.post(`/ecc/interacoes/comentarios/${c.id}/aprovar`)
  await loadComentarios()
  emit('updated', {})
}

async function rejeitar(c) {
  await api.post(`/ecc/interacoes/comentarios/${c.id}/rejeitar`, { motivo: 'Não adequado' })
  await loadComentarios()
  emit('updated', {})
}

async function ocultar(c) {
  await api.post(`/ecc/interacoes/comentarios/${c.id}/ocultar`)
  await loadComentarios()
  emit('updated', {})
}

async function remover(c) {
  await api.delete(`/ecc/interacoes/comentarios/${c.id}`)
  comentarios.value = comentarios.value.filter((x) => x.id !== c.id)
  emit('updated', {})
}

function openComments() {
  open.value = !open.value
  if (open.value && !comentarios.value.length) loadComentarios()
}

onMounted(() => {
  liked.value = props.likedByMe
  likes.value = props.likesCount
})
</script>

<template>
  <div class="mt-3 pt-3" style="border-top: 1px solid var(--color-line)" data-testid="ecc-interacoes">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="btn btn-ghost text-sm inline-flex items-center gap-1.5"
        :disabled="liking"
        data-testid="ecc-like-btn"
        @click="toggleLike"
      >
        <span aria-hidden="true">{{ liked ? '♥' : '♡' }}</span>
        {{ likes }}
      </button>
      <button
        type="button"
        class="btn btn-ghost text-sm"
        data-testid="ecc-comentarios-toggle"
        @click="openComments"
      >
        Comentários ({{ comentariosCount }})
      </button>
    </div>

    <div v-if="open" class="mt-3 space-y-3" data-testid="ecc-comentarios-panel">
      <div v-if="loadingComents" class="text-[13px]" style="color: var(--color-muted)">Carregando…</div>
      <ul v-else class="space-y-2">
        <li
          v-for="c in comentarios"
          :key="c.id"
          class="rounded-[10px] px-3 py-2"
          style="background: var(--color-bg)"
          data-testid="ecc-comentario-item"
        >
          <div class="flex items-baseline justify-between gap-2">
            <span class="text-[13px] font-medium" style="color: var(--color-ink)">
              {{ c.autor_nome || 'Participante' }}
            </span>
            <span
              v-if="c.status !== 'aprovado'"
              class="text-[11px]"
              style="color: var(--color-muted)"
            >
              {{ labelStatusComentario(c.status) }}
            </span>
          </div>
          <p class="text-[13.5px] mt-1 whitespace-pre-wrap" style="color: var(--color-ink)">
            {{ c.corpo }}
          </p>
          <div v-if="canModerar || c.status === 'pendente'" class="flex flex-wrap gap-2 mt-2">
            <button
              v-if="canModerar && c.status === 'pendente'"
              type="button"
              class="btn btn-ghost text-sm"
              data-testid="ecc-comentario-aprovar"
              @click="aprovar(c)"
            >
              Aprovar
            </button>
            <button
              v-if="canModerar && c.status === 'pendente'"
              type="button"
              class="btn btn-ghost text-sm"
              @click="rejeitar(c)"
            >
              Rejeitar
            </button>
            <button
              v-if="canModerar && c.status === 'aprovado'"
              type="button"
              class="btn btn-ghost text-sm"
              @click="ocultar(c)"
            >
              Ocultar
            </button>
            <button
              type="button"
              class="btn btn-ghost text-sm"
              style="color: var(--color-danger)"
              data-testid="ecc-comentario-remover"
              @click="remover(c)"
            >
              Remover
            </button>
          </div>
        </li>
        <li
          v-if="!comentarios.length"
          class="text-[13px]"
          style="color: var(--color-muted)"
        >
          Nenhum comentário ainda.
        </li>
      </ul>

      <form class="flex flex-col gap-2" @submit.prevent="enviar">
        <label class="fld" :for="`comentario-${alvoId}`">Seu comentário</label>
        <textarea
          :id="`comentario-${alvoId}`"
          v-model="corpo"
          class="input min-h-[72px]"
          maxlength="2000"
          placeholder="Escreva com carinho…"
          data-testid="ecc-comentario-input"
        />
        <div class="flex justify-end">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="saving || !corpo.trim()"
            data-testid="ecc-comentario-enviar"
          >
            {{ saving ? 'Enviando…' : 'Comentar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
