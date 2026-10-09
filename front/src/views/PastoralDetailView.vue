<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import {
  addMembro,
  getPastoral,
  listMembros,
  removeMembro,
  updatePastoral,
} from '@/services/pastorais'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { filterUsersBySearch, labelPapelMembro, unwrapList } from '@/utils/planejamento'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const loading = ref(true)
const saving = ref(false)
const pastoral = ref(null)
const membros = ref([])
const users = ref([])
const userSearch = ref('')
const selectedUserId = ref('')
const selectedPapel = ref('membro')

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'pastorais.manage', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

const membroUserIds = computed(() => new Set(membros.value.map((m) => m.user_id || m.user?.id)))

const usersDisponiveis = computed(() => {
  const filtered = filterUsersBySearch(users.value, userSearch.value)
  return filtered.filter((u) => !membroUserIds.value.has(u.id))
})

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [p, m] = await Promise.all([getPastoral(id), listMembros(id)])
    if (!p) {
      innovToast('error', 'Pastorais', 'Não encontrada')
      router.push('/pastorais')
      return
    }
    pastoral.value = p
    membros.value = m.map(normalizeMembro)
    if (canManage.value) await loadUsers()
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao carregar')
    router.push('/pastorais')
  } finally {
    loading.value = false
  }
}

function normalizeMembro(row) {
  return {
    user_id: row.user_id || row.user?.id,
    papel: row.papel || 'membro',
    name: row.user?.name || row.name || '—',
    email: row.user?.email || row.email || '',
  }
}

async function loadUsers() {
  try {
    const { data } = await api.get('/users')
    users.value = unwrapList(data)
  } catch {
    users.value = []
  }
}

async function toggleAtiva() {
  if (!canManage.value || !pastoral.value) return
  saving.value = true
  try {
    pastoral.value = await updatePastoral(pastoral.value.id, {
      ativa: pastoral.value.ativa === false,
    })
    innovToast('success', 'Pastorais', pastoral.value.ativa !== false ? 'Ativada' : 'Desativada')
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao atualizar')
  } finally {
    saving.value = false
  }
}

async function vincular() {
  if (!canManage.value || !selectedUserId.value) return
  saving.value = true
  try {
    await addMembro(pastoral.value.id, {
      user_id: selectedUserId.value,
      papel: selectedPapel.value,
    })
    innovToast('success', 'Pastorais', 'Membro vinculado')
    selectedUserId.value = ''
    userSearch.value = ''
    membros.value = (await listMembros(pastoral.value.id)).map(normalizeMembro)
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao vincular')
  } finally {
    saving.value = false
  }
}

async function desvincular(m) {
  if (!canManage.value) return
  const ok = await innovConfirm({
    title: 'Remover membro',
    message: `Desvincular ${m.name} desta pastoral?`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await removeMembro(pastoral.value.id, m.user_id)
    membros.value = membros.value.filter((x) => x.user_id !== m.user_id)
    innovToast('success', 'Pastorais', 'Membro removido')
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao remover')
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="pastoral-detalhe">
    <button
      type="button"
      class="btn btn-ghost !px-0 mb-4 text-[13px]"
      style="color: var(--color-primary-hover)"
      @click="router.push('/pastorais')"
    >
      ← Pastorais
    </button>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <template v-else-if="pastoral">
      <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p
            class="text-[11px] font-medium tracking-wider uppercase mb-1"
            style="color: var(--color-accent-dark)"
          >
            Pastoral
          </p>
          <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
            {{ pastoral.nome }}
          </h1>
          <p class="text-[14px] mt-1" style="color: var(--color-muted)">
            Gerencie coordenadores e membros vinculados.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            class="text-[12px] font-medium px-2.5 py-1 rounded-full self-center"
            :style="
              pastoral.ativa !== false
                ? { color: '#2A6B4A', background: '#E7F1EA' }
                : { color: 'var(--color-muted)', background: 'var(--color-accent-soft)' }
            "
          >
            {{ pastoral.ativa !== false ? 'Ativa' : 'Inativa' }}
          </span>
          <button
            v-if="canManage"
            type="button"
            class="btn btn-ghost"
            data-testid="pastoral-toggle-ativa"
            :disabled="saving"
            @click="toggleAtiva"
          >
            {{ pastoral.ativa !== false ? 'Desativar' : 'Ativar' }}
          </button>
        </div>
      </header>

      <section
        v-if="canManage"
        class="card p-5 md:p-6 mb-6"
        data-testid="pastoral-add-membro"
      >
        <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
          Vincular usuário
        </h2>
        <form class="flex flex-col gap-4" @submit.prevent="vincular">
          <div>
            <label class="fld" for="membro-busca">Buscar usuário</label>
            <input
              id="membro-busca"
              v-model="userSearch"
              type="search"
              class="input"
              placeholder="Nome ou e-mail…"
              data-testid="pastoral-membro-busca"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
            <div>
              <label class="fld" for="membro-user">Usuário</label>
              <select
                id="membro-user"
                v-model="selectedUserId"
                class="input"
                required
                data-testid="pastoral-membro-user"
              >
                <option value="" disabled>Selecione…</option>
                <option v-for="u in usersDisponiveis.slice(0, 50)" :key="u.id" :value="u.id">
                  {{ u.name }} — {{ u.email }}
                </option>
              </select>
            </div>
            <div>
              <label class="fld" for="membro-papel">Papel</label>
              <select
                id="membro-papel"
                v-model="selectedPapel"
                class="input"
                data-testid="pastoral-membro-papel"
              >
                <option value="coordenador">Coordenador</option>
                <option value="membro">Membro</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end">
            <button
              type="submit"
              class="btn btn-primary"
              data-testid="pastoral-membro-add"
              :disabled="saving || !selectedUserId"
            >
              Vincular
            </button>
          </div>
        </form>
      </section>

      <section class="card p-5 md:p-6">
        <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
          Membros ({{ membros.length }})
        </h2>
        <ul
          v-if="membros.length"
          class="list-none p-0 m-0 divide-y"
          style="border-color: var(--color-line)"
          data-testid="pastoral-membros"
        >
          <li
            v-for="m in membros"
            :key="m.user_id"
            class="flex flex-wrap items-center justify-between gap-2 py-3"
          >
            <div class="min-w-0">
              <div class="text-[14px] font-medium" style="color: var(--color-ink)">
                {{ m.name }}
              </div>
              <div class="text-[12.5px]" style="color: var(--color-muted)">
                {{ m.email }}
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span
                class="text-[12px] font-medium px-2.5 py-1 rounded-full"
                style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
              >
                {{ labelPapelMembro(m.papel) }}
              </span>
              <button
                v-if="canManage"
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-xs"
                style="color: var(--color-danger)"
                data-testid="pastoral-membro-remover"
                @click="desvincular(m)"
              >
                Remover
              </button>
            </div>
          </li>
        </ul>
        <p v-else class="text-[13.5px]" style="color: var(--color-muted)">
          Nenhum membro vinculado.
        </p>
      </section>
    </template>
  </div>
</template>
