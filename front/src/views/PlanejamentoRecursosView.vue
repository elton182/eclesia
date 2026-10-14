<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  createRecurso,
  deleteRecurso,
  listRecursos,
  updateRecurso,
} from '@/services/planejamentoRecursos'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { labelTipoRecurso, splitPorTipo } from '@/utils/planejamentoRecursos'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const loading = ref(true)
const saving = ref(false)
const busyId = ref('')
const itens = ref([])
const showForm = ref(false)
const editing = ref(null)

const emptyForm = (tipo = 'espaco') => ({
  nome: '',
  tipo,
  quantidade: 1,
  descricao: '',
  ativo: true,
})
const form = ref(emptyForm())

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canGerir = computed(
  () =>
    userHasPermission(auth.user, 'planejamento.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

const grupos = computed(() => {
  const { espacos, recursos } = splitPorTipo(itens.value)
  return [
    {
      tipo: 'espaco',
      titulo: 'Espaços',
      ajuda: 'Salas, salão, auditório. Um espaço comporta um evento por vez.',
      itens: espacos,
    },
    {
      tipo: 'recurso',
      titulo: 'Recursos',
      ajuda: 'Equipamentos móveis (projetor, som). A quantidade limita o uso simultâneo.',
      itens: recursos,
    },
  ]
})

async function load() {
  loading.value = true
  try {
    itens.value = await listRecursos()
  } catch (e) {
    innovToast('error', 'Espaços e recursos', e.response?.data?.message || 'Falha ao listar')
  } finally {
    loading.value = false
  }
}

function openCreate(tipo) {
  editing.value = null
  form.value = emptyForm(tipo)
  showForm.value = true
}

function openEdit(item) {
  editing.value = item
  form.value = {
    nome: item.nome || '',
    tipo: item.tipo || 'espaco',
    quantidade: item.quantidade || 1,
    descricao: item.descricao || '',
    ativo: item.ativo !== false,
  }
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editing.value = null
}

async function salvar() {
  const nome = form.value.nome.trim()
  if (!nome) {
    innovToast('error', 'Espaços e recursos', 'Informe o nome')
    return
  }
  const payload = {
    nome,
    tipo: form.value.tipo,
    quantidade: form.value.tipo === 'espaco' ? 1 : Math.max(1, Number(form.value.quantidade) || 1),
    descricao: form.value.descricao || null,
    ativo: !!form.value.ativo,
  }
  saving.value = true
  try {
    if (editing.value) {
      const updated = await updateRecurso(editing.value.id, payload)
      itens.value = itens.value.map((i) => (i.id === updated.id ? updated : i))
    } else {
      itens.value = [...itens.value, await createRecurso(payload)]
    }
    innovToast('success', 'Espaços e recursos', editing.value ? 'Atualizado' : 'Cadastrado')
    closeForm()
  } catch (e) {
    const errors = e.response?.data?.errors
    innovToast(
      'error',
      'Espaços e recursos',
      errors?.nome?.[0] || e.response?.data?.message || 'Falha ao salvar',
    )
  } finally {
    saving.value = false
  }
}

async function excluir(item) {
  const ok = await innovConfirm({
    title: 'Excluir',
    message: `Excluir "${item.nome}"? Se já estiver em algum evento, ele será apenas inativado.`,
    confirmText: 'Excluir',
  })
  if (!ok) return
  busyId.value = item.id
  try {
    const inativado = await deleteRecurso(item.id)
    if (inativado) {
      itens.value = itens.value.map((i) => (i.id === inativado.id ? inativado : i))
      innovToast('success', 'Espaços e recursos', 'Em uso por eventos — item inativado')
    } else {
      itens.value = itens.value.filter((i) => i.id !== item.id)
      innovToast('success', 'Espaços e recursos', 'Excluído')
    }
  } catch (e) {
    innovToast('error', 'Espaços e recursos', e.response?.data?.message || 'Falha ao excluir')
  } finally {
    busyId.value = ''
  }
}

onMounted(() => {
  if (!canGerir.value) {
    router.replace('/planejamento')
    return
  }
  load()
})
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="planejamento-recursos">
    <header class="mb-8">
      <p
        class="text-[11px] font-medium tracking-wider uppercase mb-1"
        style="color: var(--color-accent-dark)"
      >
        Planejamento anual
      </p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Espaços e recursos
      </h1>
      <p class="text-[14px] mt-1 max-w-2xl" style="color: var(--color-muted)">
        Dois eventos podem acontecer ao mesmo tempo se usarem espaços diferentes e houver recursos
        suficientes. Ao propor, o sistema bloqueia sala ocupada ou equipamento esgotado no horário.
      </p>
    </header>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <section
        v-for="g in grupos"
        :key="g.tipo"
        class="card p-5 md:p-6"
        :data-testid="`planejamento-recursos-${g.tipo}`"
      >
        <div class="flex items-start justify-between gap-3 mb-1">
          <h2 class="font-serif text-[18px] font-medium" style="color: var(--color-ink)">
            {{ g.titulo }}
          </h2>
          <button
            type="button"
            class="btn btn-primary text-xs"
            :data-testid="`planejamento-recursos-novo-${g.tipo}`"
            @click="openCreate(g.tipo)"
          >
            Novo
          </button>
        </div>
        <p class="text-[13px] mb-4" style="color: var(--color-muted)">{{ g.ajuda }}</p>

        <ul v-if="g.itens.length" class="list-none p-0 m-0 space-y-2">
          <li
            v-for="item in g.itens"
            :key="item.id"
            class="rounded-lg px-3 py-3 flex flex-wrap items-center justify-between gap-3"
            style="border: 1px solid var(--color-line)"
            data-testid="planejamento-recurso-item"
          >
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-[14px]" style="color: var(--color-ink)">{{ item.nome }}</span>
                <span
                  class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                  style="background: var(--color-primary-soft); color: var(--color-on-primary)"
                >
                  {{ labelTipoRecurso(item.tipo) }}
                </span>
                <span
                  v-if="item.tipo === 'recurso'"
                  class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                  style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
                >
                  {{ item.quantidade }} {{ item.quantidade === 1 ? 'unidade' : 'unidades' }}
                </span>
                <span
                  v-if="!item.ativo"
                  class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                  style="border: 1px solid var(--color-line); color: var(--color-muted)"
                >
                  Inativo
                </span>
              </div>
              <p v-if="item.descricao" class="text-[12.5px] mt-0.5" style="color: var(--color-muted)">
                {{ item.descricao }}
              </p>
            </div>
            <div class="flex gap-2 shrink-0">
              <button type="button" class="btn btn-ghost text-xs" @click="openEdit(item)">Editar</button>
              <button
                type="button"
                class="btn btn-ghost text-xs"
                style="color: var(--color-danger)"
                :disabled="busyId === item.id"
                @click="excluir(item)"
              >
                Excluir
              </button>
            </div>
          </li>
        </ul>
        <p v-else class="text-[13px]" style="color: var(--color-muted)">Nenhum item cadastrado.</p>
      </section>
    </div>

    <div
      v-if="showForm"
      class="fixed inset-0 z-[60] flex items-end md:items-center justify-center"
      style="background: rgba(42, 20, 24, 0.45)"
      data-testid="planejamento-recurso-form"
      @click.self="closeForm"
    >
      <div
        class="w-full max-w-md rounded-t-2xl md:rounded-2xl p-5 max-h-[92vh] overflow-y-auto"
        style="background: var(--color-surface)"
      >
        <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
          {{ editing ? 'Editar' : 'Novo' }} {{ form.tipo === 'espaco' ? 'espaço' : 'recurso' }}
        </h2>
        <form class="flex flex-col gap-4" @submit.prevent="salvar">
          <div>
            <label class="fld" for="rec-nome">Nome</label>
            <input
              id="rec-nome"
              v-model="form.nome"
              class="input"
              required
              maxlength="120"
              :placeholder="form.tipo === 'espaco' ? 'Ex.: Sala 2' : 'Ex.: Projetor móvel'"
              data-testid="planejamento-recurso-nome"
            />
          </div>
          <div class="grid gap-4 grid-cols-2">
            <div>
              <label class="fld" for="rec-tipo">Tipo</label>
              <select id="rec-tipo" v-model="form.tipo" class="input" data-testid="planejamento-recurso-tipo">
                <option value="espaco">Espaço</option>
                <option value="recurso">Recurso</option>
              </select>
            </div>
            <div>
              <label class="fld" for="rec-qtd">Quantidade</label>
              <input
                id="rec-qtd"
                v-model.number="form.quantidade"
                type="number"
                min="1"
                max="9999"
                class="input"
                :disabled="form.tipo === 'espaco'"
                data-testid="planejamento-recurso-quantidade"
              />
            </div>
          </div>
          <div>
            <label class="fld" for="rec-desc">Descrição</label>
            <textarea id="rec-desc" v-model="form.descricao" class="input" rows="2" />
          </div>
          <label class="inline-flex items-center gap-2 text-[13.5px] cursor-pointer">
            <input v-model="form.ativo" type="checkbox" />
            Ativo (disponível para novos eventos)
          </label>
          <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" @click="closeForm">Cancelar</button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
              data-testid="planejamento-recurso-salvar"
            >
              {{ saving ? 'Salvando…' : 'Salvar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
