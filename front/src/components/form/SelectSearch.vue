<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Props do componente
const props = defineProps({
    modelValue: {
        type: [String, Array, Number],
        default: () => []
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
    multiple: {
        type: Boolean,
        default: false
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

// Emits para comunicação com o componente pai
// Emits para comunicação com o componente pai
const emit = defineEmits(['update:modelValue']);

// Estado interno do componente
// Estado interno do componente
const searchTerm = ref('');
const showDropdown = ref(false);
const inputRef = ref(null);

// Determina se o componente está no modo múltiplo ou único
// Determina se o componente está no modo múltiplo ou único
const isMultipleMode = computed(() => props.multiple);

// Placeholder traduzido
const translatedPlaceholder = computed(() => props.placeholder || t('form.searchPlaceholder'));

// Valor local reativo que será sincronizado com o modelValue
// Valor local reativo que será sincronizado com o modelValue
const localValue = computed({
    get: () => {
        if (isMultipleMode.value) {
            return Array.isArray(props.modelValue) && props.modelValue.length > 0 ? props.modelValue : [];
        } else {
            return props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '' && !Array.isArray(props.modelValue)
                ? props.modelValue
                : '';
        }
    },
    set: (value) => {
        emit('update:modelValue', value);
    }
});

// Verifica se o label é uma chave de tradução (contém ponto e não contém espaços)
const isTranslationKey = (label) => {
    return typeof label === 'string' && label.includes('.') && !label.includes(' ');
};

// Filtra as opções com base no termo de pesquisa
// Filtra as opções com base no termo de pesquisa
const filteredOptions = computed(() => {
    if (!searchTerm.value) return props.options;

    const term = searchTerm.value.toLowerCase();
    return props.options.filter(option => {
        // Se o label é uma chave de tradução, pesquisa no valor traduzido
        const labelToSearch = isTranslationKey(option.label)
            ? t(option.label)
            : option.label;
        return labelToSearch.toLowerCase().includes(term);
    });
});

// Verifica se um item está selecionado (para modo múltiplo)
// Verifica se um item está selecionado (para modo múltiplo)
const isItemSelected = (value) => {
    if (isMultipleMode.value) {
        return localValue.value.includes(value);
    } else {
        return localValue.value === value;
    }
};

// Pega a label para um determinado valor
// Pega a label para um determinado valor
const getLabel = (value) => {
    const option = props.options.find(opt => opt.value === value);
    if (!option) return '';

    // Se o label parece uma chave de tradução (ex: 'users.active'), traduz
    if (isTranslationKey(option.label)) {
        return t(option.label);
    }

    return option.label;
};

// Mostra o dropdown de opções
// Mostra o dropdown de opções
const handleFocus = () => {
    if (!props.disabled) {
        showDropdown.value = true;
    }
};

// Seleciona uma opção (para seleção única)
// Seleciona uma opção (para seleção única)
const selectItem = (option) => {
    if (props.disabled) return;

    if (isMultipleMode.value) {
        toggleItem(option);
    } else {
        localValue.value = option.value;
        // Se o label é uma chave de tradução, usa o valor traduzido
        searchTerm.value = isTranslationKey(option.label)
            ? t(option.label)
            : option.label;
        showDropdown.value = false;
    }
};

// Alterna uma opção na seleção múltipla
// Alterna uma opção na seleção múltipla
const toggleItem = (option) => {
    if (props.disabled) return;

    if (isMultipleMode.value) {
        const values = [...localValue.value];
        const index = values.indexOf(option.value);

        if (index === -1) {
            values.push(option.value);
        } else {
            values.splice(index, 1);
        }

        localValue.value = values;
        searchTerm.value = ''; // Limpa o campo de pesquisa após seleção
    }
};

// Remove um item da seleção múltipla
// Remove um item da seleção múltipla
const removeItem = (value) => {
    if (props.disabled) return;

    if (isMultipleMode.value) {
        const values = [...localValue.value];
        const index = values.indexOf(value);

        if (index !== -1) {
            values.splice(index, 1);
            localValue.value = values;
        }
    }
};

// Limpa a seleção
// Limpa a seleção
const clearSelection = () => {
    if (props.disabled) return;

    if (isMultipleMode.value) {
        localValue.value = [];
    } else {
        localValue.value = '';
    }
    searchTerm.value = '';
};

// Gerencia o clique fora do elemento para fechar o dropdown
// Gerencia o clique fora do elemento para fechar o dropdown
const handleOutsideClick = (event) => {
    if (inputRef.value && !inputRef.value.contains(event.target)) {
        showDropdown.value = false;

        // Restaura o termo de pesquisa para a seleção atual no modo único
        if (!isMultipleMode.value && localValue.value) {
            searchTerm.value = getLabel(localValue.value);
        }
    }
};

// Atualiza o termo de pesquisa quando o valor selecionado muda (modo único)
// Atualiza o termo de pesquisa quando o valor selecionado muda (modo único)
watch(() => localValue.value, (newValue) => {
    if (!isMultipleMode.value) {
        if (newValue && newValue !== '') {
            searchTerm.value = getLabel(newValue);
        } else {
            searchTerm.value = '';
        }
    }
});

// Eventos de lifecycle para adicionar/remover event listeners
// Eventos de lifecycle para adicionar/remover event listeners
onMounted(() => {
    document.addEventListener('click', handleOutsideClick);

    // Inicializa o termo de pesquisa para seleção única
    if (!isMultipleMode.value) {
        if (localValue.value && localValue.value !== '') {
            searchTerm.value = getLabel(localValue.value);
        } else {
            searchTerm.value = '';
        }
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

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
                v-if="(multiple && localValue && localValue.length > 0) || (!multiple && localValue)"
                @click.stop="clearSelection"
                type="button"
                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                :disabled="disabled"
            >
                {{ multiple ? $t('form.deselectAll') : $t('common.clear') }}
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
                        autocomplete="off"
                        class="bg-white-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        :class="{
              'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500':
                (multiple && localValue.length > 0) || (!multiple && localValue)
            }"
                        :placeholder="
              multiple && localValue.length > 0
                ? `${localValue.length} ${$t('form.itemsSelected')}`
                : translatedPlaceholder
            "
                        :disabled="disabled"
                        :required="required"
                    >
                    <div
                        v-if="!multiple && localValue"
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
                                v-if="multiple && isItemSelected(option.value)"
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
                        v-if="filteredOptions.length === 0"
                        class="px-3 py-2 text-gray-500 dark:text-gray-400"
                    >
                        {{ $t('form.noOptions') }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Badges para seleção única -->
        <div
            v-if="!multiple && localValue"
            class="mt-2"
        >
            <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                {{ getLabel(localValue) }}
            </span>
        </div>

        <!-- Badges para seleção múltipla -->
        <div
            v-if="multiple && localValue.length > 0"
            class="mt-2 flex flex-wrap gap-2"
        >
            <span
                v-for="value in localValue"
                :key="value"
                class="inline-flex items-center bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"
            >
                {{ getLabel(value) }}
                <button
                    @click.stop="removeItem(value)"
                    type="button"
                    class="ml-1 text-blue-800 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-200"
                    :disabled="disabled"
                >
                    &times;
                </button>
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