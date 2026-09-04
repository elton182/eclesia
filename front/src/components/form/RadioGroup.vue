<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: ''
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
    direction: {
        type: String,
        default: 'horizontal',
        validator: (value) => ['horizontal', 'vertical'].includes(value)
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref(props.modelValue);

// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = newValue;
});

// Observa mudanças no valor local e emite o evento para o componente pai
watch(localValue, (newValue) => {
    emit('update:modelValue', newValue);
});

// Gera um ID único para cada item de rádio baseado no nome
const getRadioId = (value) => `${props.name}-${value}`;

// Determina a classe de layout com base na direção escolhida
const layoutClass = computed(() => {
    return props.direction === 'vertical'
        ? 'flex flex-col gap-3'
        : 'flex flex-wrap gap-4';
});
</script>

<template>
    <div class="form-group">
        <span class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </span>

        <div :class="layoutClass">
            <div
                class="flex items-center"
                v-for="option in options"
                :key="option.value"
            >
                <input
                    type="radio"
                    :id="getRadioId(option.value)"
                    :name="name"
                    :value="option.value"
                    v-model="localValue"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600"
                    :disabled="disabled"
                    :required="required"
                >
                <label
                    :for="getRadioId(option.value)"
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