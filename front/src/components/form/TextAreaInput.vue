<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';

import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: null
    },
    name: {
        type: String,
        required: true
    },
    placeholder: {
        type: String,
        default: null
    },
    rows: {
        type: Number,
        default: 4
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
const localValue = ref(props.modelValue);

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
            {{ label || t('form.message') }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <textarea
            :id="name"
            :name="name"
            v-model="localValue"
            :rows="rows"
            class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
            :placeholder="placeholder || t('form.messagePlaceholder')"
            :disabled="disabled"
            :required="required"
            :class="{ 'input': disabled }"
        ></textarea>
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

