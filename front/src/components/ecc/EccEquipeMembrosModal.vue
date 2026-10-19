<script setup>
import { computed, ref, watch } from 'vue'
import InnovModal from '@/components/base/InnovModal.vue'
import { casalEle, casalEla } from '@/utils/casalDisplay'
import {
  addCasaisToEquipe,
  removeCasaisFromEquipe,
  initialEquipeCasalIds,
} from '@/utils/eccEquipeMembros'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  equipe: { type: Object, default: null },
  casais: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const saving = ref(false)
const buscaDisponiveis = ref('')
const buscaMembros = ref('')
/** @type {import('vue').Ref<Set<string>>} */
const naEquipeIds = ref(new Set())
/** @type {import('vue').Ref<Set<string>>} */
const selectedLeft = ref(new Set())
/** @type {import('vue').Ref<Set<string>>} */
const selectedRight = ref(new Set())

const equipeId = computed(() => (props.equipe ? String(props.equipe.id) : ''))

const labelCasal = (c) => `${casalEle(c)} & ${casalEla(c)}`

watch(
  () => [props.modelValue, props.equipe?.id, props.casais],
  () => {
    if (!props.modelValue || !props.equipe) return
    naEquipeIds.value = initialEquipeCasalIds(props.casais, equipeId.value)
    selectedLeft.value = new Set()
    selectedRight.value = new Set()
    buscaDisponiveis.value = ''
    buscaMembros.value = ''
  },
  { immediate: true, deep: true },
)

const disponiveis = computed(() => {
  const q = buscaDisponiveis.value.trim().toLocaleLowerCase('pt-BR')
  return props.casais
    .filter((c) => !naEquipeIds.value.has(String(c.id)))
    .filter((c) => {
      if (!q) return true
      return labelCasal(c).toLocaleLowerCase('pt-BR').includes(q)
    })
})

const membros = computed(() => {
  const q = buscaMembros.value.trim().toLocaleLowerCase('pt-BR')
  return props.casais
    .filter((c) => naEquipeIds.value.has(String(c.id)))
    .filter((c) => {
      if (!q) return true
      return labelCasal(c).toLocaleLowerCase('pt-BR').includes(q)
    })
})

const toggle = (setRef, id) => {
  const next = new Set(setRef.value)
  const key = String(id)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  setRef.value = next
}

const moveToEquipe = () => {
  if (!selectedLeft.value.size) return
  naEquipeIds.value = addCasaisToEquipe(naEquipeIds.value, selectedLeft.value)
  selectedLeft.value = new Set()
}

const moveFromEquipe = () => {
  if (!selectedRight.value.size) return
  naEquipeIds.value = removeCasaisFromEquipe(naEquipeIds.value, selectedRight.value)
  selectedRight.value = new Set()
}

const close = () => emit('update:modelValue', false)

const save = async () => {
  if (!props.equipe) return
  saving.value = true
  try {
    await api.put(`/ecc/equipes/${props.equipe.id}/casais`, {
      casal_ids: Array.from(naEquipeIds.value),
    })
    innovToast('success', 'OK', 'Membros da equipe atualizados')
    emit('saved')
    close()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar membros')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <InnovModal
    :model-value="modelValue"
    size="4xl"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="space-y-4" data-testid="equipe-membros-modal">
      <div>
        <h3 class="font-serif text-xl" style="color: #2A1418">
          Membros — {{ equipe?.nome || 'Equipe' }}
        </h3>
        <p class="text-[13px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
          Selecione casais e use as setas para adicionar ou remover da equipe.
        </p>
      </div>

      <div class="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-stretch">
        <!-- Disponíveis -->
        <div
          class="rounded-[11px] overflow-hidden flex flex-col min-h-[280px]"
          style="border: 1px solid rgba(42, 20, 24, 0.1); background: #FFFDFA"
        >
          <div class="px-3 py-2.5" style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)">
            <div class="text-[12.5px] font-medium mb-1.5" style="color: #2A1418">
              Disponíveis ({{ disponiveis.length }})
            </div>
            <input
              v-model="buscaDisponiveis"
              type="search"
              class="input w-full text-[13px]"
              placeholder="Buscar…"
              data-testid="membros-busca-disponiveis"
              aria-label="Buscar casais disponíveis"
            >
          </div>
          <ul class="flex-1 overflow-y-auto max-h-72 p-2 space-y-1" data-testid="membros-lista-disponiveis">
            <li
              v-for="c in disponiveis"
              :key="c.id"
            >
              <label
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg cursor-pointer text-[13px]"
                :style="{
                  background: selectedLeft.has(String(c.id)) ? 'rgba(107, 28, 43, 0.08)' : 'transparent',
                  color: '#2A1418',
                }"
              >
                <input
                  type="checkbox"
                  class="shrink-0"
                  :checked="selectedLeft.has(String(c.id))"
                  :data-testid="`membros-disp-${c.id}`"
                  @change="toggle(selectedLeft, c.id)"
                >
                <span class="truncate">{{ labelCasal(c) }}</span>
              </label>
            </li>
            <li
              v-if="!disponiveis.length"
              class="px-3 py-6 text-center text-[13px]"
              style="color: rgba(42, 20, 24, 0.45)"
            >
              Nenhum casal disponível
            </li>
          </ul>
        </div>

        <!-- Setas -->
        <div class="flex md:flex-col items-center justify-center gap-2 py-2">
          <button
            type="button"
            class="btn btn-ghost"
            data-testid="membros-add"
            :disabled="!selectedLeft.size"
            title="Adicionar à equipe"
            @click="moveToEquipe"
          >
            →
          </button>
          <button
            type="button"
            class="btn btn-ghost"
            data-testid="membros-remove"
            :disabled="!selectedRight.size"
            title="Remover da equipe"
            @click="moveFromEquipe"
          >
            ←
          </button>
        </div>

        <!-- Na equipe -->
        <div
          class="rounded-[11px] overflow-hidden flex flex-col min-h-[280px]"
          style="border: 1px solid rgba(42, 20, 24, 0.1); background: #FFFDFA"
        >
          <div class="px-3 py-2.5" style="border-bottom: 1px solid rgba(42, 20, 24, 0.08)">
            <div class="text-[12.5px] font-medium mb-1.5" style="color: #2A1418">
              Na equipe ({{ membros.length }})
            </div>
            <input
              v-model="buscaMembros"
              type="search"
              class="input w-full text-[13px]"
              placeholder="Buscar…"
              data-testid="membros-busca-equipe"
              aria-label="Buscar casais na equipe"
            >
          </div>
          <ul class="flex-1 overflow-y-auto max-h-72 p-2 space-y-1" data-testid="membros-lista-equipe">
            <li
              v-for="c in membros"
              :key="c.id"
            >
              <label
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg cursor-pointer text-[13px]"
                :style="{
                  background: selectedRight.has(String(c.id)) ? 'rgba(107, 28, 43, 0.08)' : 'transparent',
                  color: '#2A1418',
                }"
              >
                <input
                  type="checkbox"
                  class="shrink-0"
                  :checked="selectedRight.has(String(c.id))"
                  :data-testid="`membros-eq-${c.id}`"
                  @change="toggle(selectedRight, c.id)"
                >
                <span class="truncate">{{ labelCasal(c) }}</span>
              </label>
            </li>
            <li
              v-if="!membros.length"
              class="px-3 py-6 text-center text-[13px]"
              style="color: rgba(42, 20, 24, 0.45)"
            >
              Nenhum casal nesta equipe
            </li>
          </ul>
        </div>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end pt-1">
        <button type="button" class="btn btn-ghost w-full sm:w-auto" data-testid="membros-cancelar" @click="close">
          Cancelar
        </button>
        <button
          type="button"
          class="btn btn-primary w-full sm:w-auto"
          data-testid="membros-salvar"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Salvando…' : 'Salvar' }}
        </button>
      </div>
    </div>
  </InnovModal>
</template>
