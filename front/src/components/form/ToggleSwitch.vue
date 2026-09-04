<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Boolean, Number],
        default: false
    },
    label: {
        type: String,
        default: 'Ativo'
    },
    name: {
        type: String,
        required: false,
        default: ''
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
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref(!!props.modelValue);

// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = !!newValue;
});

// Observa mudanças no valor local e emite o evento para o componente pai
watch(localValue, (newValue) => {
    emit('update:modelValue', newValue);
});

// Função para alternar o valor
const toggleValue = () => {
    if (!props.disabled) {
        localValue.value = !localValue.value;
    }
};
</script>

<template>
    <div class="form-group">
        <div class="flex items-center">
            <div
                class="relative inline-flex items-center cursor-pointer"
                :class="{ 'opacity-70 cursor-not-allowed': disabled }"
                @click="toggleValue"
            >
                <input
                    type="checkbox"
                    :id="name"
                    :name="name"
                    v-model="localValue"
                    class="sr-only peer"
                    :disabled="disabled"
                    :required="required"
                    @change="toggleValue"
                >
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
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