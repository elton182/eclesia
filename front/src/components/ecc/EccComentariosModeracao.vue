<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { labelStatusComentario } from '@/utils/eccInteracoes'

const items = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ecc/interacoes/comentarios', {
      params: { status: 'pendente' },
    })
    items.value = data?.data || []
  } catch (e) {
    items.value = []
    innovToast('error', 'Moderação', e.response?.data?.message || 'Falha ao carregar pendentes')
  } finally {
    loading.value = false
  }
}

async function aprovar(c) {
  await api.post(`/ecc/interacoes/comentarios/${c.id}/aprovar`)
  innovToast('success', 'Moderação', 'Comentário aprovado')
  await load()
}

async function rejeitar(c) {
  await api.post(`/ecc/interacoes/comentarios/${c.id}/rejeitar`, {
    motivo: 'Não adequado ao mural',
  })
  innovToast('success', 'Moderação', 'Comentário rejeitado')
  await load()
}

onMounted(load)
</script>

<template>
  <section
    v-if="loading || items.length"
    class="card p-4"
    data-testid="ecc-moderacao-comentarios"
  >
    <h2 class="font-serif text-[20px] font-normal" style="color: var(--color-ink)">
      Comentários para revisar
    </h2>
    <p class="text-[13px] mt-1 mb-3" style="color: var(--color-muted)">
      Aprove ou rejeite antes de aparecerem para todos.
    </p>
    <div v-if="loading" class="text-[13px]" style="color: var(--color-muted)">Carregando…</div>
    <ul v-else class="space-y-2">
      <li
        v-for="c in items"
        :key="c.id"
        class="rounded-[10px] px-3 py-2"
        style="background: var(--color-bg)"
        data-testid="ecc-moderacao-item"
      >
        <div class="text-[12px]" style="color: var(--color-muted)">
          {{ c.autor_nome || 'Participante' }}
          · {{ c.alvo_tipo }}
          · {{ labelStatusComentario(c.status) }}
        </div>
        <p class="text-[14px] mt-1" style="color: var(--color-ink)">{{ c.corpo }}</p>
        <div class="flex gap-2 mt-2">
          <button type="button" class="btn btn-primary text-sm" @click="aprovar(c)">Aprovar</button>
          <button type="button" class="btn btn-ghost text-sm" @click="rejeitar(c)">Rejeitar</button>
        </div>
      </li>
    </ul>
  </section>
</template>
