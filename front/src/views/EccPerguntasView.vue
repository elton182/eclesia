<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import {
  campoVazio,
  labelStatusPergunta,
  labelTipoCampo,
} from '@/utils/eccPerguntas'
import { listenPerguntaEnvio, pusherConfigured } from '@/utils/pusherEcho'

const router = useRouter()
const auth = useAuthStore()
const tenantStore = useTenantStore()

const loading = ref(true)
const items = ref([])
const users = ref([])
const showForm = ref(false)
const saving = ref(false)
const form = ref(blankForm())
let stopListen = null

const canManage = computed(() => userHasPermission(auth.user, 'ecc.perguntas.manage'))

function blankForm() {
  return {
    titulo: '',
    descricao: '',
    responsavel_user_id: '',
    campos: [campoVazio('texto_curto')],
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ecc/perguntas')
    items.value = data?.data || []
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

async function loadUsers() {
  if (!canManage.value) return
  try {
    const { data } = await api.get('/users', { params: { per_page: 100 } })
    users.value = data?.data || data || []
  } catch {
    users.value = []
  }
}

function openNew() {
  form.value = blankForm()
  if (auth.user?.id) {
    form.value.responsavel_user_id = auth.user.id
  }
  showForm.value = true
}

function addCampo(tipo) {
  form.value.campos.push(campoVazio(tipo))
}

function removeCampo(idx) {
  if (form.value.campos.length <= 1) return
  form.value.campos.splice(idx, 1)
}

function addOpcao(campo) {
  if (!Array.isArray(campo.opcoes)) campo.opcoes = []
  campo.opcoes.push('')
}

async function save() {
  saving.value = true
  try {
    const payload = {
      titulo: form.value.titulo,
      descricao: form.value.descricao || null,
      responsavel_user_id: form.value.responsavel_user_id,
      campos: form.value.campos.map((c, i) => ({
        tipo: c.tipo,
        rotulo: c.rotulo,
        obrigatorio: c.obrigatorio,
        ordem: i,
        opcoes: c.tipo === 'lista' ? (c.opcoes || []).filter((o) => String(o).trim()) : undefined,
      })),
    }
    const { data } = await api.post('/ecc/perguntas', payload)
    innovToast('success', 'Perguntas', 'Formulário criado como rascunho.')
    showForm.value = false
    await load()
    router.push({ name: 'ecc-pergunta-detail', params: { id: data.data.id } })
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Não foi possível salvar')
  } finally {
    saving.value = false
  }
}

function openItem(item) {
  router.push({ name: 'ecc-pergunta-detail', params: { id: item.id } })
}

onMounted(async () => {
  await Promise.all([load(), loadUsers()])
  if (canManage.value && pusherConfigured() && auth.user?.id) {
    stopListen = await listenPerguntaEnvio(
      auth.user.id,
      (payload) => {
        innovToast(
          'info',
          'Nova resposta',
          `${payload.equipe_nome || 'Equipe'} respondeu: ${payload.pergunta_titulo || 'formulário'}`,
        )
        load()
      },
      {
        authHeaders: {
          Authorization: `Bearer ${localStorage.getItem('tenant_token') || ''}`,
          'X-Tenant': tenantStore.slug || '',
        },
      },
    )
  }
})

onUnmounted(() => {
  if (typeof stopListen === 'function') stopListen()
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 py-5 md:py-8 w-full max-w-2xl mx-auto" data-testid="ecc-perguntas-page">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-eyebrow">ECC</p>
        <h1 class="font-serif text-[28px] font-normal leading-tight mt-1" style="color: var(--color-ink)">
          Caixa de perguntas
        </h1>
        <p class="mt-2 text-[14px]" style="color: var(--color-muted)">
          Formulários da secretaria para as equipes responderem.
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn btn-primary"
        data-testid="perguntas-nova"
        @click="openNew"
      >
        Novo formulário
      </button>
    </header>

    <div v-if="showForm && canManage" class="card p-5 mb-6" data-testid="perguntas-form">
      <h2 class="font-serif text-[20px] mb-4" style="color: var(--color-ink)">Novo formulário</h2>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="perg-titulo">Título</label>
          <input id="perg-titulo" v-model="form.titulo" class="input" required maxlength="180" data-testid="perguntas-titulo">
        </div>
        <div>
          <label class="fld" for="perg-desc">Descrição (opcional)</label>
          <textarea id="perg-desc" v-model="form.descricao" class="input min-h-[72px]" rows="2" />
        </div>
        <div>
          <label class="fld" for="perg-resp">Usuário responsável</label>
          <select id="perg-resp" v-model="form.responsavel_user_id" class="input" required data-testid="perguntas-responsavel">
            <option disabled value="">Selecione…</option>
            <option v-for="u in users" :key="u.id" :value="u.id">
              {{ u.name }} <template v-if="u.username || u.email">({{ u.username || u.email }})</template>
            </option>
          </select>
        </div>

        <div class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-[14px] font-medium" style="color: var(--color-ink)">Campos</h3>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('texto_curto')">+ Curto</button>
              <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('texto_longo')">+ Longo</button>
              <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('lista')">+ Lista</button>
            </div>
          </div>
          <div
            v-for="(campo, idx) in form.campos"
            :key="idx"
            class="rounded-[10px] border p-3 space-y-2"
            style="border-color: var(--color-line)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-[12px]" style="color: var(--color-muted)">{{ labelTipoCampo(campo.tipo) }}</span>
              <button type="button" class="btn btn-ghost text-[12px]" :disabled="form.campos.length <= 1" @click="removeCampo(idx)">
                Remover
              </button>
            </div>
            <input v-model="campo.rotulo" class="input" :placeholder="`Rótulo do campo ${idx + 1}`" required>
            <label class="flex items-center gap-2 text-[13px]" style="color: var(--color-ink)">
              <input v-model="campo.obrigatorio" type="checkbox">
              Obrigatório
            </label>
            <div v-if="campo.tipo === 'lista'" class="space-y-2">
              <input
                v-for="(op, oi) in campo.opcoes"
                :key="oi"
                v-model="campo.opcoes[oi]"
                class="input"
                :placeholder="`Opção ${oi + 1}`"
              >
              <button type="button" class="btn btn-ghost text-[12px]" @click="addOpcao(campo)">+ Opção</button>
            </div>
          </div>
        </div>

        <div class="flex gap-2 justify-end">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Cancelar</button>
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="perguntas-salvar">
            {{ saving ? 'Salvando…' : 'Salvar rascunho' }}
          </button>
        </div>
      </form>
    </div>

    <div v-if="loading" class="text-[13.5px]" style="color: var(--color-muted)">Carregando…</div>

    <ul v-else class="flex flex-col gap-3" data-testid="perguntas-lista">
      <li
        v-if="!items.length"
        class="rounded-[12px] border border-dashed p-10 text-center text-[13.5px]"
        style="border-color: var(--color-line); color: var(--color-muted)"
      >
        Nenhum formulário {{ canManage ? 'ainda' : 'disponível' }}.
      </li>
      <li
        v-for="item in items"
        :key="item.id"
        class="card p-4 cursor-pointer"
        data-testid="perguntas-item"
        @click="openItem(item)"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-medium text-[16px]" style="color: var(--color-ink)">{{ item.titulo }}</div>
            <p v-if="item.descricao" class="text-[13px] mt-1" style="color: var(--color-muted)">
              {{ item.descricao }}
            </p>
            <p class="text-[12px] mt-2" style="color: var(--color-muted)">
              {{ item.campos_count }} campo(s)
              <template v-if="item.meu_envio"> · {{ labelStatusPergunta(item.meu_envio.status) }}</template>
              <template v-else-if="item.status === 'disponivel' && !canManage"> · Pendente</template>
            </p>
          </div>
          <span
            class="text-[11px] px-2 py-0.5 rounded-full shrink-0"
            style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
          >
            {{ labelStatusPergunta(item.status) }}
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>
