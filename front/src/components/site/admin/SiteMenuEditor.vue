<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowDown, faArrowUp, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons'
import { createMenuItem } from '@/utils/siteBlocks'

library.add(faArrowDown, faArrowUp, faPlus, faTrash)

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  pages: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const update = (items) => emit('update:modelValue', items)

const add = () => update([...props.modelValue, createMenuItem()])

const remove = (index) => update(props.modelValue.filter((_, i) => i !== index))

const move = (index, delta) => {
  const target = index + delta
  if (target < 0 || target >= props.modelValue.length) return
  const items = [...props.modelValue]
  ;[items[index], items[target]] = [items[target], items[index]]
  update(items)
}

const patch = (index, changes) =>
  update(props.modelValue.map((item, i) => (i === index ? { ...item, ...changes } : item)))
</script>

<template>
  <div class="space-y-3" data-testid="site-menu-editor">
    <p
      v-if="!modelValue.length"
      class="text-[13px] rounded-xl px-3.5 py-3"
      style="background: var(--color-surface-2); color: var(--color-muted)"
    >
      Sem itens de menu: o site mostra apenas o link “Início”.
    </p>

    <div
      v-for="(item, index) in modelValue"
      :key="index"
      class="rounded-xl p-3 grid gap-3 sm:grid-cols-[1fr_auto]"
      style="background: var(--color-surface-2)"
    >
      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label class="fld" :for="`menu-label-${index}`">Rótulo</label>
          <input
            :id="`menu-label-${index}`"
            :value="item.label"
            class="input"
            placeholder="Início"
            :data-testid="`menu-label-${index}`"
            @input="patch(index, { label: $event.target.value })"
          />
        </div>
        <div>
          <label class="fld" :for="`menu-tipo-${index}`">Destino</label>
          <select
            :id="`menu-tipo-${index}`"
            :value="item.tipo"
            class="input"
            @change="patch(index, { tipo: $event.target.value })"
          >
            <option value="pagina">Página do site</option>
            <option value="link">Link externo</option>
          </select>
        </div>
        <div v-if="item.tipo === 'link'">
          <label class="fld" :for="`menu-href-${index}`">URL</label>
          <input
            :id="`menu-href-${index}`"
            :value="item.href"
            class="input"
            placeholder="https://…"
            @input="patch(index, { href: $event.target.value })"
          />
        </div>
        <div v-else>
          <label class="fld" :for="`menu-slug-${index}`">Página</label>
          <select
            :id="`menu-slug-${index}`"
            :value="item.slug"
            class="input"
            @change="patch(index, { slug: $event.target.value })"
          >
            <option value="">Selecione…</option>
            <option value="home">Página inicial</option>
            <option v-for="p in pages" :key="p.id" :value="p.slug">
              {{ p.titulo }} (/{{ p.slug }})
            </option>
          </select>
        </div>
      </div>

      <div class="flex sm:flex-col gap-1 sm:self-end sm:pb-0.5">
        <button
          type="button"
          class="h-8 w-8 rounded-lg text-xs"
          style="color: var(--color-muted)"
          :disabled="index === 0"
          :class="index === 0 ? 'opacity-30' : 'hover:bg-black/5'"
          aria-label="Mover item para cima"
          @click="move(index, -1)"
        >
          <FontAwesomeIcon :icon="faArrowUp" />
        </button>
        <button
          type="button"
          class="h-8 w-8 rounded-lg text-xs"
          style="color: var(--color-muted)"
          :disabled="index === modelValue.length - 1"
          :class="index === modelValue.length - 1 ? 'opacity-30' : 'hover:bg-black/5'"
          aria-label="Mover item para baixo"
          @click="move(index, 1)"
        >
          <FontAwesomeIcon :icon="faArrowDown" />
        </button>
        <button
          type="button"
          class="h-8 w-8 rounded-lg text-xs hover:bg-red-50"
          style="color: var(--color-danger)"
          aria-label="Remover item do menu"
          :data-testid="`menu-remover-${index}`"
          @click="remove(index)"
        >
          <FontAwesomeIcon :icon="faTrash" />
        </button>
      </div>
    </div>

    <button type="button" class="btn btn-ghost" data-testid="menu-adicionar" @click="add">
      <FontAwesomeIcon :icon="faPlus" />
      Adicionar item
    </button>
  </div>
</template>
