<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const SECOES = [
  { value: 'grade', label: 'Grade (missa / celebração)' },
  { value: 'festa', label: 'Festa / especial' },
  { value: 'casamento', label: 'Casamento' },
]

const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const tipos = ref([])
const form = ref({
  nome: '',
  secao_padrao: 'grade',
  exige_titulo: false,
  ordem: 0,
})
const editingId = ref(null)

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'calendario.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

function labelSecao(v) {
  return SECOES.find((s) => s.value === v)?.label || v
}

function resetForm() {
  editingId.value = null
  form.value = {
    nome: '',
    secao_padrao: 'grade',
    exige_titulo: false,
    ordem: (tipos.value.at(-1)?.ordem || 0) + 1,
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/calendario/evento-tipos', { params: { todos: 1 } })
    tipos.value = data.data || data || []
    if (!editingId.value) {
      form.value.ordem = (tipos.value.at(-1)?.ordem || 0) + 1
    }
  } catch (e) {
    innovToast('error', 'Tipos', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

function editar(tipo) {
  editingId.value = tipo.id
  form.value = {
    nome: tipo.nome,
    secao_padrao: tipo.secao_padrao,
    exige_titulo: !!tipo.exige_titulo,
    ordem: tipo.ordem ?? 0,
  }
}

async function salvar() {
  if (!form.value.nome.trim()) {
    innovToast('error', 'Tipos', 'Informe o nome.')
    return
  }
  try {
    if (editingId.value) {
      const { data } = await api.put(`/calendario/evento-tipos/${editingId.value}`, {
        nome: form.value.nome.trim(),
        secao_padrao: form.value.secao_padrao,
        exige_titulo: !!form.value.exige_titulo,
        ordem: Number(form.value.ordem) || 0,
      })
      const updated = data.data || data
      tipos.value = tipos.value.map((t) => (t.id === updated.id ? { ...t, ...updated } : t))
      innovToast('success', 'Tipos', 'Atualizado')
    } else {
      const { data } = await api.post('/calendario/evento-tipos', {
        nome: form.value.nome.trim(),
        secao_padrao: form.value.secao_padrao,
        exige_titulo: !!form.value.exige_titulo,
        ordem: Number(form.value.ordem) || 0,
      })
      const created = data.data || data
      tipos.value = [...tipos.value, created].sort((a, b) => (a.ordem || 0) - (b.ordem || 0))
      innovToast('success', 'Tipos', 'Cadastrado')
    }
    resetForm()
  } catch (e) {
    innovToast('error', 'Tipos', e.response?.data?.message || 'Falha ao salvar')
  }
}

async function toggleAtivo(tipo) {
  try {
    const { data } = await api.put(`/calendario/evento-tipos/${tipo.id}`, {
      ativo: !tipo.ativo,
    })
    const updated = data.data || data
    tipos.value = tipos.value.map((t) => (t.id === updated.id ? { ...t, ...updated } : t))
  } catch (e) {
    innovToast('error', 'Tipos', e.response?.data?.message || 'Falha')
  }
}

async function remover(tipo) {
  if (tipo.sistema) {
    innovToast('error', 'Tipos', 'Tipos do sistema não podem ser excluídos.')
    return
  }
  const ok = await innovConfirm({
    title: 'Remover tipo',
    message: `Remover “${tipo.nome}”?`,
    confirmText: 'Remover',
  })
  if (!ok) return
  try {
    await api.delete(`/calendario/evento-tipos/${tipo.id}`)
    tipos.value = tipos.value.filter((t) => t.id !== tipo.id)
    if (editingId.value === tipo.id) resetForm()
    innovToast('success', 'Tipos', 'Removido')
  } catch (e) {
    innovToast('error', 'Tipos', e.response?.data?.message || 'Falha ao remover')
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full space-y-6" data-testid="calendario-tipos">
    <header>
      <p class="page-eyebrow">Calendário</p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Tipos de evento
      </h1>
      <p class="text-[14px] mt-1" style="color: var(--color-muted)">
        Catálogo usado ao adicionar eventos no mês (Missa, Festa, etc.).
      </p>
    </header>

    <div v-if="loading" class="text-sm" style="color: var(--color-muted)">Carregando…</div>

    <template v-else>
      <div class="card p-5 md:p-6 space-y-3">
        <ul class="list-none p-0 m-0 space-y-0" data-testid="calendario-tipos-lista">
          <li
            v-for="tipo in tipos"
            :key="tipo.id"
            class="flex flex-wrap items-center justify-between gap-2 py-3 border-b text-sm"
            style="border-color: var(--color-line); color: var(--color-ink)"
          >
            <div class="min-w-0">
              <div class="font-medium">
                {{ tipo.nome }}
                <span
                  v-if="tipo.sistema"
                  class="text-[11px] font-normal ml-1"
                  style="color: var(--color-muted)"
                >(sistema)</span>
                <span
                  v-if="!tipo.ativo"
                  class="text-[11px] font-normal ml-1"
                  style="color: var(--color-danger)"
                >(inativo)</span>
              </div>
              <div class="text-[12px]" style="color: var(--color-muted)">
                {{ labelSecao(tipo.secao_padrao) }}
                <span v-if="tipo.exige_titulo"> · exige nome</span>
              </div>
            </div>
            <div v-if="canManage" class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-sm" @click="editar(tipo)">
                Editar
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                @click="toggleAtivo(tipo)"
              >
                {{ tipo.ativo ? 'Desativar' : 'Ativar' }}
              </button>
              <button
                v-if="!tipo.sistema"
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-sm"
                style="color: var(--color-danger)"
                @click="remover(tipo)"
              >
                Remover
              </button>
            </div>
          </li>
        </ul>
      </div>

      <form
        v-if="canManage"
        class="card p-5 md:p-6 space-y-4"
        data-testid="calendario-tipos-form"
        @submit.prevent="salvar"
      >
        <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
          {{ editingId ? 'Editar tipo' : 'Novo tipo' }}
        </h2>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="fld" for="tipo-nome">Nome</label>
            <input
              id="tipo-nome"
              v-model="form.nome"
              class="input"
              required
              data-testid="calendario-tipo-nome"
            />
          </div>
          <div>
            <label class="fld" for="tipo-secao">Seção no PDF</label>
            <select id="tipo-secao" v-model="form.secao_padrao" class="input">
              <option v-for="s in SECOES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="fld" for="tipo-ordem">Ordem</label>
            <input id="tipo-ordem" v-model.number="form.ordem" type="number" min="0" class="input" />
          </div>
          <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 text-sm" style="color: var(--color-ink)">
              <input v-model="form.exige_titulo" type="checkbox" class="rounded" />
              Exige nome digitado (como “Outro”)
            </label>
          </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            v-if="editingId"
            type="button"
            class="btn btn-ghost"
            @click="resetForm"
          >
            Cancelar
          </button>
          <button type="submit" class="btn btn-primary" data-testid="calendario-tipo-salvar">
            {{ editingId ? 'Salvar' : 'Adicionar' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>
