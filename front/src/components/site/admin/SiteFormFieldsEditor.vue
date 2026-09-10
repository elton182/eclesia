<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowDown, faArrowUp, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons'
import { FIELD_TYPES, createField, slugifyFieldName } from '@/utils/siteForms'

library.add(faArrowDown, faArrowUp, faPlus, faTrash)

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const update = (fields) => emit('update:modelValue', fields.map((f, i) => ({ ...f, ordem: i })))

const add = () => update([...props.modelValue, createField('text', props.modelValue.length)])

const remove = (index) => update(props.modelValue.filter((_, i) => i !== index))

const move = (index, delta) => {
  const target = index + delta
  if (target < 0 || target >= props.modelValue.length) return
  const fields = [...props.modelValue]
  ;[fields[index], fields[target]] = [fields[target], fields[index]]
  update(fields)
}

const patch = (index, changes) =>
  update(props.modelValue.map((field, i) => (i === index ? { ...field, ...changes } : field)))

const setLabel = (index, label) => {
  const field = props.modelValue[index]
  const derivado = slugifyFieldName(field.label)
  // Mantém o nome sincronizado enquanto o usuário não o edita manualmente.
  const changes = { label }
  if (!field.nome || field.nome === derivado) changes.nome = slugifyFieldName(label)
  patch(index, changes)
}

const setOpcoes = (index, raw) =>
  patch(index, { opcoes: raw.split('\n').map((o) => o.trim()).filter(Boolean) })
</script>

<template>
  <div class="space-y-3" data-testid="site-form-fields-editor">
    <div
      v-for="(field, index) in modelValue"
      :key="index"
      class="rounded-xl p-4 space-y-3"
      style="background: var(--color-surface-2)"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-muted)">
          Campo {{ index + 1 }}
        </span>
        <div class="flex gap-1">
          <button
            type="button"
            class="h-8 w-8 rounded-lg text-xs"
            style="color: var(--color-muted)"
            :class="index === 0 ? 'opacity-30' : 'hover:bg-black/5'"
            :disabled="index === 0"
            aria-label="Mover campo para cima"
            @click="move(index, -1)"
          >
            <FontAwesomeIcon :icon="faArrowUp" />
          </button>
          <button
            type="button"
            class="h-8 w-8 rounded-lg text-xs"
            style="color: var(--color-muted)"
            :class="index === modelValue.length - 1 ? 'opacity-30' : 'hover:bg-black/5'"
            :disabled="index === modelValue.length - 1"
            aria-label="Mover campo para baixo"
            @click="move(index, 1)"
          >
            <FontAwesomeIcon :icon="faArrowDown" />
          </button>
          <button
            type="button"
            class="h-8 w-8 rounded-lg text-xs hover:bg-red-50"
            style="color: var(--color-danger)"
            aria-label="Remover campo"
            :data-testid="`campo-remover-${index}`"
            @click="remove(index)"
          >
            <FontAwesomeIcon :icon="faTrash" />
          </button>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="fld" :for="`campo-label-${index}`">Rótulo</label>
          <input
            :id="`campo-label-${index}`"
            :value="field.label"
            class="input"
            placeholder="Nome completo"
            :data-testid="`campo-label-${index}`"
            @input="setLabel(index, $event.target.value)"
          />
        </div>
        <div>
          <label class="fld" :for="`campo-tipo-${index}`">Tipo</label>
          <select
            :id="`campo-tipo-${index}`"
            :value="field.tipo"
            class="input"
            @change="patch(index, { tipo: $event.target.value })"
          >
            <option v-for="t in FIELD_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
      </div>

      <div v-if="field.tipo === 'select'">
        <label class="fld" :for="`campo-opcoes-${index}`">Opções (uma por linha)</label>
        <textarea
          :id="`campo-opcoes-${index}`"
          :value="(field.opcoes || []).join('\n')"
          class="input text-[13px]"
          rows="3"
          @input="setOpcoes(index, $event.target.value)"
        />
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            :checked="field.obrigatorio"
            @change="patch(index, { obrigatorio: $event.target.checked })"
          />
          Preenchimento obrigatório
        </label>
        <span class="text-xs font-mono" style="color: var(--color-muted)">
          {{ field.nome || slugifyFieldName(field.label) || '—' }}
        </span>
      </div>
    </div>

    <button type="button" class="btn btn-ghost" data-testid="campo-adicionar" @click="add">
      <FontAwesomeIcon :icon="faPlus" />
      Adicionar campo
    </button>
  </div>
</template>
