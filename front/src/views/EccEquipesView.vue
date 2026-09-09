<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useTenantStore } from '@/stores/tenant'
import { innovToast } from '@/plugins/toast'
import { filterEquipesBySearch, casaisRouteForEquipe } from '@/utils/eccFilters'

const router = useRouter()
const tenantStore = useTenantStore()
const equipes = ref([])
const search = ref('')
const loading = ref(false)
const mode = ref('list')
const form = ref({ id: null, nome: '', cor: '#00234E' })
const saving = ref(false)

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
  form.value = { id: null, nome: '', cor: '#00234E' }
  mode.value = 'form'
}

const openEdit = (item) => {
  form.value = { id: item.id, nome: item.nome, cor: item.cor || '#00234E' }
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
  <div data-testid="ecc-equipes-page">
    <div class="mb-6">
      <p class="page-eyebrow">ECC</p>
      <h2 class="text-3xl" style="color: var(--color-primary)">Equipes</h2>
      <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
        Equipes do movimento (ex.: EQUIPE A) às quais os casais pertencem.
      </p>
    </div>

    <div v-if="mode === 'list'" class="space-y-4">
      <div class="flex flex-wrap gap-3 items-center">
        <button class="btn btn-primary" @click="openCreate">Nova equipe</button>
        <input
          v-model="search"
          type="search"
          class="input max-w-sm"
          placeholder="Pesquisar equipe…"
          data-testid="equipes-search"
          aria-label="Pesquisar equipe"
        />
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-if="loading" class="card p-6" style="color: var(--color-muted)">Carregando…</div>
        <div
          v-else-if="!filteredEquipes.length"
          class="card p-6 sm:col-span-2 lg:col-span-3 text-center"
          style="color: var(--color-muted)"
        >
          {{ search ? 'Nenhuma equipe encontrada.' : 'Nenhuma equipe cadastrada.' }}
        </div>
        <div
          v-for="equipe in filteredEquipes"
          :key="equipe.id"
          class="card overflow-hidden"
        >
          <div class="h-1.5" :style="{ background: equipe.cor || 'var(--color-accent)' }" />
          <div class="p-5">
            <h3 class="text-xl" style="color: var(--color-ink)">{{ equipe.nome }}</h3>
            <button
              type="button"
              class="mt-2 text-left text-sm w-full group"
              style="color: var(--color-muted)"
              data-testid="equipe-ver-casais-count"
              :aria-label="`Ver casais da equipe ${equipe.nome}`"
              @click="verCasais(equipe)"
            >
              <strong
                class="text-2xl group-hover:underline"
                style="color: var(--color-primary); font-family: Fraunces, serif"
              >
                {{ equipe.casais_count ?? 0 }}
              </strong>
              casais
            </button>
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                class="btn btn-primary"
                data-testid="equipe-ver-casais"
                @click="verCasais(equipe)"
              >
                Ver casais
              </button>
              <button class="btn btn-ghost" @click="openEdit(equipe)">Editar</button>
              <button class="btn btn-ghost text-red-700" @click="remove(equipe)">Excluir</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-6 max-w-md">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar equipe' : 'Nova equipe' }}</h3>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="eq-nome">Nome</label>
          <input id="eq-nome" v-model="form.nome" class="input" required />
        </div>
        <div>
          <label class="fld" for="eq-cor">Cor</label>
          <input id="eq-cor" v-model="form.cor" type="color" class="h-10 w-20" />
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">Salvar</button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>
