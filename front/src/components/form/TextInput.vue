<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    name: {
        type: String,
        required: false
    },
    placeholder: {
        type: String,
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
    },
    type: {
        type: String,
        default: 'text'
    },
    accept: {
        type: String,
        default: ''
    },
    mask: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref(props.modelValue);

// Função para aplicar máscara
const applyMask = (value, maskPattern) => {
    if (!maskPattern || !value) return value;

    // Remove tudo que não é número
    const numbers = value.toString().replace(/\D/g, '');

    // Máscaras predefinidas
    const masks = {
        'date': '99/99/9999',
        'datetime': '99/99/9999 99:99',
        'time': '99:99',
        'cpf': '999.999.999-99',
        'cnpj': '99.999.999/9999-99',
        'phone': '(99) 99999-9999',
        'cep': '99999-999'
    };

    const pattern = masks[maskPattern] || maskPattern;
    let maskedValue = '';
    let numberIndex = 0;

    for (let i = 0; i < pattern.length && numberIndex < numbers.length; i++) {
        if (pattern[i] === '9') {
            maskedValue += numbers[numberIndex];
            numberIndex++;
        } else {
            maskedValue += pattern[i];
        }
    }

    return maskedValue;
};

// Manipulador de entrada com máscara
const handleInput = (event) => {
    let value = event.target.value;

    if (props.mask) {
        value = applyMask(value, props.mask);
        event.target.value = value;
    }

    localValue.value = value;
};

// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = newValue;
});

// Observa mudanças no valor local e emite o evento para o componente pai
watch(localValue, (newValue) => {
    emit('update:modelValue', newValue);
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
        </label>
        <input
            :type="type"
            :id="name"
            :name="name"
            :value="localValue"
            @input="handleInput"
            class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
            :class="{ 'input': disabled }"
            :placeholder="placeholder"
            :disabled="disabled"
            :accept="accept"
        >
        <p
            v-if="errors"
            class="mt-1 text-sm text-red-600 dark:text-red-500"
        >
            {{ errors }}
        </p>
    </div>
</template>

<style scoped>
.input {
    background-color: #f0f0f0;
    color: #000;
}

</style>

