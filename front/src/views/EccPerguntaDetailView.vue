<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'
import {
  campoVazio,
  labelStatusPergunta,
  labelTipoCampo,
} from '@/utils/eccPerguntas'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const pergunta = ref(null)
const envios = ref([])
const users = ref([])
const saving = ref(false)
const answering = ref(false)
const editing = ref(false)
const respostas = ref({})
const equipeId = ref('')
const respostaTexto = ref('')
const envioSelecionado = ref(null)
const editForm = ref(blankEditForm())

const canManage = computed(() => userHasPermission(auth.user, 'ecc.perguntas.manage'))
const isResponsavel = computed(
  () => pergunta.value && auth.user?.id && pergunta.value.responsavel_user_id === auth.user.id,
)
const canReply = computed(() => canManage.value || isResponsavel.value)
const equipesLideradas = computed(() => {
  const lista = auth.user?.equipes_lideradas
  return Array.isArray(lista) ? lista : []
})
const meuEnvio = computed(() => pergunta.value?.meu_envio || null)
const jaEnviou = computed(() => Boolean(meuEnvio.value))
const podeEnviar = computed(
  () => pergunta.value?.status === 'disponivel' && !jaEnviou.value && !canManage.value,
)
const podeEditar = computed(
  () => canManage.value && pergunta.value && pergunta.value.status !== 'arquivado',
)
const podeEditarCampos = computed(
  () => podeEditar.value && !pergunta.value?.tem_envios,
)

function blankEditForm() {
  return {
    titulo: '',
    descricao: '',
    mensagem_pos_envio: '',
    responsavel_user_id: '',
    campos: [campoVazio('texto_curto')],
  }
}

watch(
  () => pergunta.value?.campos,
  (campos) => {
    const next = {}
    for (const c of campos || []) {
      next[c.id] = respostas.value[c.id] ?? ''
    }
    respostas.value = next
  },
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/ecc/perguntas/${route.params.id}`)
    pergunta.value = data?.data || null
    if (equipesLideradas.value.length && !equipeId.value) {
      equipeId.value = String(equipesLideradas.value[0].id)
    }
    if (canReply.value) {
      await loadEnvios()
    }
    if (canManage.value) {
      await loadUsers()
    }
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Não encontrado')
    router.push({ name: 'ecc-perguntas' })
  } finally {
    loading.value = false
  }
}

async function loadEnvios() {
  try {
    const { data } = await api.get(`/ecc/perguntas/${route.params.id}/envios`)
    envios.value = data?.data || []
  } catch {
    envios.value = []
  }
}

async function loadUsers() {
  try {
    const { data } = await api.get('/users', { params: { per_page: 100 } })
    users.value = data?.data || data || []
  } catch {
    users.value = []
  }
}

function startEdit() {
  if (!pergunta.value) return
  editForm.value = {
    titulo: pergunta.value.titulo || '',
    descricao: pergunta.value.descricao || '',
    mensagem_pos_envio: pergunta.value.mensagem_pos_envio || '',
    responsavel_user_id: pergunta.value.responsavel_user_id || '',
    campos: (pergunta.value.campos || []).map((c) => ({
      tipo: c.tipo,
      rotulo: c.rotulo,
      obrigatorio: c.obrigatorio,
      opcoes: c.tipo === 'lista' ? [...(c.opcoes || [])] : [],
    })),
  }
  if (!editForm.value.campos.length) {
    editForm.value.campos = [campoVazio('texto_curto')]
  }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

function addCampo(tipo) {
  editForm.value.campos.push(campoVazio(tipo))
}

function removeCampo(idx) {
  if (editForm.value.campos.length <= 1) return
  editForm.value.campos.splice(idx, 1)
}

function addOpcao(campo) {
  if (!Array.isArray(campo.opcoes)) campo.opcoes = []
  campo.opcoes.push('')
}

async function saveEdit() {
  saving.value = true
  try {
    const payload = {
      titulo: editForm.value.titulo,
      descricao: editForm.value.descricao || null,
      mensagem_pos_envio: editForm.value.mensagem_pos_envio || null,
      responsavel_user_id: editForm.value.responsavel_user_id,
    }
    if (podeEditarCampos.value) {
      payload.campos = editForm.value.campos.map((c, i) => ({
        tipo: c.tipo,
        rotulo: c.rotulo,
        obrigatorio: c.obrigatorio,
        ordem: i,
        opcoes: c.tipo === 'lista' ? (c.opcoes || []).filter((o) => String(o).trim()) : undefined,
      }))
    }
    const { data } = await api.put(`/ecc/perguntas/${route.params.id}`, payload)
    pergunta.value = data?.data
    editing.value = false
    innovToast('success', 'Perguntas', 'Formulário atualizado.')
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Não foi possível salvar')
  } finally {
    saving.value = false
  }
}

async function disponibilizar() {
  const ok = await innovConfirm({
    title: 'Disponibilizar',
    message: 'Os líderes verão este formulário na Minha equipe. Continuar?',
    confirmText: 'Disponibilizar',
  })
  if (!ok) return
  try {
    const { data } = await api.post(`/ecc/perguntas/${route.params.id}/disponibilizar`)
    pergunta.value = data?.data
    innovToast('success', 'Perguntas', 'Formulário disponível para as equipes.')
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Falha ao disponibilizar')
  }
}

async function arquivar() {
  const ok = await innovConfirm({
    title: 'Arquivar',
    message: 'O formulário sai do feed dos líderes. Envios já feitos continuam legíveis.',
    confirmText: 'Arquivar',
  })
  if (!ok) return
  try {
    const { data } = await api.post(`/ecc/perguntas/${route.params.id}/arquivar`)
    pergunta.value = data?.data
    editing.value = false
    innovToast('success', 'Perguntas', 'Arquivado.')
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Falha ao arquivar')
  }
}

async function enviar() {
  if (!equipeId.value) {
    innovToast('error', 'Perguntas', 'Selecione a equipe.')
    return
  }
  saving.value = true
  try {
    const { data } = await api.post(`/ecc/perguntas/${route.params.id}/envios`, {
      equipe_id: equipeId.value,
      respostas: respostas.value,
    })
    if (pergunta.value) {
      pergunta.value = { ...pergunta.value, meu_envio: data.data }
    }
    innovToast('success', 'Perguntas', 'Respostas enviadas.')
  } catch (e) {
    const errors = e.response?.data?.errors
    const first = errors && Object.values(errors)[0]?.[0]
    innovToast('error', 'Perguntas', first || e.response?.data?.message || 'Não foi possível enviar')
  } finally {
    saving.value = false
  }
}

function selecionarEnvio(envio) {
  envioSelecionado.value = envio
  respostaTexto.value = envio.resposta_responsavel || ''
}

async function responder() {
  if (!envioSelecionado.value) return
  answering.value = true
  try {
    const { data } = await api.post(
      `/ecc/perguntas/envios/${envioSelecionado.value.id}/responder`,
      { resposta: respostaTexto.value },
    )
    const idx = envios.value.findIndex((e) => e.id === data.data.id)
    if (idx >= 0) envios.value[idx] = data.data
    envioSelecionado.value = data.data
    innovToast('success', 'Perguntas', 'Resposta registrada.')
  } catch (e) {
    innovToast('error', 'Perguntas', e.response?.data?.message || 'Falha ao responder')
  } finally {
    answering.value = false
  }
}

function rotuloCampo(campoId) {
  return pergunta.value?.campos?.find((c) => c.id === campoId)?.rotulo || campoId
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 py-5 md:py-8 w-full max-w-2xl mx-auto" data-testid="ecc-pergunta-detail">
    <button type="button" class="btn btn-ghost text-[13px] mb-4" @click="router.push({ name: 'ecc-perguntas' })">
      ← Voltar
    </button>

    <div v-if="loading" class="text-[13.5px]" style="color: var(--color-muted)">Carregando…</div>

    <template v-else-if="pergunta">
      <header class="mb-6">
        <p class="page-eyebrow">ECC · Perguntas</p>
        <h1 class="font-serif text-[28px] font-normal leading-tight mt-1" style="color: var(--color-ink)">
          {{ pergunta.titulo }}
        </h1>
        <p v-if="pergunta.descricao" class="mt-2 text-[14px]" style="color: var(--color-muted)">
          {{ pergunta.descricao }}
        </p>
        <p class="mt-2 text-[12px]" style="color: var(--color-muted)">
          {{ labelStatusPergunta(pergunta.status) }}
          · Responsável: {{ pergunta.responsavel_nome || '—' }}
        </p>
      </header>

      <div v-if="canManage" class="flex flex-wrap gap-2 mb-6">
        <button
          v-if="podeEditar && !editing"
          type="button"
          class="btn btn-primary"
          data-testid="pergunta-editar"
          @click="startEdit"
        >
          Editar formulário
        </button>
        <button
          v-if="pergunta.status === 'rascunho' && !editing"
          type="button"
          class="btn btn-primary"
          data-testid="pergunta-disponibilizar"
          @click="disponibilizar"
        >
          Disponibilizar
        </button>
        <button
          v-if="pergunta.status !== 'arquivado'"
          type="button"
          class="btn btn-ghost"
          data-testid="pergunta-arquivar"
          @click="arquivar"
        >
          Arquivar
        </button>
      </div>

      <!-- Admin: editar -->
      <section v-if="editing && podeEditar" class="card p-5 mb-6" data-testid="pergunta-edit-form">
        <h2 class="font-serif text-[20px] mb-4" style="color: var(--color-ink)">Editar formulário</h2>
        <form class="space-y-4" @submit.prevent="saveEdit">
          <div>
            <label class="fld" for="edit-titulo">Título</label>
            <input id="edit-titulo" v-model="editForm.titulo" class="input" required maxlength="180" data-testid="pergunta-edit-titulo">
          </div>
          <div>
            <label class="fld" for="edit-desc">Descrição (opcional)</label>
            <textarea id="edit-desc" v-model="editForm.descricao" class="input min-h-[72px]" rows="2" />
          </div>
          <div>
            <label class="fld" for="edit-msg">Mensagem após resposta do líder (opcional)</label>
            <textarea
              id="edit-msg"
              v-model="editForm.mensagem_pos_envio"
              class="input min-h-[72px]"
              rows="2"
              maxlength="2000"
              placeholder="Ex.: Obrigado! A secretaria vai analisar e retornar."
              data-testid="pergunta-edit-mensagem-pos"
            />
            <p class="text-[12px] mt-1" style="color: var(--color-muted)">
              Aparece para o líder depois que ele envia as respostas.
            </p>
          </div>
          <div>
            <label class="fld" for="edit-resp">Usuário responsável</label>
            <select id="edit-resp" v-model="editForm.responsavel_user_id" class="input" required data-testid="pergunta-edit-responsavel">
              <option disabled value="">Selecione…</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }} <template v-if="u.username || u.email">({{ u.username || u.email }})</template>
              </option>
            </select>
          </div>

          <div v-if="podeEditarCampos" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-[14px] font-medium" style="color: var(--color-ink)">Campos</h3>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('texto_curto')">+ Curto</button>
                <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('texto_longo')">+ Longo</button>
                <button type="button" class="btn btn-ghost text-[12px]" @click="addCampo('lista')">+ Lista</button>
              </div>
            </div>
            <div
              v-for="(campo, idx) in editForm.campos"
              :key="idx"
              class="rounded-[10px] border p-3 space-y-2"
              style="border-color: var(--color-line)"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-[12px]" style="color: var(--color-muted)">{{ labelTipoCampo(campo.tipo) }}</span>
                <button type="button" class="btn btn-ghost text-[12px]" :disabled="editForm.campos.length <= 1" @click="removeCampo(idx)">
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
          <p v-else class="text-[13px]" style="color: var(--color-muted)">
            Já existem envios — título, mensagem e responsável podem mudar; os campos ficam travados.
          </p>

          <div class="flex gap-2 justify-end">
            <button type="button" class="btn btn-ghost" @click="cancelEdit">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="pergunta-edit-salvar">
              {{ saving ? 'Salvando…' : 'Salvar alterações' }}
            </button>
          </div>
        </form>
      </section>

      <!-- Líder: formulário de envio -->
      <section v-if="podeEnviar" class="card p-5 mb-6" data-testid="pergunta-envio-form">
        <h2 class="font-serif text-[20px] mb-4" style="color: var(--color-ink)">Responder</h2>
        <div v-if="equipesLideradas.length > 1" class="mb-4">
          <label class="fld" for="envio-equipe">Equipe</label>
          <select id="envio-equipe" v-model="equipeId" class="input">
            <option v-for="eq in equipesLideradas" :key="eq.id" :value="eq.id">{{ eq.nome || eq.id }}</option>
          </select>
        </div>
        <form class="space-y-4" @submit.prevent="enviar">
          <div v-for="campo in pergunta.campos" :key="campo.id">
            <label class="fld" :for="`campo-${campo.id}`">
              {{ campo.rotulo }}
              <span v-if="campo.obrigatorio" style="color: var(--color-accent-dark)">*</span>
              <span class="text-[11px] font-normal" style="color: var(--color-muted)">
                · {{ labelTipoCampo(campo.tipo) }}
              </span>
            </label>
            <input
              v-if="campo.tipo === 'texto_curto'"
              :id="`campo-${campo.id}`"
              v-model="respostas[campo.id]"
              class="input"
              maxlength="255"
              :required="campo.obrigatorio"
            >
            <textarea
              v-else-if="campo.tipo === 'texto_longo'"
              :id="`campo-${campo.id}`"
              v-model="respostas[campo.id]"
              class="input min-h-[96px]"
              rows="3"
              :required="campo.obrigatorio"
            />
            <select
              v-else
              :id="`campo-${campo.id}`"
              v-model="respostas[campo.id]"
              class="input"
              :required="campo.obrigatorio"
            >
              <option value="">Selecione…</option>
              <option v-for="op in campo.opcoes || []" :key="op" :value="op">{{ op }}</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-full" :disabled="saving" data-testid="pergunta-enviar">
            {{ saving ? 'Enviando…' : 'Enviar respostas' }}
          </button>
        </form>
      </section>

      <!-- Líder: já enviou -->
      <section v-if="meuEnvio" class="card p-5 mb-6" data-testid="pergunta-meu-envio">
        <h2 class="font-serif text-[20px] mb-3" style="color: var(--color-ink)">Seu envio</h2>
        <p class="text-[12px] mb-3" style="color: var(--color-muted)">
          {{ labelStatusPergunta(meuEnvio.status) }}
          · {{ meuEnvio.equipe_nome }}
        </p>
        <div
          v-if="pergunta.mensagem_pos_envio"
          class="mb-4 rounded-[10px] px-3 py-3 text-[14px] whitespace-pre-wrap"
          style="background: var(--color-accent-soft); color: var(--color-ink)"
          data-testid="pergunta-mensagem-pos-envio"
        >
          {{ pergunta.mensagem_pos_envio }}
        </div>
        <dl class="space-y-2 text-[14px]">
          <div v-for="(val, key) in meuEnvio.respostas" :key="key">
            <dt class="text-[12px]" style="color: var(--color-muted)">{{ rotuloCampo(key) }}</dt>
            <dd style="color: var(--color-ink)">{{ val }}</dd>
          </div>
        </dl>
        <div
          v-if="meuEnvio.resposta_responsavel"
          class="mt-4 pt-4 border-t"
          style="border-color: var(--color-line)"
          data-testid="pergunta-resposta-responsavel"
        >
          <p class="text-[12px] mb-1" style="color: var(--color-muted)">Resposta do responsável</p>
          <p class="text-[14px] whitespace-pre-wrap" style="color: var(--color-ink)">
            {{ meuEnvio.resposta_responsavel }}
          </p>
        </div>
        <p v-else class="mt-4 text-[13px]" style="color: var(--color-muted)">
          Aguardando resposta do responsável.
        </p>
      </section>

      <!-- Admin/responsável: inbox -->
      <section v-if="canReply && !editing" class="mb-6" data-testid="pergunta-envios">
        <h2 class="font-serif text-[20px] mb-3" style="color: var(--color-ink)">Envios das equipes</h2>
        <ul class="flex flex-col gap-3">
          <li
            v-if="!envios.length"
            class="rounded-[12px] border border-dashed p-8 text-center text-[13.5px]"
            style="border-color: var(--color-line); color: var(--color-muted)"
          >
            Nenhum envio ainda.
          </li>
          <li
            v-for="envio in envios"
            :key="envio.id"
            class="card p-4 cursor-pointer"
            :data-testid="`pergunta-envio-${envio.id}`"
            @click="selecionarEnvio(envio)"
          >
            <div class="flex justify-between gap-2">
              <div class="font-medium" style="color: var(--color-ink)">{{ envio.equipe_nome }}</div>
              <span class="text-[11px]" style="color: var(--color-muted)">{{ labelStatusPergunta(envio.status) }}</span>
            </div>
            <p class="text-[12px] mt-1" style="color: var(--color-muted)">
              por {{ envio.enviado_por_nome || '—' }}
            </p>
          </li>
        </ul>

        <div v-if="envioSelecionado" class="card p-5 mt-4" data-testid="pergunta-responder-panel">
          <h3 class="font-medium mb-3" style="color: var(--color-ink)">
            {{ envioSelecionado.equipe_nome }}
          </h3>
          <dl class="space-y-2 text-[14px] mb-4">
            <div v-for="(val, key) in envioSelecionado.respostas" :key="key">
              <dt class="text-[12px]" style="color: var(--color-muted)">{{ rotuloCampo(key) }}</dt>
              <dd style="color: var(--color-ink)">{{ val }}</dd>
            </div>
          </dl>
          <label class="fld" for="resp-admin">Sua resposta à equipe</label>
          <textarea
            id="resp-admin"
            v-model="respostaTexto"
            class="input min-h-[96px]"
            rows="3"
            data-testid="pergunta-resposta-texto"
          />
          <button
            type="button"
            class="btn btn-primary mt-3"
            :disabled="answering || !respostaTexto.trim()"
            data-testid="pergunta-responder-btn"
            @click="responder"
          >
            {{ answering ? 'Salvando…' : 'Enviar resposta' }}
          </button>
        </div>
      </section>

      <section v-if="canManage && !editing && pergunta.campos?.length" class="mt-4">
        <h2 class="text-[14px] font-medium mb-2" style="color: var(--color-ink)">Campos do formulário</h2>
        <ul class="text-[13px] space-y-1" style="color: var(--color-muted)">
          <li v-for="c in pergunta.campos" :key="c.id">
            {{ c.ordem + 1 }}. {{ c.rotulo }} ({{ labelTipoCampo(c.tipo) }})
            <template v-if="c.obrigatorio"> · obrigatório</template>
          </li>
        </ul>
        <p v-if="pergunta.mensagem_pos_envio" class="text-[13px] mt-3" style="color: var(--color-muted)">
          Mensagem pós-envio: {{ pergunta.mensagem_pos_envio }}
        </p>
      </section>
    </template>
  </div>
</template>
