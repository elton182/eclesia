<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'

const props = defineProps({
  alvoTipo: {
    type: String,
    required: true,
    validator: (v) => ['recado', 'reflexao'].includes(v),
  },
  alvoId: {
    type: String,
    required: true,
  },
})

const loading = ref(false)
const data = ref(null)

function formatWhen(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
  }
}

async function load() {
  if (!props.alvoId) return
  loading.value = true
  try {
    const path =
      props.alvoTipo === 'recado'
        ? `/ecc/recados/${props.alvoId}/engajamento`
        : `/ecc/reflexoes/${props.alvoId}/engajamento`
    const { data: res } = await api.get(path)
    data.value = res?.data || null
  } catch (e) {
    data.value = null
    innovToast('error', 'Engajamento', e.response?.data?.message || 'Falha ao carregar')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.alvoTipo, props.alvoId],
  () => load(),
)

onMounted(load)
</script>

<template>
  <section
    class="mt-4 rounded-[12px] border p-4"
    style="border-color: var(--color-line); background: var(--color-bg)"
    data-testid="ecc-engajamento"
  >
    <h4 class="text-[14px] font-medium mb-3" style="color: var(--color-ink)">Engajamento</h4>
    <p v-if="loading" class="text-[13px]" style="color: var(--color-muted)">Carregando…</p>
    <template v-else-if="data">
      <div class="flex flex-wrap gap-4 text-[13px] mb-4" style="color: var(--color-ink)">
        <span>{{ data.resumo?.leituras_count ?? 0 }} leituras</span>
        <span>{{ data.resumo?.likes_count ?? 0 }} curtidas</span>
        <span>{{ data.resumo?.comentarios_aprovados_count ?? 0 }} comentários</span>
      </div>
      <div class="grid gap-4 sm:grid-cols-3">
        <div>
          <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-muted)">Quem leu</p>
          <ul class="space-y-1.5 text-[12.5px]" style="color: var(--color-ink)">
            <li v-for="(row, i) in data.leituras || []" :key="`l-${i}`">
              {{ row.nome || `Usuário #${row.user_id}` }}
              <span style="color: var(--color-muted)"> · {{ formatWhen(row.lido_em) }}</span>
            </li>
            <li v-if="!(data.leituras || []).length" style="color: var(--color-muted)">Ninguém ainda</li>
          </ul>
        </div>
        <div>
          <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-muted)">Quem curtiu</p>
          <ul class="space-y-1.5 text-[12.5px]" style="color: var(--color-ink)">
            <li v-for="(row, i) in data.likes || []" :key="`k-${i}`">
              {{ row.nome || `Usuário #${row.user_id}` }}
            </li>
            <li v-if="!(data.likes || []).length" style="color: var(--color-muted)">Ninguém ainda</li>
          </ul>
        </div>
        <div>
          <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-muted)">Comentários</p>
          <ul class="space-y-1.5 text-[12.5px]" style="color: var(--color-ink)">
            <li v-for="row in data.comentarios || []" :key="row.id">
              <span class="font-medium">{{ row.nome || `Usuário #${row.user_id}` }}</span>
              <span style="color: var(--color-muted)"> ({{ row.status }})</span>
              — {{ row.corpo }}
            </li>
            <li v-if="!(data.comentarios || []).length" style="color: var(--color-muted)">Nenhum ainda</li>
          </ul>
        </div>
      </div>
    </template>
  </section>
</template>
