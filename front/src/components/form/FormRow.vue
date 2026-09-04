<template>
    <div class="grid gap-x-4 gap-y-4 mb-4" :class="gridClasses">
        <slot />
    </div>
</template>

<script setup>
import { computed, defineProps } from 'vue';

const props = defineProps({
    columns: {
        type: Number,
        default: 12
    },
});

const gridClasses = computed(() => `grid-cols-${props.columns}`);
</script>
