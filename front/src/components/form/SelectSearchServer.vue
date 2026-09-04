<template>
    <div class="form-group">
        <div class="flex justify-between mb-2">
            <label
                :for="name"
                class="text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                {{ label }}
                <span v-if="required" class="text-red-500">*</span>
            </label>
            <button
                v-if="localValue"
                @click.stop="clearSelection"
                type="button"
                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                :disabled="disabled"
            >
                {{ $t('common.clear') }}
            </button>
        </div>

        <div class="relative" ref="inputRef">
            <div class="flex">
                <div class="relative flex-grow">
                    <input
                        type="text"
                        :id="name"
                        :name="name"
                        v-model="searchTerm"
                        @focus="handleFocus"
                        @click="handleFocus"
                        @input="handleInput"
                        autocomplete="off"
                        class="bg-white-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :class="{
                            'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500': localValue
                        }"
                        :placeholder="localValue ? getLabel(localValue) : placeholder"
                        :disabled="disabled"
                        :required="required"
                    >
                    <div
                        v-if="localValue"
                        class="absolute inset-y-0 right-0 flex items-center pr-3"
                    >
                        <button
                            @click.stop="clearSelection"
                            type="button"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                            :disabled="disabled"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-5 h-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                    <div
                        v-if="loading"
                        class="absolute inset-y-0 right-0 flex items-center pr-3"
                    >
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                    </div>
                </div>
            </div>

            <div
                v-if="showDropdown"
                class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg rounded-md max-h-60 overflow-auto"
                @click.stop
            >
                <ul class="py-1">
                    <li
                        v-for="option in filteredOptions"
                        :key="option.value"
                        @mousedown="selectItem(option)"
                        class="px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                        :class="[
                            isItemSelected(option.value)
                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300 font-medium'
                                : 'text-gray-700 dark:text-gray-200'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <span>{{ isTranslationKey(option.label) ? $t(option.label) : option.label }}</span>
                            <svg
                                v-if="isItemSelected(option.value)"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-5 h-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.5 12.75l6 6 9-13.5"
                                />
                            </svg>
                        </div>
                    </li>
                    <li
                        v-if="filteredOptions.length === 0 && !loading"
                        class="px-3 py-2 text-gray-500 dark:text-gray-400"
                    >
                        {{ $t('form.noOptions') }}
                    </li>
                    <li
                        v-if="loading"
                        class="px-3 py-2 text-gray-500 dark:text-gray-400 text-center"
                    >
                        {{ $t('common.loading') }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Badge para seleção -->
        <div
            v-if="localValue"
            class="mt-2"
        >
            <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                {{ getLabel(localValue) }}
            </span>
        </div>

        <!-- Mensagem de erro -->
        <p
            v-if="errors"
            class="mt-1 text-sm text-red-600 dark:text-red-500"
        >
            {{ errors }}
        </p>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    options: {
        type: Array,
        required: true
    },
    label: {
        type: String,
        default: 'Selecione'
    },
    placeholder: {
        type: String,
        default: null
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
    },
    loading: {
        type: Boolean,
        default: false
    },
    searchFunction: {
        type: Function,
        required: true
    }
});

const emit = defineEmits(['update:modelValue']);

const searchTerm = ref('');
const showDropdown = ref(false);
const inputRef = ref(null);
let searchTimeout = null;

const translatedPlaceholder = computed(() => props.placeholder || t('form.searchPlaceholder'));

const localValue = computed({
    get: () => {
        return props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== ''
            ? props.modelValue
            : '';
    },
    set: (value) => {
        emit('update:modelValue', value);
    }
});

const isTranslationKey = (label) => {
    return typeof label === 'string' && label.includes('.') && !label.includes(' ');
};

const filteredOptions = computed(() => {
    if (!searchTerm.value) return props.options;

    const term = searchTerm.value.toLowerCase();
    return props.options.filter(option => {
        const labelToSearch = isTranslationKey(option.label)
            ? t(option.label)
            : option.label;
        return labelToSearch.toLowerCase().includes(term);
    });
});

const isItemSelected = (value) => {
    return localValue.value === value;
};

const getLabel = (value) => {
    const option = props.options.find(opt => opt.value === value);
    if (!option) return '';

    if (isTranslationKey(option.label)) {
        return t(option.label);
    }

    return option.label;
};

const handleFocus = () => {
    if (!props.disabled) {
        showDropdown.value = true;
    }
};

const handleInput = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        if (props.searchFunction && typeof props.searchFunction === 'function') {
            props.searchFunction(searchTerm.value);
        }
    }, 300);
};

const selectItem = (option) => {
    if (props.disabled) return;

    localValue.value = option.value;
    searchTerm.value = isTranslationKey(option.label)
        ? t(option.label)
        : option.label;
    showDropdown.value = false;
};

const clearSelection = () => {
    if (props.disabled) return;

    localValue.value = '';
    searchTerm.value = '';
};

const handleOutsideClick = (event) => {
    if (inputRef.value && !inputRef.value.contains(event.target)) {
        showDropdown.value = false;

        if (localValue.value) {
            searchTerm.value = getLabel(localValue.value);
        }
    }
};

watch(() => localValue.value, (newValue) => {
    if (newValue && newValue !== '') {
        searchTerm.value = getLabel(newValue);
    } else {
        searchTerm.value = '';
    }
});

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);

    if (localValue.value && localValue.value !== '') {
        searchTerm.value = getLabel(localValue.value);
    } else {
        searchTerm.value = '';
    }

    // Carregar dados iniciais
    if (props.searchFunction && typeof props.searchFunction === 'function') {
        props.searchFunction('');
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>
