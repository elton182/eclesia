<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'
import { labelStatusPergunta, labelTipoCampo } from '@/utils/eccPerguntas'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const pergunta = ref(null)
const envios = ref([])
const saving = ref(false)
const answering = ref(false)
const respostas = ref({})
const equipeId = ref('')
const respostaTexto = ref('')
const envioSelecionado = ref(null)

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
          v-if="pergunta.status === 'rascunho'"
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
      <section v-if="canReply" class="mb-6" data-testid="pergunta-envios">
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

      <!-- Preview campos (admin em rascunho) -->
      <section v-if="canManage && pergunta.status === 'rascunho'" class="mt-4">
        <h2 class="text-[14px] font-medium mb-2" style="color: var(--color-ink)">Campos do formulário</h2>
        <ul class="text-[13px] space-y-1" style="color: var(--color-muted)">
          <li v-for="c in pergunta.campos" :key="c.id">
            {{ c.ordem + 1 }}. {{ c.rotulo }} ({{ labelTipoCampo(c.tipo) }})
            <template v-if="c.obrigatorio"> · obrigatório</template>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
