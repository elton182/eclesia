<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { filterOptionsByLabel } from '@/utils/selectSearchable'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  /** @type {{ value: string|number, label: string }[]} */
  options: { type: Array, required: true },
  id: { type: String, default: '' },
  placeholder: { type: String, default: 'Pesquisar…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const term = ref('')
const root = ref(null)

const selectedLabel = computed(() => {
  const opt = props.options.find((o) => String(o.value) === String(props.modelValue))
  return opt?.label || ''
})

const filtered = computed(() =>
  filterOptionsByLabel(props.options, term.value, selectedLabel.value),
)

watch(
  () => [props.modelValue, props.options],
  () => {
    if (!open.value) term.value = selectedLabel.value
  },
  { immediate: true, deep: true },
)

function openList() {
  if (props.disabled) return
  open.value = true
  if (term.value === selectedLabel.value) term.value = ''
}

function pick(option) {
  if (props.disabled) return
  emit('update:modelValue', option.value)
  term.value = option.label
  open.value = false
}

function closeList() {
  open.value = false
  term.value = selectedLabel.value
}

function onBlurTerm() {
  // delay so mousedown on option runs first
  setTimeout(() => {
    if (!open.value) return
    closeList()
  }, 120)
}

function onOutside(event) {
  if (root.value && !root.value.contains(event.target)) {
    open.value = false
    term.value = selectedLabel.value
  }
}

onMounted(() => document.addEventListener('mousedown', onOutside))
onUnmounted(() => document.removeEventListener('mousedown', onOutside))
</script>

<template>
  <div ref="root" class="relative" data-testid="select-searchable">
    <input
      :id="id"
      v-model="term"
      type="search"
      class="input"
      autocomplete="off"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-expanded="open"
      aria-autocomplete="list"
      role="combobox"
      v-bind="$attrs"
      @focus="openList"
      @click="openList"
      @blur="onBlurTerm"
      @keydown.escape.prevent="closeList"
    />
    <ul
      v-if="open"
      class="absolute z-20 mt-1 w-full max-h-56 overflow-auto rounded-lg py-1 list-none m-0 p-0"
      style="background: var(--color-surface); border: 1px solid var(--color-line); box-shadow: 0 8px 24px rgba(42, 20, 24, 0.12)"
      role="listbox"
      data-testid="select-searchable-list"
    >
      <li
        v-for="opt in filtered"
        :key="String(opt.value)"
        role="option"
        class="px-3 py-2 text-[14px] cursor-pointer"
        :style="{
          color: 'var(--color-ink)',
          background:
            String(opt.value) === String(modelValue) ? 'var(--color-accent-soft)' : 'transparent',
        }"
        :aria-selected="String(opt.value) === String(modelValue)"
        data-testid="select-searchable-option"
        @mousedown.prevent="pick(opt)"
      >
        {{ opt.label }}
      </li>
      <li
        v-if="!filtered.length"
        class="px-3 py-2 text-[13px]"
        style="color: var(--color-muted)"
      >
        Nenhuma opção
      </li>
    </ul>
    <!-- valor real para validação nativa do form -->
    <input
      v-if="required"
      type="text"
      tabindex="-1"
      aria-hidden="true"
      :value="modelValue"
      required
      :disabled="disabled"
      style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0"
      @focus="openList"
    />
  </div>
</template>
