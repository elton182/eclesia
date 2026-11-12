<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import {
  acaoCadastroLabel,
  buildResumoCards,
  formatDateTimeBr,
  normalizeJanelaDias,
  statusAcessoLabel,
  statusAcessoTone,
} from '@/utils/eccCadastrosAcessos'

const router = useRouter()

const loading = ref(false)
const janelaDias = ref(30)
const relatorio = ref(null)

const cards = computed(() => buildResumoCards(relatorio.value?.resumo))
const equipes = computed(() => relatorio.value?.equipes_acessos || [])
const quemAtualizou = computed(() => relatorio.value?.quem_atualizou || [])
const atualizacoes = computed(() => relatorio.value?.atualizacoes_recentes || [])
const estagnados = computed(() => relatorio.value?.casais_estagnados || [])

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ecc/relatorios/cadastros-acessos', {
      params: { janela_dias: normalizeJanelaDias(janelaDias.value) },
    })
    relatorio.value = data?.data ?? data
  } catch (e) {
    innovToast('error', 'Relatório', e.response?.data?.message || 'Falha ao carregar relatório')
    relatorio.value = null
  } finally {
    loading.value = false
  }
}

function onJanelaBlur() {
  janelaDias.value = normalizeJanelaDias(janelaDias.value)
  load()
}

function toneStyle(tone) {
  if (tone === 'ok') {
    return { background: 'color-mix(in srgb, #2f6b4f 12%, var(--color-surface))', color: '#2f6b4f' }
  }
  if (tone === 'warn') {
    return { background: 'var(--color-accent-soft)', color: 'var(--color-accent-dark)' }
  }
  if (tone === 'danger') {
    return { background: 'color-mix(in srgb, var(--color-primary) 12%, var(--color-surface))', color: 'var(--color-primary)' }
  }
  return { background: 'color-mix(in srgb, var(--color-ink) 6%, var(--color-surface))', color: 'var(--color-muted)' }
}

function openCasal(id) {
  if (!id) return
  router.push(`/ecc/casais/${id}`)
}

onMounted(load)
</script>

<template>
  <div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full" data-testid="ecc-cadastros-acessos-page">
    <header class="mb-8">
      <p class="page-eyebrow">ECC</p>
      <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">
        Cadastros e acessos
      </h1>
      <p class="text-[14px] mt-1 max-w-2xl" style="color: var(--color-muted)">
        Quem atualizou as fichas dos casais, último acesso de cada equipe e o que está parado.
      </p>
    </header>

    <div class="flex flex-wrap items-end gap-3 mb-8">
      <label class="flex flex-col gap-1 text-[13px]" style="color: var(--color-muted)">
        Janela (dias)
        <input
          v-model.number="janelaDias"
          type="number"
          min="7"
          max="365"
          class="border rounded-lg px-3 py-1.5 text-sm w-28"
          style="border-color: var(--color-line); background: var(--color-surface); color: var(--color-ink)"
          data-testid="ecc-relatorio-janela"
          @blur="onJanelaBlur"
          @keyup.enter="onJanelaBlur"
        />
      </label>
      <button
        type="button"
        class="btn btn-primary"
        :disabled="loading"
        data-testid="ecc-relatorio-atualizar"
        @click="onJanelaBlur"
      >
        {{ loading ? 'Carregando…' : 'Atualizar' }}
      </button>
    </div>

    <section class="mb-10" data-testid="ecc-relatorio-resumo">
      <h2 class="font-serif text-[20px] font-normal mb-4" style="color: var(--color-ink)">Resumo</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div
          v-for="card in cards"
          :key="card.key"
          class="rounded-[var(--radius)] px-4 py-3"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
          :data-testid="`ecc-relatorio-card-${card.key}`"
        >
          <div class="text-[12px] uppercase tracking-wide" style="color: var(--color-muted)">
            {{ card.label }}
          </div>
          <div class="font-serif text-[28px] leading-tight mt-1" style="color: var(--color-ink)">
            {{ card.value }}
          </div>
          <div v-if="card.hint" class="text-[12px] mt-1" style="color: var(--color-muted)">
            {{ card.hint }}
          </div>
        </div>
      </div>
    </section>

    <section class="mb-10" data-testid="ecc-relatorio-equipes">
      <h2 class="font-serif text-[20px] font-normal mb-2" style="color: var(--color-ink)">
        Acessos por equipe
      </h2>
      <p class="text-[13px] mb-4" style="color: var(--color-muted)">
        Status com base no último login da conta compartilhada da equipe.
      </p>

      <div
        v-if="!loading && equipes.length === 0"
        class="text-[14px] py-6"
        style="color: var(--color-muted)"
      >
        Nenhuma equipe cadastrada.
      </div>

      <div class="overflow-x-auto" style="border: 1px solid var(--color-line); border-radius: var(--radius); background: var(--color-surface)">
        <table class="w-full text-left text-[13.5px]" data-testid="ecc-relatorio-equipes-table">
          <thead>
            <tr style="border-bottom: 1px solid var(--color-line); color: var(--color-muted)">
              <th class="px-4 py-3 font-medium">Equipe</th>
              <th class="px-4 py-3 font-medium">Casais</th>
              <th class="px-4 py-3 font-medium">Login</th>
              <th class="px-4 py-3 font-medium">Último acesso</th>
              <th class="px-4 py-3 font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="eq in equipes"
              :key="eq.id"
              style="border-bottom: 1px solid var(--color-line)"
            >
              <td class="px-4 py-3" style="color: var(--color-ink)">
                <span
                  v-if="eq.cor"
                  class="inline-block w-2.5 h-2.5 rounded-full mr-2 align-middle"
                  :style="{ background: eq.cor }"
                />
                {{ eq.nome }}
              </td>
              <td class="px-4 py-3" style="color: var(--color-ink)">{{ eq.casais_count }}</td>
              <td class="px-4 py-3 font-mono text-[12.5px]" style="color: var(--color-muted)">
                {{ eq.acesso_username || '—' }}
                <span
                  v-if="eq.must_change_password"
                  class="ml-1 text-[11px]"
                  style="color: var(--color-accent-dark)"
                >
                  (trocar senha)
                </span>
              </td>
              <td class="px-4 py-3" style="color: var(--color-ink)">
                {{ formatDateTimeBr(eq.ultimo_acesso_em) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-block text-[12px] px-2 py-0.5 rounded"
                  :style="toneStyle(statusAcessoTone(eq.status_acesso))"
                >
                  {{ statusAcessoLabel(eq.status_acesso) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-8 mb-10">
      <section data-testid="ecc-relatorio-quem-atualizou">
        <h2 class="font-serif text-[20px] font-normal mb-2" style="color: var(--color-ink)">
          Quem atualizou fichas
        </h2>
        <p class="text-[13px] mb-4" style="color: var(--color-muted)">
          Contagem de atualizações de casais na janela.
        </p>
        <ul v-if="quemAtualizou.length" class="space-y-2">
          <li
            v-for="(row, idx) in quemAtualizou"
            :key="row.ator_user_id || idx"
            class="flex items-baseline justify-between gap-3 px-3 py-2 rounded-[var(--radius)]"
            style="background: var(--color-surface); border: 1px solid var(--color-line)"
          >
            <div>
              <div style="color: var(--color-ink)">{{ row.ator_label || '—' }}</div>
              <div class="text-[12px]" style="color: var(--color-muted)">
                Última: {{ formatDateTimeBr(row.ultima_em) }}
              </div>
            </div>
            <div class="font-serif text-[22px]" style="color: var(--color-ink)">
              {{ row.atualizacoes_count }}
            </div>
          </li>
        </ul>
        <p v-else class="text-[14px]" style="color: var(--color-muted)">
          Nenhuma atualização de ficha nesta janela.
        </p>
      </section>

      <section data-testid="ecc-relatorio-atualizacoes">
        <h2 class="font-serif text-[20px] font-normal mb-2" style="color: var(--color-ink)">
          Atualizações recentes
        </h2>
        <p class="text-[13px] mb-4" style="color: var(--color-muted)">
          Últimos create/update registrados na auditoria.
        </p>
        <ul v-if="atualizacoes.length" class="space-y-2">
          <li
            v-for="(row, idx) in atualizacoes"
            :key="`${row.casal_id}-${row.em}-${idx}`"
            class="px-3 py-2 rounded-[var(--radius)]"
            style="background: var(--color-surface); border: 1px solid var(--color-line)"
          >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <button
                type="button"
                class="text-left border-0 bg-transparent p-0 cursor-pointer font-medium"
                style="color: var(--color-primary)"
                @click="openCasal(row.casal_id)"
              >
                {{ row.casal_label || row.casal_id || 'Casal' }}
              </button>
              <span class="text-[12px]" style="color: var(--color-muted)">
                {{ formatDateTimeBr(row.em) }}
              </span>
            </div>
            <div class="text-[13px] mt-0.5" style="color: var(--color-muted)">
              {{ acaoCadastroLabel(row.acao) }}
              ·
              {{ row.ator_label || '—' }}
            </div>
          </li>
        </ul>
        <p v-else class="text-[14px]" style="color: var(--color-muted)">
          Sem eventos recentes de cadastro.
        </p>
      </section>
    </div>

    <section class="mb-6" data-testid="ecc-relatorio-estagnados">
      <h2 class="font-serif text-[20px] font-normal mb-2" style="color: var(--color-ink)">
        Casais estagnados
      </h2>
      <p class="text-[13px] mb-4" style="color: var(--color-muted)">
        Fichas sem atualização dentro da janela selecionada.
      </p>

      <div
        v-if="estagnados.length === 0"
        class="text-[14px]"
        style="color: var(--color-muted)"
      >
        Nenhum casal estagnado nesta janela.
      </div>

      <div
        v-else
        class="overflow-x-auto"
        style="border: 1px solid var(--color-line); border-radius: var(--radius); background: var(--color-surface)"
      >
        <table class="w-full text-left text-[13.5px]">
          <thead>
            <tr style="border-bottom: 1px solid var(--color-line); color: var(--color-muted)">
              <th class="px-4 py-3 font-medium">Casal</th>
              <th class="px-4 py-3 font-medium">Equipe</th>
              <th class="px-4 py-3 font-medium">Última atualização</th>
              <th class="px-4 py-3 font-medium">Dias</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in estagnados"
              :key="c.id"
              style="border-bottom: 1px solid var(--color-line)"
            >
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="border-0 bg-transparent p-0 cursor-pointer"
                  style="color: var(--color-primary)"
                  @click="openCasal(c.id)"
                >
                  {{ c.label }}
                </button>
              </td>
              <td class="px-4 py-3" style="color: var(--color-ink)">{{ c.equipe_nome || '—' }}</td>
              <td class="px-4 py-3" style="color: var(--color-ink)">
                {{ formatDateTimeBr(c.atualizado_em) }}
              </td>
              <td class="px-4 py-3" style="color: var(--color-ink)">{{ c.dias_sem_atualizacao }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
