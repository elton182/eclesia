<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovConfirm } from '@/plugins/dialog'
import {
  labelMesCalendario,
  labelStatusCalendario,
  MES_NOMES,
  proximoMesCalendario,
} from '@/utils/calendario'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'

const router = useRouter()
const auth = useAuthStore()
const authAdmin = useAuthAdminStore()
const loading = ref(true)
const saving = ref(false)
const busyId = ref('')
const mensais = ref([])

const next = new Date()
next.setMonth(next.getMonth() + 1)
const form = ref({
  ano: next.getFullYear(),
  mes: next.getMonth() + 1,
  titulo: '',
})

const isPlatformAdmin = computed(() => authAdmin.isAuthenticated && !auth.isAuthenticated)
const canManage = computed(
  () =>
    userHasPermission(auth.user, 'calendario.gerir', { isSuperAdmin: isPlatformAdmin.value }) ||
    isPlatformAdmin.value,
)

const mesOptions = computed(() =>
  MES_NOMES.slice(1).map((nome, i) => ({ value: i + 1, label: nome })),
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/calendario/mensais')
    mensais.value = data.data || data || []
  } catch (e) {
    innovToast('error', 'Calendário', e.response?.data?.message || 'Falha ao listar')
  } finally {
    loading.value = false
  }
}

async function criar() {
  saving.value = true
  try {
    const { data } = await api.post('/calendario/mensais', {
      ano: Number(form.value.ano),
      mes: Number(form.value.mes),
      titulo: form.value.titulo || null,
      copiar_anterior: true,
    })
    const created = data.data || data
    innovToast('success', 'Calendário', 'Rascunho criado')
    router.push(`/calendario/${created.id}`)
  } catch (e) {
    innovToast('error', 'Calendário', e.response?.data?.message || 'Falha ao criar')
  } finally {
    saving.value = false
  }
}

async function copiarProximo(m, e) {
  e?.stopPropagation?.()
  if (!canManage.value) return
  const dest = proximoMesCalendario(m.ano, m.mes)
  const ok = await innovConfirm({
    title: 'Copiar para o próximo mês',
    message: `Criar ${labelMesCalendario(dest.ano, dest.mes)} a partir de ${labelMesCalendario(m.ano, m.mes)}? Observações e celebrantes da grade serão copiados.`,
    confirmText: 'Copiar',
  })
  if (!ok) return
  busyId.value = m.id
  try {
    const { data } = await api.post(`/calendario/mensais/${m.id}/copiar-proximo`)
    const created = data.data || data
    innovToast('success', 'Calendário', `Rascunho de ${labelMesCalendario(created.ano, created.mes)} criado`)
    router.push(`/calendario/${created.id}`)
  } catch (err) {
    innovToast('error', 'Calendário', err.response?.data?.message || 'Falha ao copiar')
  } finally {
    busyId.value = ''
  }
}

async function excluir(m, e) {
  e?.stopPropagation?.()
  if (!canManage.value) return
  const ok = await innovConfirm({
    title: 'Excluir calendário',
    message: `Excluir ${labelMesCalendario(m.ano, m.mes)}? Esta ação não pode ser desfeita.`,
    confirmText: 'Excluir',
  })
  if (!ok) return
  busyId.value = m.id
  try {
    await api.delete(`/calendario/mensais/${m.id}`)
    mensais.value = mensais.value.filter((x) => x.id !== m.id)
    innovToast('success', 'Calendário', 'Excluído')
  } catch (err) {
    innovToast('error', 'Calendário', err.response?.data?.message || 'Falha ao excluir')
  } finally {
    busyId.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="calendario-lista">
    <header class="mb-8">
      <p
        class="text-[11px] font-medium tracking-wider uppercase mb-1"
        style="color: var(--color-accent-dark)"
      >
        Núcleo
      </p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Calendário oficial
      </h1>
      <p class="text-[14px] mt-1 max-w-xl" style="color: var(--color-muted)">
        Grade mensal de missas, celebrações e casamentos.
      </p>
    </header>

    <section
      v-if="canManage"
      class="card p-5 md:p-6 mb-8"
      data-testid="calendario-novo"
    >
      <h2 class="font-serif text-[18px] font-medium mb-4" style="color: var(--color-ink)">
        Abrir próximo mês
      </h2>
      <form class="flex flex-col gap-4" @submit.prevent="criar">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[8rem_12rem_minmax(0,1fr)]">
          <div>
            <label class="fld" for="cal-ano">Ano</label>
            <input
              id="cal-ano"
              v-model.number="form.ano"
              type="number"
              min="2020"
              max="2100"
              class="input"
              required
            />
          </div>
          <div>
            <label class="fld" for="cal-mes">Mês</label>
            <select id="cal-mes" v-model.number="form.mes" class="input" required>
              <option v-for="m in mesOptions" :key="m.value" :value="m.value">
                {{ m.label }}
              </option>
            </select>
          </div>
          <div class="sm:col-span-2 lg:col-span-1">
            <label class="fld" for="cal-titulo">Título</label>
            <input
              id="cal-titulo"
              v-model="form.titulo"
              class="input"
              placeholder="Paróquia…"
            />
          </div>
        </div>
        <div class="flex justify-end pt-1">
          <button
            type="submit"
            class="btn btn-primary"
            data-testid="calendario-criar"
            :disabled="saving"
          >
            {{ saving ? 'Criando…' : 'Criar rascunho' }}
          </button>
        </div>
      </form>
    </section>

    <p v-if="loading" class="text-[14px]" style="color: var(--color-muted)">Carregando…</p>

    <ul
      v-else-if="mensais.length"
      class="flex flex-col gap-3 list-none p-0 m-0"
      data-testid="calendario-lista-itens"
    >
      <li v-for="m in mensais" :key="m.id" class="card px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            class="min-w-0 flex-1 text-left border-0 bg-transparent cursor-pointer p-0"
            data-testid="calendario-item"
            @click="router.push(`/calendario/${m.id}`)"
          >
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">
              {{ labelMesCalendario(m.ano, m.mes) }}
            </div>
            <div class="text-[13px] mt-0.5 truncate" style="color: var(--color-muted)">
              {{ m.titulo || 'Sem título' }}
            </div>
          </button>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <span
              class="text-[12px] font-medium px-2.5 py-1 rounded-full"
              style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
            >
              {{ labelStatusCalendario(m.status) }}
            </span>
            <template v-if="canManage">
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-xs"
                data-testid="calendario-copiar-proximo"
                :disabled="busyId === m.id"
                @click="copiarProximo(m, $event)"
              >
                Copiar p/ próximo
              </button>
              <button
                type="button"
                class="btn btn-ghost !py-1.5 !px-3 text-xs"
                style="color: var(--color-danger)"
                data-testid="calendario-excluir"
                :disabled="busyId === m.id"
                @click="excluir(m, $event)"
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
      data-testid="calendario-vazio"
    >
      <p class="font-serif text-[17px]" style="color: var(--color-ink)">Nenhum calendário ainda</p>
      <p class="text-[13.5px] mt-2 max-w-sm mx-auto" style="color: var(--color-muted)">
        {{
          canManage
            ? 'Crie o rascunho do próximo mês para iniciar a coleta e montar a grade oficial.'
            : 'Quando o responsável publicar um mês, ele aparecerá aqui.'
        }}
      </p>
    </div>
  </div>
</template>
