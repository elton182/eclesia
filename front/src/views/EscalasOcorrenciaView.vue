<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import { equipesJaEscalado } from '@/utils/escalas'
import { extractApiError } from '@/utils/tenantAuth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const canManage = computed(() =>
  userHasPermission(auth.user, 'escalas.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }),
)

const ocorrenciaId = computed(() => String(route.params.id))
const ocorrencia = ref(null)
const tipo = ref(null)
const loading = ref(false)
const pickerEquipeId = ref(null)
const q = ref('')
const candidatos = ref({ pessoas: [], casais: [] })
const searching = ref(false)

const equipes = computed(() => tipo.value?.equipes || [])

const atribuicoesPorEquipe = computed(() => {
  const map = {}
  for (const e of equipes.value) map[e.id] = []
  for (const a of ocorrencia.value?.atribuicoes || []) {
    if (!map[a.escala_equipe_id]) map[a.escala_equipe_id] = []
    map[a.escala_equipe_id].push(a)
  }
  return map
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/escalas/ocorrencias/${ocorrenciaId.value}`)
    ocorrencia.value = data?.data || data
    const t = await api.get(`/escalas/tipos/${ocorrencia.value.escala_tipo_id}`)
    tipo.value = t.data?.data || t.data
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao carregar ocorrência.'))
  } finally {
    loading.value = false
  }
}

async function searchCandidatos() {
  searching.value = true
  try {
    const { data } = await api.get('/escalas/candidatos', { params: { q: q.value || undefined } })
    candidatos.value = data?.data || { pessoas: [], casais: [] }
  } catch {
    candidatos.value = { pessoas: [], casais: [] }
  } finally {
    searching.value = false
  }
}

watch(pickerEquipeId, (id) => {
  if (id) searchCandidatos()
})

function jaEm(who) {
  return equipesJaEscalado(ocorrencia.value?.atribuicoes || [], who)
}

async function escalar(payload) {
  try {
    await api.post(`/escalas/ocorrencias/${ocorrenciaId.value}/atribuicoes`, {
      escala_equipe_id: pickerEquipeId.value,
      ...payload,
    })
    innovToast('success', 'OK', 'Escalado.')
    pickerEquipeId.value = null
    await load()
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Não foi possível escalar.'))
  }
}

async function remover(atrId) {
  try {
    await api.delete(`/escalas/atribuicoes/${atrId}`)
    await load()
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao remover.'))
  }
}

function formatWhen(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="escalas-montagem">
    <button
      type="button"
      class="text-[13px] mb-4 bg-transparent border-0 cursor-pointer p-0"
      style="color: #8A2436"
      @click="ocorrencia && router.push(`/escalas/tipos/${ocorrencia.escala_tipo_id}`)"
    >
      ← Voltar ao tipo
    </button>

    <p v-if="loading" class="text-[14px]" style="color: rgba(42, 20, 24, 0.62)">Carregando…</p>
    <template v-else-if="ocorrencia">
      <h1 class="font-serif text-[26px] font-normal" style="color: #2A1418">
        {{ ocorrencia.titulo || ocorrencia.tipo_nome || 'Montagem da escala' }}
      </h1>
      <p class="text-[13px] mb-6" style="color: rgba(42, 20, 24, 0.62)">
        {{ formatWhen(ocorrencia.inicia_em) }}
        <span v-if="ocorrencia.local"> · {{ ocorrencia.local }}</span>
      </p>

      <div class="grid gap-4 md:grid-cols-2" data-testid="escalas-equipes-grid">
        <div
          v-for="e in equipes"
          :key="e.id"
          class="rounded-xl p-4"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        >
          <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
              <span
                class="w-2.5 h-2.5 rounded-full"
                :style="{ background: e.cor || '#6B1C2B' }"
              />
              <h3 class="font-serif text-[17px] m-0" style="color: #2A1418">{{ e.nome }}</h3>
            </div>
            <span class="text-[12px]" style="color: rgba(42, 20, 24, 0.55)">
              {{ (atribuicoesPorEquipe[e.id] || []).length
              }}{{ e.vagas_sugeridas ? ` / ${e.vagas_sugeridas}` : '' }}
            </span>
          </div>
          <ul class="list-none p-0 m-0 flex flex-col gap-1.5 mb-3">
            <li
              v-for="a in atribuicoesPorEquipe[e.id] || []"
              :key="a.id"
              class="flex items-center justify-between gap-2 text-[13px] px-2 py-1.5 rounded-md"
              style="background: #F7F4EF"
            >
              <span style="color: #2A1418">
                {{ a.casal_rotulo || a.pessoa_nome || '—' }}
              </span>
              <button
                v-if="canManage"
                type="button"
                class="text-[12px] bg-transparent border-0 cursor-pointer"
                style="color: #8A2436"
                @click="remover(a.id)"
              >
                Remover
              </button>
            </li>
            <li
              v-if="!(atribuicoesPorEquipe[e.id] || []).length"
              class="text-[12.5px]"
              style="color: rgba(42, 20, 24, 0.5)"
            >
              Ninguém escalado
            </li>
          </ul>
          <button
            v-if="canManage"
            type="button"
            class="text-[13px] px-3 py-2 rounded-lg border-0 cursor-pointer"
            style="background: #6B1C2B; color: #FFFDFA"
            @click="pickerEquipeId = e.id"
          >
            + Escalar
          </button>
        </div>
      </div>
    </template>

    <div
      v-if="pickerEquipeId"
      class="fixed inset-0 z-50 flex items-end md:items-center justify-center p-4"
      style="background: rgba(42, 20, 24, 0.35)"
      data-testid="escalas-picker"
      @click.self="pickerEquipeId = null"
    >
      <div
        class="w-full max-w-md rounded-xl p-4 max-h-[80vh] overflow-auto"
        style="background: #FFFDFA"
      >
        <div class="flex justify-between items-center mb-3">
          <h3 class="font-serif text-[18px] m-0" style="color: #2A1418">Escalar</h3>
          <button
            type="button"
            class="bg-transparent border-0 cursor-pointer text-[18px]"
            style="color: #2A1418"
            @click="pickerEquipeId = null"
          >
            ×
          </button>
        </div>
        <input
          v-model="q"
          type="search"
          placeholder="Buscar pessoa ou casal"
          class="w-full px-3 py-2 rounded-lg text-[14px] mb-3"
          style="border: 1px solid rgba(42, 20, 24, 0.14)"
          @input="searchCandidatos"
        />
        <p v-if="searching" class="text-[13px]" style="color: rgba(42, 20, 24, 0.55)">Buscando…</p>
        <div class="mb-3">
          <div class="text-[11px] uppercase tracking-wide mb-1" style="color: #B4703F">
            Pessoas
          </div>
          <button
            v-for="p in candidatos.pessoas"
            :key="p.id"
            type="button"
            class="w-full text-left px-2 py-2 rounded-md text-[13px] border-0 cursor-pointer mb-1"
            style="background: #F7F4EF; color: #2A1418"
            @click="escalar({ pessoa_id: p.id })"
          >
            {{ p.nome }}
            <span
              v-if="jaEm({ pessoaId: p.id }).length"
              class="block text-[11px]"
              style="color: #B4703F"
            >
              Já em {{ jaEm({ pessoaId: p.id }).join(', ') }}
            </span>
          </button>
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wide mb-1" style="color: #B4703F">
            Casais
          </div>
          <button
            v-for="c in candidatos.casais"
            :key="c.id"
            type="button"
            class="w-full text-left px-2 py-2 rounded-md text-[13px] border-0 cursor-pointer mb-1"
            style="background: #F7F4EF; color: #2A1418"
            @click="escalar({ casal_id: c.id })"
          >
            {{ c.rotulo }}
            <span
              v-if="jaEm({ casalId: c.id }).length"
              class="block text-[11px]"
              style="color: #B4703F"
            >
              Já em {{ jaEm({ casalId: c.id }).join(', ') }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
