<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0
    },
    label: {
        type: String,
        default: 'Avaliação'
    },
    name: {
        type: String,
        required: true
    },
    min: {
        type: [Number, String],
        default: 0
    },
    max: {
        type: [Number, String],
        default: 10
    },
    step: {
        type: [Number, String],
        default: 1
    },
    disabled: {
        type: Boolean,
        default: false
    },
    required: {
        type: Boolean,
        default: false
    },
    errors: {
        type: String,
        default: ''
    },
    showMarkers: {
        type: Boolean,
        default: true
    },
    markers: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref(Number(props.modelValue));

// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = Number(newValue);
});

// Observa mudanças no valor local e emite o evento para o componente pai
watch(localValue, (newValue) => {
    emit('update:modelValue', Number(newValue));
});

// Calcula os marcadores automáticos se não forem fornecidos
const displayMarkers = computed(() => {
    if (props.markers && props.markers.length > 0) {
        return props.markers;
    }

    // Marcadores padrão (min, meio, max)
    const min = Number(props.min);
    const max = Number(props.max);
    const middle = Math.floor((min + max) / 2);

    return [min, middle, max];
});
</script>

<template>
    <div class="form-group">
        <label
            :for="name"
            class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
        >
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
            <span class="font-bold ml-1">{{ localValue }}</span>
        </label>
        <input
            type="range"
            :id="name"
            :name="name"
            v-model="localValue"
            :min="min"
            :max="max"
            :step="step"
            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            :disabled="disabled"
            :required="required"
        >
        <div v-if="showMarkers" class="flex justify-between text-xs text-gray-500 dark:text-gray-400 px-1 mt-1">
            <span v-for="marker in displayMarkers" :key="marker">{{ marker }}</span>
        </div>
        <p
            v-if="errors"
            class="mt-1 text-sm text-red-600 dark:text-red-500"
        >
            {{ errors }}
        </p>
    </div>
</template>