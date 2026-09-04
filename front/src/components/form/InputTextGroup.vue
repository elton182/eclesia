<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: 'E-mail'
    },
    name: {
        type: String,
        default: 'email'
    },
    placeholder: {
        type: String,
        default: 'seu@email.com'
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
    icon: {
        type: String,
    }
});

const emit = defineEmits(['update:modelValue']);

// Valor local que será sincronizado com o v-model do componente pai
// Valor local que será sincronizado com o v-model do componente pai
const localValue = ref(props.modelValue);

// Observa mudanças no valor do prop modelValue
// Observa mudanças no valor do prop modelValue
watch(() => props.modelValue, (newValue) => {
    localValue.value = newValue;
});

// Observa mudanças no valor local e emite o evento para o componente pai
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
        <div class="relative">
<!--            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none" v-html="icon">-->
<!--                &lt;!&ndash; O ícone SVG será inserido aqui via v-html &ndash;&gt;-->
<!--            </div>-->
            <input
                type="email"
                :id="name"
                :name="name"
                v-model="localValue"
                class="bg-white-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
            >
        </div>
        <p
            v-if="errors"
            class="mt-1 text-sm text-red-600 dark:text-red-500"
        >
            {{ errors }}
        </p>
    </div>
</template>