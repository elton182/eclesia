<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { innovToast } from '@/plugins/toast'
import { filterEquipesBySearch, casaisRouteForEquipe } from '@/utils/eccFilters'
import { userHasPermission } from '@/utils/userRoles'

const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()
const equipes = ref([])
const search = ref('')
const loading = ref(false)
const mode = ref('list')
const form = ref({ id: null, nome: '', cor: '#6B1C2B' })
const saving = ref(false)

const canManageEquipes = computed(() =>
  userHasPermission(authStore.user, 'ecc.equipes.manage', {
    isSuperAdmin: authAdminStore.isAuthenticated,
  }),
)

const filteredEquipes = computed(() => filterEquipesBySearch(equipes.value, search.value))

const ensureTenant = () => {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/inicio')
    return false
  }
  return true
}

const load = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    const { data } = await api.get('/ecc/equipes')
    equipes.value = data.data || data
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao listar equipes')
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = { id: null, nome: '', cor: '#6B1C2B' }
  mode.value = 'form'
}

const openEdit = (item) => {
  form.value = { id: item.id, nome: item.nome, cor: item.cor || '#6B1C2B' }
  mode.value = 'form'
}

const save = async () => {
  saving.value = true
  try {
    if (form.value.id) {
      await api.put(`/ecc/equipes/${form.value.id}`, {
        nome: form.value.nome,
        cor: form.value.cor,
      })
    } else {
      await api.post('/ecc/equipes', {
        nome: form.value.nome,
        cor: form.value.cor,
      })
    }
    innovToast('success', 'OK', 'Equipe salva')
    mode.value = 'list'
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

const remove = async (item) => {
  if (!confirm(`Remover a equipe "${item.nome}"?`)) return
  try {
    await api.delete(`/ecc/equipes/${item.id}`)
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

const verCasais = (equipe) => {
  router.push(casaisRouteForEquipe(equipe.id))
}

onMounted(load)
</script>

<template>
  <div class="p-6 md:p-[30px]" data-testid="ecc-equipes-page">
    <div v-if="mode === 'list'">
      <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
        <div>
          <h1 class="font-serif text-[27px] leading-tight" style="color: #2A1418">Equipes</h1>
          <p class="text-[13.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
            Grupos aos quais os casais pertencem no movimento.
          </p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="canManageEquipes"
            type="button"
            class="rounded-lg px-4 py-2.5 text-[13px] font-medium border-0"
            style="background: #6B1C2B; color: #FFFDFA"
            data-testid="equipes-nova"
            @click="openCreate"
          >
            Nova equipe
          </button>
        </div>
      </div>

      <div class="flex gap-2.5 mb-[18px] flex-wrap">
        <input
          v-model="search"
          type="search"
          class="flex-1 min-w-[220px] rounded-lg px-3.5 py-2.5 text-[13.5px] outline-none"
          style="border: 1px solid rgba(42, 20, 24, 0.14); background: #fff; color: #2A1418"
          placeholder="Buscar equipe ou casal…"
          data-testid="equipes-search"
          aria-label="Pesquisar equipe"
        >
      </div>

      <div
        class="rounded-[11px] overflow-hidden"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
      >
        <div
          class="hidden md:grid gap-3.5 px-[18px] py-3 text-[11px] font-medium tracking-wide uppercase"
          style="
            grid-template-columns: 1.6fr 0.9fr 1.1fr 88px;
            background: #F3EDE6;
            color: rgba(42, 20, 24, 0.62);
          "
        >
          <span>Equipe</span>
          <span>Casais</span>
          <span>Coordenação</span>
          <span />
        </div>

        <div v-if="loading" class="px-[18px] py-8" style="color: rgba(42, 20, 24, 0.5)">Carregando…</div>
        <div
          v-else-if="!filteredEquipes.length"
          class="px-[18px] py-8 text-center"
          style="color: rgba(42, 20, 24, 0.5)"
        >
          {{ search ? 'Nenhuma equipe encontrada.' : 'Nenhuma equipe cadastrada.' }}
        </div>

        <div
          v-for="equipe in filteredEquipes"
          :key="equipe.id"
          class="grid gap-3.5 px-[18px] py-4 items-center"
          style="
            grid-template-columns: 1.6fr 0.9fr 1.1fr 88px;
            border-top: 1px solid rgba(42, 20, 24, 0.07);
          "
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <div
              class="w-[30px] h-[30px] rounded-md flex items-center justify-center font-serif text-[13px] font-medium shrink-0"
              :style="{ background: '#F3EDE6', color: equipe.cor || '#6B1C2B' }"
            >
              {{ (equipe.nome || '?')[0] }}
            </div>
            <div class="min-w-0">
              <div class="text-[13.5px] font-medium truncate" style="color: #2A1418">{{ equipe.nome }}</div>
            </div>
          </div>
          <div class="text-[13px]" style="color: rgba(42, 20, 24, 0.7)">
            {{ equipe.casais_count ?? 0 }} casais
          </div>
          <div class="text-[13px] truncate" style="color: rgba(42, 20, 24, 0.7)">—</div>
          <div class="flex gap-3 justify-end text-[12.5px] font-medium">
            <button
              type="button"
              class="bg-transparent border-0 cursor-pointer p-0"
              style="color: #8A2436"
              data-testid="equipe-ver-casais"
              @click="verCasais(equipe)"
            >
              abrir
            </button>
            <button
              v-if="canManageEquipes"
              type="button"
              class="bg-transparent border-0 cursor-pointer p-0"
              style="color: rgba(42, 20, 24, 0.62)"
              data-testid="equipe-editar"
              @click="openEdit(equipe)"
            >
              ···
            </button>
          </div>
        </div>

        <div
          class="flex items-center justify-between px-[18px] py-3 text-[12.5px]"
          style="border-top: 1px solid rgba(42, 20, 24, 0.07); color: rgba(42, 20, 24, 0.62)"
        >
          <span>Mostrando {{ filteredEquipes.length }} de {{ equipes.length }} equipes</span>
        </div>
      </div>
    </div>

    <div
      v-else
      class="rounded-[11px] p-6 max-w-md"
      style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
    >
      <h3 class="font-serif text-xl mb-4">{{ form.id ? 'Editar equipe' : 'Nova equipe' }}</h3>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="eq-nome">Nome</label>
          <input id="eq-nome" v-model="form.nome" class="input" required>
        </div>
        <div>
          <label class="fld" for="eq-cor">Cor</label>
          <input id="eq-cor" v-model="form.cor" type="color" class="h-10 w-20">
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">Salvar</button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>
