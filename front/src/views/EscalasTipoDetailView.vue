<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
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

const tipoId = computed(() => String(route.params.id))
const tipo = ref(null)
const ocorrencias = ref([])
const loading = ref(false)
const equipeForm = ref({ nome: '', cor: '#6B1C2B', ordem: 0, vagas_sugeridas: null })
const ocorrForm = ref({ titulo: '', inicia_em: '', local: '' })
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const [t, o] = await Promise.all([
      api.get(`/escalas/tipos/${tipoId.value}`),
      api.get(`/escalas/tipos/${tipoId.value}/ocorrencias`),
    ])
    tipo.value = t.data?.data || t.data
    ocorrencias.value = o.data?.data || o.data || []
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao carregar tipo.'))
  } finally {
    loading.value = false
  }
}

async function addEquipe() {
  if (!equipeForm.value.nome.trim()) return
  saving.value = true
  try {
    await api.post(`/escalas/tipos/${tipoId.value}/equipes`, {
      nome: equipeForm.value.nome,
      cor: equipeForm.value.cor || null,
      ordem: Number(equipeForm.value.ordem) || 0,
      vagas_sugeridas: equipeForm.value.vagas_sugeridas
        ? Number(equipeForm.value.vagas_sugeridas)
        : null,
    })
    equipeForm.value = { nome: '', cor: '#6B1C2B', ordem: 0, vagas_sugeridas: null }
    innovToast('success', 'OK', 'Equipe adicionada.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao criar equipe.'))
  } finally {
    saving.value = false
  }
}

async function addOcorrencia() {
  if (!ocorrForm.value.inicia_em) {
    innovToast('error', 'Validação', 'Informe data/hora.')
    return
  }
  saving.value = true
  try {
    const { data } = await api.post(`/escalas/tipos/${tipoId.value}/ocorrencias`, {
      titulo: ocorrForm.value.titulo || null,
      inicia_em: new Date(ocorrForm.value.inicia_em).toISOString(),
      local: ocorrForm.value.local || null,
    })
    const created = data?.data || data
    ocorrForm.value = { titulo: '', inicia_em: '', local: '' }
    innovToast('success', 'OK', 'Ocorrência criada.')
    await load()
    if (created?.id) router.push(`/escalas/ocorrencias/${created.id}`)
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao criar ocorrência.'))
  } finally {
    saving.value = false
  }
}

async function deleteTipo() {
  if (!tipo.value || !canManage.value) return
  if (
    !confirm(
      `Excluir o tipo "${tipo.value.nome}"? Equipes, ocorrências e atribuições serão removidas.`,
    )
  ) {
    return
  }
  try {
    await api.delete(`/escalas/tipos/${tipo.value.id}`)
    innovToast('success', 'OK', 'Tipo de escala excluído.')
    router.push('/escalas')
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao excluir tipo.'))
  }
}

function formatWhen(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      dateStyle: 'short',
      timeStyle: 'short',
    })
  } catch {
    return iso
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="escalas-tipo-detail">
    <button
      type="button"
      class="text-[13px] mb-4 bg-transparent border-0 cursor-pointer p-0"
      style="color: #8A2436"
      @click="router.push('/escalas')"
    >
      ← Tipos de escala
    </button>

    <p v-if="loading" class="text-[14px]" style="color: rgba(42, 20, 24, 0.62)">Carregando…</p>
    <template v-else-if="tipo">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-1">
        <h1 class="font-serif text-[28px] font-normal m-0" style="color: #2A1418">
          {{ tipo.nome }}
        </h1>
        <button
          v-if="canManage"
          type="button"
          class="px-3 py-1.5 rounded-lg text-[12.5px] cursor-pointer"
          style="background: transparent; border: 1px solid rgba(138, 36, 54, 0.35); color: #8A2436"
          data-testid="escalas-excluir-tipo-detail"
          @click="deleteTipo"
        >
          Excluir tipo
        </button>
      </div>
      <p class="text-[14px] mb-6" style="color: rgba(42, 20, 24, 0.62)">
        {{ tipo.descricao || 'Sem descrição.' }}
      </p>

      <section class="mb-8">
        <h2 class="font-serif text-[20px] mb-3" style="color: #2A1418">Equipes / funções</h2>
        <ul class="list-none p-0 m-0 flex flex-col gap-2 mb-4">
          <li
            v-for="e in tipo.equipes || []"
            :key="e.id"
            class="flex items-center gap-2 px-3 py-2 rounded-lg"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
          >
            <span
              class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="{ background: e.cor || '#6B1C2B' }"
            />
            <span class="text-[14px]" style="color: #2A1418">{{ e.nome }}</span>
            <span
              v-if="e.vagas_sugeridas"
              class="text-[12px] ml-auto"
              style="color: rgba(42, 20, 24, 0.55)"
            >
              {{ e.vagas_sugeridas }} vagas
            </span>
          </li>
        </ul>
        <form
          v-if="canManage"
          class="flex flex-wrap gap-2 items-end"
          data-testid="escalas-equipe-form"
          @submit.prevent="addEquipe"
        >
          <label class="text-[12px]" style="color: #2A1418">
            Nome
            <input
              v-model="equipeForm.nome"
              class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
              style="border: 1px solid rgba(42, 20, 24, 0.14)"
              required
            />
          </label>
          <button
            type="submit"
            class="px-3 py-2 rounded-lg text-[13px] font-medium border-0 cursor-pointer"
            style="background: #6B1C2B; color: #FFFDFA"
            :disabled="saving"
          >
            Adicionar equipe
          </button>
        </form>
      </section>

      <section>
        <h2 class="font-serif text-[20px] mb-3" style="color: #2A1418">Ocorrências</h2>
        <ul class="list-none p-0 m-0 flex flex-col gap-2 mb-4" data-testid="escalas-ocorrencias-list">
          <li
            v-for="o in ocorrencias"
            :key="o.id"
            class="px-3 py-3 rounded-lg cursor-pointer"
            style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
            @click="router.push(`/escalas/ocorrencias/${o.id}`)"
          >
            <div class="text-[14px] font-medium" style="color: #2A1418">
              {{ o.titulo || tipo.nome }}
            </div>
            <div class="text-[12.5px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">
              {{ formatWhen(o.inicia_em) }}
              <span v-if="o.local"> · {{ o.local }}</span>
            </div>
          </li>
          <li
            v-if="!ocorrencias.length"
            class="text-[13px]"
            style="color: rgba(42, 20, 24, 0.62)"
          >
            Nenhuma ocorrência.
          </li>
        </ul>
        <form
          v-if="canManage"
          class="flex flex-wrap gap-2 items-end"
          data-testid="escalas-ocorrencia-form"
          @submit.prevent="addOcorrencia"
        >
          <label class="text-[12px]" style="color: #2A1418">
            Título
            <input
              v-model="ocorrForm.titulo"
              class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
              style="border: 1px solid rgba(42, 20, 24, 0.14)"
            />
          </label>
          <label class="text-[12px]" style="color: #2A1418">
            Início
            <input
              v-model="ocorrForm.inicia_em"
              type="datetime-local"
              class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
              style="border: 1px solid rgba(42, 20, 24, 0.14)"
              required
            />
          </label>
          <label class="text-[12px]" style="color: #2A1418">
            Local
            <input
              v-model="ocorrForm.local"
              class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
              style="border: 1px solid rgba(42, 20, 24, 0.14)"
            />
          </label>
          <button
            type="submit"
            class="px-3 py-2 rounded-lg text-[13px] font-medium border-0 cursor-pointer"
            style="background: #6B1C2B; color: #FFFDFA"
            :disabled="saving"
          >
            Criar ocorrência
          </button>
        </form>
      </section>
    </template>
  </div>
</template>
