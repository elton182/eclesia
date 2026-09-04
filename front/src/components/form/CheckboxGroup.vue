<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    options: {
        type: Array,
        required: true
    },
    name: {
        type: String,
        required: true
    },
    label: {
        type: String,
        default: 'Opções'
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
    inline: {
        type: Boolean,
        default: false
    },
    columns: {
        type: [Number, String],
        default: 3
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref([...props.modelValue]);

// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = [...newValue];
});

// Observa mudanças no valor local e emite o evento para o componente pai
watch(localValue, (newValue) => {
    emit('update:modelValue', [...newValue]);
});

// Gera um ID único para cada checkbox baseado no nome
const getCheckboxId = (value) => `${props.name}-${value}`;

// Define a classe de grid com base nas configurações
const gridClass = computed(() => {
    if (props.inline) {
        return 'flex flex-wrap gap-x-6 gap-y-3';
    } else {
        return `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${props.columns} gap-3`;
    }
});
</script>

<template>
    <div class="form-group">
        <span class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </span>

        <div :class="gridClass">
            <div
                class="flex items-center"
                v-for="option in options"
                :key="option.value"
            >
                <input
                    type="checkbox"
                    :id="getCheckboxId(option.value)"
                    :name="name"
                    :value="option.value"
                    v-model="localValue"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600"
                    :disabled="disabled"
                    :required="required"
                >
                <label
                    :for="getCheckboxId(option.value)"
                    class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300"
                >
                    {{ option.label }}
                </label>
            </div>
        </div>

        <p
            v-if="errors"
            class="mt-1 text-sm text-red-600 dark:text-red-500"
        >
            {{ errors }}
        </p>
    </div>
</template>