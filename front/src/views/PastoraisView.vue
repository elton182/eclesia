<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import {
  createPastoral,
  deletePastoral,
  listPastorais,
  updatePastoral,
} from '@/services/pastorais'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const loading = ref(true)
const saving = ref(false)
const busyId = ref('')
const pastorais = ref([])
const showForm = ref(false)
const editingId = ref(null)
const form = ref({
  nome: '',
  ativa: true,
  ordem: 0,
  descricao_publica: '',
  contato_publico: '',
  publicado_no_site: false,
})

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'pastorais.manage', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

function resetForm() {
  editingId.value = null
  form.value = {
    nome: '',
    ativa: true,
    ordem: pastorais.value.length,
    descricao_publica: '',
    contato_publico: '',
    publicado_no_site: false,
  }
}

function openCreate() {
  resetForm()
  showForm.value = true
}

function openEdit(p) {
  editingId.value = p.id
  form.value = {
    nome: p.nome || '',
    ativa: p.ativa !== false,
    ordem: p.ordem ?? 0,
    descricao_publica: p.descricao_publica || '',
    contato_publico: p.contato_publico || '',
    publicado_no_site: !!p.publicado_no_site,
  }
  showForm.value = true
}

async function load() {
  loading.value = true
  try {
    pastorais.value = await listPastorais()
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao listar')
  } finally {
    loading.value = false
  }
}

async function salvar() {
  if (!canManage.value) return
  const nome = form.value.nome.trim()
  if (!nome) {
    innovToast('error', 'Pastorais', 'Informe o nome')
    return
  }
  saving.value = true
  try {
    const payload = {
      nome,
      ativa: !!form.value.ativa,
      ordem: Number(form.value.ordem) || 0,
      descricao_publica: form.value.descricao_publica || null,
      contato_publico: form.value.contato_publico || null,
      publicado_no_site: !!form.value.publicado_no_site,
    }
    if (editingId.value) {
      await updatePastoral(editingId.value, payload)
      innovToast('success', 'Pastorais', 'Atualizada')
    } else {
      const created = await createPastoral(payload)
      innovToast('success', 'Pastorais', 'Criada')
      showForm.value = false
      resetForm()
      await load()
      if (created?.id) router.push(`/pastorais/${created.id}`)
      return
    }
    showForm.value = false
    resetForm()
    await load()
  } catch (e) {
    innovToast('error', 'Pastorais', e.response?.data?.message || 'Falha ao salvar')
  } finally {
    saving.value = false
  }
}

async function excluir(p, e) {
  e?.stopPropagation?.()
  if (!canManage.value) return
  const ok = await innovConfirm({
    title: 'Excluir pastoral',
    message: `Excluir "${p.nome}"? Membros serão desvinculados.`,
    confirmText: 'Excluir',
  })
  if (!ok) return
  busyId.value = p.id
  try {
    await deletePastoral(p.id)
    pastorais.value = pastorais.value.filter((x) => x.id !== p.id)
    innovToast('success', 'Pastorais', 'Excluída')
  } catch (err) {
    innovToast('error', 'Pastorais', err.response?.data?.message || 'Falha ao excluir')
  } finally {
    busyId.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="pastorais-lista">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
      <div>
        <p
          class="text-[11px] font-medium tracking-wider uppercase mb-1"
          style="color: var(--color-accent-dark)"
        >
          Pastorais
        </p>
        <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
          Cadastro de pastorais
        </h1>
        <p class="text-[14px] mt-1 max-w-xl" style="color: var(--color-muted)">
          Pastorais e movimentos da igreja, com coordenadores e membros.
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn btn-primary"
        data-testid="pastorais-nova"
        @click="openCreate"
      >
        Nova pastoral
      </button>
    </header>

    <section
      v-if="showForm && canManage"
      class="card p-5 md:p-6 mb-8"
      data-testid="pastorais-form"
    >
      <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
        {{ editingId ? 'Editar pastoral' : 'Nova pastoral' }}
      </h2>
      <form class="flex flex-col gap-4" @submit.prevent="salvar">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_8rem_8rem]">
          <div>
            <label class="fld" for="past-nome">Nome</label>
            <input
              id="past-nome"
              v-model="form.nome"
              class="input"
              required
              data-testid="pastorais-nome"
            />
          </div>
          <div>
            <label class="fld" for="past-ordem">Ordem</label>
            <input
              id="past-ordem"
              v-model.number="form.ordem"
              type="number"
              min="0"
              class="input"
            />
          </div>
          <div class="flex items-end pb-1">
            <label class="inline-flex items-center gap-2 text-[13px] cursor-pointer">
              <input v-model="form.ativa" type="checkbox" data-testid="pastorais-ativa" />
              Ativa
            </label>
          </div>
        </div>
        <details class="text-[13px]" style="color: var(--color-muted)">
          <summary class="cursor-pointer">Campos do site público (opcional)</summary>
          <div class="grid gap-4 sm:grid-cols-2 mt-3">
            <div class="sm:col-span-2">
              <label class="fld" for="past-desc">Descrição pública</label>
              <textarea
                id="past-desc"
                v-model="form.descricao_publica"
                class="input min-h-[80px]"
                rows="3"
              />
            </div>
            <div>
              <label class="fld" for="past-contato">Contato público</label>
              <input id="past-contato" v-model="form.contato_publico" class="input" />
            </div>
            <div class="flex items-end pb-1">
              <label class="inline-flex items-center gap-2 text-[13px] cursor-pointer">
                <input v-model="form.publicado_no_site" type="checkbox" />
                Publicado no site
              </label>
            </div>
          </div>
        </details>
        <div class="flex justify-end gap-2 pt-1">
          <button
            type="button"
            class="btn btn-ghost"
            @click="showForm = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            data-testid="pastorais-salvar"
            :disabled="saving"
          >
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
        </div>
      </form>
    </section>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <ul
      v-else-if="pastorais.length"
      class="flex flex-col gap-3 list-none p-0 m-0"
      data-testid="pastorais-lista-itens"
    >
      <li v-for="p in pastorais" :key="p.id" class="card px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            class="min-w-0 flex-1 text-left border-0 bg-transparent cursor-pointer p-0"
            data-testid="pastorais-item"
            @click="router.push(`/pastorais/${p.id}`)"
          >
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">
              {{ p.nome }}
            </div>
            <div class="text-[13px] mt-0.5" style="color: var(--color-muted)">
              Ordem {{ p.ordem ?? 0 }}
              <span v-if="p.membros_count != null"> · {{ p.membros_count }} membro(s)</span>
            </div>
          </button>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <span
              class="text-[12px] font-medium px-2.5 py-1 rounded-full"
              :style="
                p.ativa !== false
                  ? { color: '#2A6B4A', background: '#E7F1EA' }
                  : { color: 'var(--color-muted)', background: 'var(--color-accent-soft)' }
              "
            >
              {{ p.ativa !== false ? 'Ativa' : 'Inativa' }}
            </span>
            <template v-if="canManage">
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-xs"
                data-testid="pastorais-editar"
                @click="openEdit(p)"
              >
                Editar
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-xs"
                style="color: var(--color-danger)"
                data-testid="pastorais-excluir"
                :disabled="busyId === p.id"
                @click="excluir(p, $event)"
              >
                Excluir
              </button>
            </template>
          </div>
        </div>
      </li>
    </ul>

    <div
      v-else
      class="rounded-xl px-6 py-10 text-center"
      style="background: var(--color-surface); border: 1px dashed var(--color-line)"
      data-testid="pastorais-vazio"
    >
      <p class="font-serif text-[17px]" style="color: var(--color-ink)">Nenhuma pastoral ainda</p>
      <p class="text-[13.5px] mt-2 max-w-sm mx-auto" style="color: var(--color-muted)">
        {{
          canManage
            ? 'Cadastre a primeira pastoral para vincular coordenadores e planejar o ano.'
            : 'Quando houver pastorais cadastradas, elas aparecerão aqui.'
        }}
      </p>
    </div>
  </div>
</template>
