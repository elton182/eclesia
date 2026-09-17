<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { userHasPermission } from '@/utils/userRoles'
import { extractApiError } from '@/utils/tenantAuth'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const canManage = computed(() =>
  userHasPermission(auth.user, 'escalas.manage', {
    isSuperAdmin: authAdmin.isAuthenticated && !auth.isAuthenticated,
  }),
)

const tipos = ref([])
const loading = ref(false)
const showForm = ref(false)
const saving = ref(false)
const form = ref({
  nome: '',
  descricao: '',
  unidade_preferida: 'ambos',
  recorrencia: 'avulsa',
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/escalas/tipos')
    tipos.value = data?.data || data || []
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Não foi possível carregar os tipos.'))
  } finally {
    loading.value = false
  }
}

async function createTipo() {
  if (!form.value.nome.trim()) {
    innovToast('error', 'Validação', 'Informe o nome.')
    return
  }
  saving.value = true
  try {
    await api.post('/escalas/tipos', form.value)
    innovToast('success', 'OK', 'Tipo de escala criado.')
    showForm.value = false
    form.value = { nome: '', descricao: '', unidade_preferida: 'ambos', recorrencia: 'avulsa' }
    await load()
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao criar tipo.'))
  } finally {
    saving.value = false
  }
}

async function deleteTipo(t, event) {
  event?.stopPropagation?.()
  if (!canManage.value) return
  if (!confirm(`Excluir o tipo "${t.nome}"? Equipes, ocorrências e atribuições serão removidas.`)) {
    return
  }
  try {
    await api.delete(`/escalas/tipos/${t.id}`)
    innovToast('success', 'OK', 'Tipo de escala excluído.')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', extractApiError(e, 'Erro ao excluir tipo.'))
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="escalas-tipos-page">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
      <div>
        <p class="text-[11px] font-medium tracking-wider uppercase mb-1" style="color: #B4703F">
          Núcleo
        </p>
        <h1 class="font-serif text-[28px] font-normal" style="color: #2A1418">Escalas</h1>
        <p class="text-[14px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
          Tipos de escala da igreja — liturgia, coroinhas, equipes de apoio e mais.
        </p>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-2 rounded-lg text-[13px] cursor-pointer"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.14); color: #2A1418"
          data-testid="escalas-goto-agenda"
          @click="router.push('/escalas/agenda')"
        >
          Agenda
        </button>
        <button
          v-if="canManage"
          type="button"
          class="px-3 py-2 rounded-lg text-[13px] font-medium cursor-pointer border-0"
          style="background: #6B1C2B; color: #FFFDFA"
          data-testid="escalas-novo-tipo"
          @click="showForm = !showForm"
        >
          {{ showForm ? 'Cancelar' : 'Novo tipo' }}
        </button>
      </div>
    </div>

    <form
      v-if="showForm"
      class="rounded-xl p-4 mb-6 flex flex-col gap-3"
      style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
      data-testid="escalas-tipo-form"
      @submit.prevent="createTipo"
    >
      <label class="text-[13px]" style="color: #2A1418">
        Nome
        <input
          v-model="form.nome"
          class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
          style="border: 1px solid rgba(42, 20, 24, 0.14)"
          required
        />
      </label>
      <label class="text-[13px]" style="color: #2A1418">
        Descrição
        <textarea
          v-model="form.descricao"
          rows="2"
          class="mt-1 w-full px-3 py-2 rounded-lg text-[14px]"
          style="border: 1px solid rgba(42, 20, 24, 0.14)"
        />
      </label>
      <div class="flex flex-wrap gap-3">
        <label class="text-[13px]" style="color: #2A1418">
          Unidade
          <select
            v-model="form.unidade_preferida"
            class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
          >
            <option value="ambos">Pessoa ou casal</option>
            <option value="pessoa">Só pessoa</option>
            <option value="casal">Só casal</option>
          </select>
        </label>
        <label class="text-[13px]" style="color: #2A1418">
          Recorrência
          <select
            v-model="form.recorrencia"
            class="mt-1 block px-3 py-2 rounded-lg text-[14px]"
            style="border: 1px solid rgba(42, 20, 24, 0.14)"
          >
            <option value="avulsa">Avulsa</option>
            <option value="semanal">Semanal</option>
            <option value="mensal">Mensal</option>
          </select>
        </label>
      </div>
      <button
        type="submit"
        class="self-start px-4 py-2 rounded-lg text-[13px] font-medium border-0 cursor-pointer"
        style="background: #6B1C2B; color: #FFFDFA"
        :disabled="saving"
      >
        {{ saving ? 'Salvando…' : 'Criar' }}
      </button>
    </form>

    <p v-if="loading" class="text-[14px]" style="color: rgba(42, 20, 24, 0.62)">Carregando…</p>
    <ul v-else class="flex flex-col gap-3 list-none p-0 m-0" data-testid="escalas-tipos-list">
      <li
        v-for="t in tipos"
        :key="t.id"
        class="rounded-xl p-4 flex items-start justify-between gap-3 cursor-pointer"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        @click="router.push(`/escalas/tipos/${t.id}`)"
      >
        <div class="min-w-0">
          <div class="font-serif text-[18px]" style="color: #2A1418">{{ t.nome }}</div>
          <div class="text-[13px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
            {{ t.equipes_count || 0 }} equipes · {{ t.ocorrencias_count || 0 }} ocorrências
          </div>
        </div>
        <button
          v-if="canManage"
          type="button"
          class="shrink-0 px-3 py-1.5 rounded-lg text-[12.5px] cursor-pointer"
          style="background: transparent; border: 1px solid rgba(138, 36, 54, 0.35); color: #8A2436"
          data-testid="escalas-excluir-tipo"
          @click="deleteTipo(t, $event)"
        >
          Excluir
        </button>
      </li>
      <li
        v-if="!tipos.length"
        class="text-[14px] py-8 text-center"
        style="color: rgba(42, 20, 24, 0.62)"
      >
        Nenhum tipo de escala ainda.
      </li>
    </ul>
  </div>
</template>
