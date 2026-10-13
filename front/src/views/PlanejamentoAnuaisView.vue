<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createAnual, deleteAnual, listAnuais } from '@/services/planejamento'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import { labelStatusPlanejamento } from '@/utils/planejamento'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()

const loading = ref(true)
const saving = ref(false)
const busyId = ref('')
const anuais = ref([])
const form = ref({
  ano: new Date().getFullYear() + (new Date().getMonth() >= 10 ? 1 : 0),
})

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canGerir = computed(
  () =>
    userHasPermission(auth.user, 'planejamento.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

async function load() {
  loading.value = true
  try {
    anuais.value = await listAnuais()
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao listar')
  } finally {
    loading.value = false
  }
}

async function criar() {
  if (!canGerir.value) return
  saving.value = true
  try {
    const created = await createAnual({ ano: Number(form.value.ano) })
    innovToast('success', 'Planejamento', 'Ano criado em rascunho')
    router.push(`/planejamento/${created.id}`)
  } catch (e) {
    innovToast('error', 'Planejamento', e.response?.data?.message || 'Falha ao criar')
  } finally {
    saving.value = false
  }
}

async function excluir(a, e) {
  e?.stopPropagation?.()
  if (!canGerir.value) return
  const ok = await innovConfirm({
    title: 'Excluir planejamento',
    message: `Excluir o ano ${a.ano}? Eventos serão removidos.`,
    confirmText: 'Excluir',
  })
  if (!ok) return
  busyId.value = a.id
  try {
    await deleteAnual(a.id)
    anuais.value = anuais.value.filter((x) => x.id !== a.id)
    innovToast('success', 'Planejamento', 'Excluído')
  } catch (err) {
    innovToast('error', 'Planejamento', err.response?.data?.message || 'Falha ao excluir')
  } finally {
    busyId.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="planejamento-lista">
    <header class="mb-8">
      <p
        class="text-[11px] font-medium tracking-wider uppercase mb-1"
        style="color: var(--color-accent-dark)"
      >
        Pastorais
      </p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Planejamento anual
      </h1>
      <p class="text-[14px] mt-1 max-w-xl" style="color: var(--color-muted)">
        Coleta de atividades das pastorais, revisão e fechamento do ano — distinto do calendário oficial.
      </p>
    </header>

    <section
      v-if="canGerir"
      class="card p-5 md:p-6 mb-8"
      data-testid="planejamento-novo"
    >
      <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
        Abrir ano
      </h2>
      <form class="flex flex-col gap-4" @submit.prevent="criar">
        <div class="max-w-[10rem]">
          <label class="fld" for="pl-ano">Ano</label>
          <input
            id="pl-ano"
            v-model.number="form.ano"
            type="number"
            min="2020"
            max="2100"
            class="input"
            required
            data-testid="planejamento-ano"
          />
        </div>
        <div class="flex justify-end">
          <button
            type="submit"
            class="btn btn-primary"
            data-testid="planejamento-criar"
            :disabled="saving"
          >
            {{ saving ? 'Criando…' : 'Criar rascunho' }}
          </button>
        </div>
      </form>
    </section>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <ul
      v-else-if="anuais.length"
      class="flex flex-col gap-3 list-none p-0 m-0"
      data-testid="planejamento-lista-itens"
    >
      <li v-for="a in anuais" :key="a.id" class="card px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            class="min-w-0 flex-1 text-left border-0 bg-transparent cursor-pointer p-0"
            data-testid="planejamento-item"
            @click="router.push(`/planejamento/${a.id}`)"
          >
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">
              {{ a.ano }}
            </div>
            <div class="text-[13px] mt-0.5" style="color: var(--color-muted)">
              <span v-if="a.eventos_count != null">{{ a.eventos_count }} evento(s)</span>
              <span v-else>Abrir calendário</span>
            </div>
          </button>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <span
              class="text-[12px] font-medium px-2.5 py-1 rounded-full"
              style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
            >
              {{ labelStatusPlanejamento(a.status) }}
            </span>
            <button
              v-if="canGerir"
              type="button"
              class="btn btn-ghost !py-1.5 !px-3 text-xs"
              style="color: var(--color-danger)"
              data-testid="planejamento-excluir"
              :disabled="busyId === a.id"
              @click="excluir(a, $event)"
            >
              Excluir
            </button>
          </div>
        </div>
      </li>
    </ul>

    <div
      v-else
      class="rounded-xl px-6 py-10 text-center"
      style="background: var(--color-surface); border: 1px dashed var(--color-line)"
      data-testid="planejamento-vazio"
    >
      <p class="font-serif text-[17px]" style="color: var(--color-ink)">Nenhum ano ainda</p>
      <p class="text-[13.5px] mt-2 max-w-sm mx-auto" style="color: var(--color-muted)">
        {{
          canGerir
            ? 'Crie o rascunho do ano para iniciar a coleta com as pastorais.'
            : 'Quando o responsável abrir o planejamento, ele aparecerá aqui.'
        }}
      </p>
    </div>
  </div>
</template>
