<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  filterMultiSelectOptions,
  multiSelectSummary,
  selectedOptionsFromValues,
  toggleMultiSelectValue,
} from '@/utils/multiSelect'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  /** @type {Array<string|number>} */
  modelValue: { type: Array, default: () => [] },
  /** @type {{ value: string|number, label: string }[]} */
  options: { type: Array, required: true },
  id: { type: String, default: '' },
  placeholder: { type: String, default: 'Pesquisar…' },
  emptyLabel: { type: String, default: 'Nenhuma opção selecionada' },
  singular: { type: String, default: 'selecionado' },
  plural: { type: String, default: 'selecionados' },
  noneSummary: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const term = ref('')
const root = ref(null)
const searchInput = ref(null)

const selected = computed(() =>
  selectedOptionsFromValues(props.options, props.modelValue),
)

const filtered = computed(() =>
  filterMultiSelectOptions(props.options, term.value),
)

const summary = computed(() =>
  multiSelectSummary(
    selected.value.length,
    props.singular,
    props.plural,
    props.noneSummary || props.emptyLabel,
  ),
)

const selectedSet = computed(() => new Set((props.modelValue || []).map(String)))

function isSelected(value) {
  return selectedSet.value.has(String(value))
}

async function openList() {
  if (props.disabled) return
  open.value = true
  term.value = ''
  await Promise.resolve()
  searchInput.value?.focus?.()
}

function closeList() {
  open.value = false
  term.value = ''
}

function toggleOption(option) {
  if (props.disabled) return
  const next = toggleMultiSelectValue(props.modelValue, option.value, !isSelected(option.value))
  // preserva ordem das options
  const order = new Map(props.options.map((o, i) => [String(o.value), i]))
  next.sort((a, b) => (order.get(String(a)) ?? 0) - (order.get(String(b)) ?? 0))
  emit('update:modelValue', next)
}

function removeChip(value, event) {
  event?.stopPropagation?.()
  if (props.disabled) return
  emit('update:modelValue', toggleMultiSelectValue(props.modelValue, value, false))
}

function clearAll(event) {
  event?.stopPropagation?.()
  if (props.disabled) return
  emit('update:modelValue', [])
}

function onOutside(event) {
  if (root.value && !root.value.contains(event.target)) {
    closeList()
  }
}

onMounted(() => document.addEventListener('mousedown', onOutside))
onUnmounted(() => document.removeEventListener('mousedown', onOutside))
</script>

<template>
  <div
    ref="root"
    class="relative"
    data-testid="multi-select-chips"
    v-bind="$attrs"
  >
    <button
      :id="id"
      type="button"
      class="input w-full text-left min-h-[42px] h-auto py-2 flex flex-wrap items-center gap-1.5"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      data-testid="multi-select-trigger"
      @click="open ? closeList() : openList()"
      @keydown.escape.prevent="closeList"
    >
      <template v-if="selected.length">
        <span
          v-for="opt in selected"
          :key="String(opt.value)"
          class="inline-flex items-center gap-1 max-w-full rounded-md px-2 py-0.5 text-[12.5px] font-medium"
          style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
          :data-testid="`multi-select-chip-${opt.value}`"
        >
          <span class="truncate">{{ opt.label }}</span>
          <span
            v-if="!disabled"
            role="button"
            tabindex="0"
            class="shrink-0 leading-none opacity-70 hover:opacity-100"
            :aria-label="`Remover ${opt.label}`"
            data-testid="multi-select-chip-remove"
            @click="removeChip(opt.value, $event)"
            @keydown.enter.prevent="removeChip(opt.value, $event)"
          >
            ×
          </span>
        </span>
      </template>
      <span
        v-else
        class="text-[13.5px]"
        style="color: var(--color-muted)"
      >
        {{ emptyLabel }}
      </span>
      <span class="ml-auto pl-2 text-[12px] shrink-0" style="color: var(--color-muted)">
        {{ open ? '▲' : '▼' }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg"
      style="background: var(--color-surface); border: 1px solid var(--color-line); box-shadow: 0 8px 24px rgba(42, 20, 24, 0.12)"
      data-testid="multi-select-panel"
    >
      <div class="p-2" style="border-bottom: 1px solid var(--color-line)">
        <input
          ref="searchInput"
          v-model="term"
          type="search"
          class="input w-full"
          autocomplete="off"
          :placeholder="placeholder"
          data-testid="multi-select-search"
          @keydown.escape.prevent="closeList"
        >
      </div>

      <div
        class="flex items-center justify-between gap-2 px-3 py-2 text-[12px]"
        style="color: var(--color-muted); border-bottom: 1px solid var(--color-line)"
      >
        <span data-testid="multi-select-summary">{{ summary }}</span>
        <button
          v-if="selected.length && !disabled"
          type="button"
          class="text-[12px] font-medium underline-offset-2 hover:underline"
          style="color: var(--color-accent-dark)"
          data-testid="multi-select-clear"
          @click="clearAll"
        >
          Limpar
        </button>
      </div>

      <ul
        class="max-h-56 overflow-auto py-1 list-none m-0 p-0"
        role="listbox"
        aria-multiselectable="true"
        data-testid="multi-select-list"
      >
        <li
          v-for="opt in filtered"
          :key="String(opt.value)"
          role="option"
          class="px-3 py-2 text-[14px] cursor-pointer flex items-center gap-2.5"
          :style="{
            color: 'var(--color-ink)',
            background: isSelected(opt.value) ? 'var(--color-accent-soft)' : 'transparent',
          }"
          :aria-selected="isSelected(opt.value)"
          :data-testid="`multi-select-option-${opt.value}`"
          @mousedown.prevent="toggleOption(opt)"
        >
          <span
            class="inline-flex h-4 w-4 items-center justify-center rounded border shrink-0 text-[11px] leading-none"
            :style="{
              borderColor: isSelected(opt.value) ? 'var(--color-primary-soft)' : 'var(--color-line)',
              background: isSelected(opt.value) ? 'var(--color-primary-soft)' : 'transparent',
              color: isSelected(opt.value) ? '#fff' : 'transparent',
            }"
            aria-hidden="true"
          >
            ✓
          </span>
          <span class="min-w-0 truncate">{{ opt.label }}</span>
        </li>
        <li
          v-if="!filtered.length"
          class="px-3 py-2 text-[13px]"
          style="color: var(--color-muted)"
        >
          Nenhuma opção
        </li>
      </ul>
    </div>
  </div>
</template>
