<script setup>
import { computed } from 'vue'
import {
  formatSaldo,
  itensExcedidos,
  setQuantidadeRecurso,
  splitPorTipo,
  toggleEspaco,
} from '@/utils/planejamentoRecursos'

const props = defineProps({
  /** @type {import('vue').PropType<{ espacoIds: string[], quantidades: Record<string, number> }>} */
  modelValue: { type: Object, required: true },
  /** @type {import('vue').PropType<Array<{ recurso_id: string, nome: string, tipo: string, quantidade: number, disponivel: number }>>} */
  disponibilidade: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  semData: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const grupos = computed(() => splitPorTipo(props.disponibilidade))
const excedidos = computed(() => new Set(itensExcedidos(props.modelValue, props.disponibilidade)))

function espacoSelecionado(id) {
  return props.modelValue.espacoIds.includes(id)
}

function onToggleEspaco(item) {
  if (!espacoSelecionado(item.recurso_id) && item.disponivel < 1) return
  emit('update:modelValue', toggleEspaco(props.modelValue, item.recurso_id))
}

function quantidade(id) {
  return props.modelValue.quantidades[id] || 0
}

function setQtd(item, valor) {
  emit('update:modelValue', setQuantidadeRecurso(props.modelValue, item.recurso_id, valor, item.quantidade))
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="planejamento-recursos-picker">
    <p v-if="semData" class="text-[12.5px]" style="color: var(--color-muted)">
      Informe a data para ver espaços e recursos livres.
    </p>
    <template v-else>
      <div>
        <span class="fld">
          Espaços
          <span v-if="loading" class="font-normal" style="color: var(--color-muted)">· verificando…</span>
        </span>
        <div class="flex flex-wrap gap-2 mt-1">
          <label
            v-for="item in grupos.espacos"
            :key="item.recurso_id"
            class="inline-flex items-center gap-1.5 text-[13px] px-2.5 py-1.5 rounded-lg"
            :class="item.disponivel < 1 && !espacoSelecionado(item.recurso_id) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
            :style="{
              border: `1px solid ${excedidos.has(item.recurso_id) ? 'var(--color-danger)' : 'var(--color-line)'}`,
            }"
            :data-testid="`planejamento-espaco-${item.recurso_id}`"
          >
            <input
              type="checkbox"
              :checked="espacoSelecionado(item.recurso_id)"
              :disabled="item.disponivel < 1 && !espacoSelecionado(item.recurso_id)"
              @change="onToggleEspaco(item)"
            />
            {{ item.nome }}
            <span
              class="text-[11px]"
              :style="{ color: item.disponivel < 1 ? 'var(--color-danger)' : 'var(--color-muted)' }"
            >
              · {{ formatSaldo(item) }}
            </span>
          </label>
          <span v-if="!grupos.espacos.length" class="text-[12.5px]" style="color: var(--color-muted)">
            Nenhum espaço cadastrado.
          </span>
        </div>
      </div>

      <div v-if="grupos.recursos.length">
        <span class="fld">Recursos</span>
        <ul class="list-none p-0 m-0 mt-1 flex flex-col gap-1.5">
          <li
            v-for="item in grupos.recursos"
            :key="item.recurso_id"
            class="flex items-center justify-between gap-3 text-[13px] px-2.5 py-1.5 rounded-lg"
            :style="{
              border: `1px solid ${excedidos.has(item.recurso_id) ? 'var(--color-danger)' : 'var(--color-line)'}`,
            }"
            :data-testid="`planejamento-recurso-${item.recurso_id}`"
          >
            <div class="min-w-0">
              <span style="color: var(--color-ink)">{{ item.nome }}</span>
              <span
                class="text-[11px] ml-1"
                :style="{ color: item.disponivel < 1 ? 'var(--color-danger)' : 'var(--color-muted)' }"
              >
                {{ formatSaldo(item) }}
              </span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                class="btn btn-ghost !px-2 !py-0.5 text-xs"
                :disabled="quantidade(item.recurso_id) < 1"
                :aria-label="`Diminuir ${item.nome}`"
                @click="setQtd(item, quantidade(item.recurso_id) - 1)"
              >
                −
              </button>
              <span class="w-6 text-center tabular-nums" data-testid="planejamento-recurso-qtd">
                {{ quantidade(item.recurso_id) }}
              </span>
              <button
                type="button"
                class="btn btn-ghost !px-2 !py-0.5 text-xs"
                :disabled="quantidade(item.recurso_id) >= item.disponivel"
                :aria-label="`Aumentar ${item.nome}`"
                @click="setQtd(item, quantidade(item.recurso_id) + 1)"
              >
                +
              </button>
            </div>
          </li>
        </ul>
      </div>

      <p
        v-if="excedidos.size"
        class="text-[12.5px]"
        style="color: var(--color-danger)"
        data-testid="planejamento-recursos-excedidos"
      >
        Há item selecionado sem disponibilidade neste horário — ajuste o horário ou a seleção.
      </p>
    </template>
  </div>
</template>
