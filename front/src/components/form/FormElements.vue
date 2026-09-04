<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-6 transition-all duration-300 border border-gray-100 dark:border-gray-700">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">{{ title }}</h2>

        <form
            @submit.prevent="handleSubmit"
            class="space-y-6"
        >
            <!-- Layout em grid para os elementos do formulário -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Input de texto -->

                <TextInput
                    v-model="formData.nome"
                    name="nome-usuario"
                    label="Nome do Usuário"
                    placeholder="Digite seu nome completo"
                    required
                    :errors="errors.nome"
                />

                <!-- Input group icon -->
                <InputTextGroup
                    v-model="formData.email"
                    name="email-usuario"
                    label="E-mail de Contato"
                    placeholder="seu@email.com"
                    required
                    :icon="icon"
                    :errors="errors.email"
                />

            </div>

            <TextAreaInput
                v-model="formData.mensagem"
                name="mensagem"
                label="Mensagem"
                placeholder="Digite sua mensagem aqui"
                :rows="4"
            />

            <!-- Segunda linha de grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <SelectSearch
                    v-model="formData.produto"
                    :options="produtos"
                    name="produto"
                    label="Produto de Interesse"
                    placeholder="Pesquise um produto"
                    :multiple="false"
                    required
                    :errors="errors.produto"
                />

                <SelectSearch
                    v-model="formData.produtos"
                    :options="produtos"
                    name="produto"
                    label="Produto de Interesse"
                    placeholder="Pesquise um produto"
                    :multiple="true"
                    required
                    :errors="errors.produtos"
                />

            </div>

            <!-- Data picker -->
            <DateInput
                v-model="formData.data"
                name="data-evento"
                label="Data do Evento"
                required
                :errors="errors.data"
            />

            <!-- Terceira linha de grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Radio buttons -->
                <RadioGroup
                    v-model="formData.prioridade"
                    :options="prioridades"
                    name="prioridade"
                    label="Prioridade"
                    required
                    :errors="errors.prioridade"
                />

                <!-- RangeSlider com configuração padrão -->
                <div class="mb-6">
                    <RangeSlider
                        v-model="formData.avaliacao"
                        name="avaliacao"
                        label="Avaliação do Produto"
                        min="0"
                        max="10"
                        step="1"
                        :errors="errors.avaliacao"
                    />
                </div>

                <!-- RangeSlider com marcadores personalizados -->
                <div class="mb-6">
                    <RangeSlider
                        v-model="formData.satisfacao"
                        name="satisfacao"
                        label="Nível de Satisfação"
                        min="0"
                        max="4"
                        step="1"
                        :markers="satisfacaoMarkers"
                        :errors="errors.satisfacao"
                    />
                </div>

                <!-- Switch toggle -->
                <ToggleSwitch
                    v-model="formData.ativo"
                    name="ativo"
                    label="Ativo"
                    :errors="errors.ativo"
                />
            </div>

            <!-- Checkboxes -->
            <!-- Checkboxes em grade (padrão) -->
            <div class="mb-6">
                <CheckboxGroup
                    v-model="formData.interesses"
                    :options="interesses"
                    name="interesses"
                    label="Interesses"
                    required
                    :errors="errors.interesses"
                    :columns="6"
                />
            </div>

            <!-- Checkboxes em linha -->
            <div class="mb-6">
                <CheckboxGroup
                    v-model="formData.interesses"
                    :options="interesses"
                    name="interesses-inline"
                    label="Interesses (em linha)"
                    :inline="true"
                />
            </div>

            <!-- Upload de arquivo -->
            <div class="form-group">
                <label
                    class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                    for="arquivo"
                >Upload de arquivo
                </label>
                <div
                    class="flex flex-col items-center justify-center w-full"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleFileDrop($event)"
                >
                    <label
                        for="arquivo"
                        :class="[
              'flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer',
              dragOver ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600 hover:bg-white-50 dark:hover:bg-gray-700',
              fileError ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : '',
              isDisabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
                    >
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <div
                                v-if="formData.arquivo"
                                class="mb-3 flex flex-col items-center"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8 text-green-500 mb-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ formData.arquivo.name }}
                                </p>
                            </div>
                            <div
                                v-else-if="fileError"
                                class="mb-3 flex flex-col items-center"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8 text-red-500 mb-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                                    />
                                </svg>
                                <p class="text-sm text-red-500 dark:text-red-400">
                                    {{ fileError }}
                                </p>
                            </div>
                            <div v-else>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8 text-gray-500 dark:text-gray-400 mb-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"
                                    />
                                </svg>
                                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">Clique para fazer upload</span>
                                    ou arraste e solte
                                </p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SVG, PNG, JPG ou GIF (Máx. 2MB)</p>
                        </div>
                        <input
                            type="file"
                            id="arquivo"
                            class="hidden"
                            @change="handleFileUpload"
                            accept="image/png,image/jpeg,image/gif,image/svg+xml"
                            :disabled="isDisabled"
                        >
                    </label>
                </div>
                <!-- Botão para remover arquivo -->
                <div
                    v-if="formData.arquivo"
                    class="mt-2 flex justify-end"
                >
                    <button
                        type="button"
                        @click="removeFile"
                        class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        :disabled="isDisabled"
                    >
                        Remover arquivo
                    </button>
                </div>
            </div>

            <!-- Botões de ação -->
            <div class="flex justify-end space-x-2 pt-4">
                <button
                    type="button"
                    @click="resetForm"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-white-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    :disabled="isDisabled"
                >
                    Limpar
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                    :disabled="isDisabled"
                >
                    Enviar
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import {ref, computed, onMounted, onUnmounted, watch, nextTick} from 'vue';
import TextInput from "@/components/form/TextInput.vue";
import InputTextGroup from "@/components/form/InputTextGroup.vue";
import TextAreaInput from "@/components/form/TextAreaInput.vue";
import SelectSearch from "@/components/form/SelectSearch.vue";
import DateInput from "@/components/form/DateInput.vue";
import RadioGroup from "@/components/form/RadioGroup.vue";
import RangeSlider from "@/components/form/RangeSlider.vue";
import ToggleSwitch from "@/components/form/ToggleSwitch.vue";
import CheckboxGroup from "@/components/form/CheckboxGroup.vue";

// Props
const props = defineProps({
    title: {
        type: String,
        default: 'Formulário'
    },
    isDisabled: {
        type: Boolean,
        default: false
    },
    isRequired: {
        type: Boolean,
        default: false
    },
    initialData: {
        type: Object,
        default: () => ({})
    }
});

const icon = `<svg
                                class="w-4 h-4 text-gray-500 dark:text-gray-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>`

// Emits de eventos
const emit = defineEmits(['submit', 'reset']);

// Estado do formulário
const formData = ref({
    nome: props.initialData.nome || '',
    email: props.initialData.email || '',
    mensagem: props.initialData.mensagem || '',
    categoria: props.initialData.categoria || '',
    produto: props.initialData.produto || '',
    habilidades: props.initialData.habilidades || [],
    produtos: props.initialData.produtos || 'media',
    interesses: props.initialData.interesses || [],
    data: props.initialData.data || getTodayFormatted(),
    avaliacao: props.initialData.avaliacao || 5,
    ativo: props.initialData.ativo !== undefined ? props.initialData.ativo : true,
    arquivo: props.initialData.arquivo || null
});

// Estado dos selects com pesquisa
const produtoSearchTerm = ref('');
const showProdutoDropdown = ref(false);

const habilidadesSearchTerm = ref('');
const showHabilidadesDropdown = ref(false);

const satisfacaoMarkers = ['Ruim', 'Regular', 'Bom', 'Ótimo', 'Excelente'];

// Erros de validação
const errors = ref({});

// Adicione esta nova propriedade no script
const fileError = ref('');

// Dados de exemplo
const categorias = [
    {value: 'tecnologia', label: 'Tecnologia'},
    {value: 'saude', label: 'Saúde'},
    {value: 'educacao', label: 'Educação'},
    {value: 'financas', label: 'Finanças'},
    {value: 'outros', label: 'Outros'}
];

const produtos = [
    {value: 'laptop', label: 'Laptop'},
    {value: 'smartphone', label: 'Smartphone'},
    {value: 'tablet', label: 'Tablet'},
    {value: 'smartwatch', label: 'Smartwatch'},
    {value: 'camera', label: 'Câmera Digital'},
    {value: 'headphone', label: 'Fone de Ouvido'},
    {value: 'mouse', label: 'Mouse'},
    {value: 'teclado', label: 'Teclado'},
    {value: 'monitor', label: 'Monitor'},
    {value: 'impressora', label: 'Impressora'}
];

const habilidades = [
    {value: 'html', label: 'HTML'},
    {value: 'css', label: 'CSS'},
    {value: 'javascript', label: 'JavaScript'},
    {value: 'vue', label: 'Vue.js'},
    {value: 'react', label: 'React'},
    {value: 'angular', label: 'Angular'},
    {value: 'node', label: 'Node.js'},
    {value: 'python', label: 'Python'},
    {value: 'java', label: 'Java'},
    {value: 'csharp', label: 'C#'},
    {value: 'php', label: 'PHP'},
    {value: 'ruby', label: 'Ruby'}
];

const prioridades = [
    {value: 'baixa', label: 'Baixa'},
    {value: 'media', label: 'Média'},
    {value: 'alta', label: 'Alta'}
];

const interesses = [
    {value: 'esportes', label: 'Esportes'},
    {value: 'musica', label: 'Música'},
    {value: 'viagem', label: 'Viagem'},
    {value: 'gastronomia', label: 'Gastronomia'},
    {value: 'tecnologia', label: 'Tecnologia'},
    {value: 'cinema', label: 'Cinema'}
];

// Computados para os selects com pesquisa
const filteredProdutos = computed(() => {
    if (!produtoSearchTerm.value) return produtos;
    const search = produtoSearchTerm.value.toLowerCase();
    return produtos.filter(produto =>
        produto.label.toLowerCase().includes(search)
    );
});

const filteredHabilidades = computed(() => {
    if (!habilidadesSearchTerm.value) return habilidades;
    const search = habilidadesSearchTerm.value.toLowerCase();
    return habilidades.filter(habilidade =>
        habilidade.label.toLowerCase().includes(search)
    );
});

// Métodos
function handleSubmit() {
    // Validação básica
    errors.value = {};

    if (!formData.value.nome) {
        errors.value.nome = 'O nome é obrigatório';
    }

    if (!formData.value.email) {
        errors.value.email = 'O e-mail é obrigatório';
    } else if (!validateEmail(formData.value.email)) {
        errors.value.email = 'E-mail inválido';
    }

    // Se não tiver erros, emitir evento
    if (Object.keys(errors.value).length === 0) {
        emit('submit', {...formData.value});
    }
}

function resetForm() {
    formData.value = {
        nome: '',
        email: '',
        mensagem: '',
        categoria: '',
        produto: '',
        habilidades: [],
        prioridade: 'media',
        interesses: [],
        data: getTodayFormatted(),
        avaliacao: 5,
        ativo: true,
        arquivo: null
    };

    produtoSearchTerm.value = '';
    habilidadesSearchTerm.value = '';
    errors.value = {};

    emit('reset');
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Funções para o select com pesquisa (seleção única)
function handleFocusProduto() {
    nextTick(() => {
        showProdutoDropdown.value = true;
        // Quando o dropdown se abre, se houver produto selecionado, mostrar o nome dele
        if (formData.value.produto && !produtoSearchTerm.value) {
            produtoSearchTerm.value = getProdutoLabel(formData.value.produto);
        }
    });
}

function filterProdutos() {
    if (!showProdutoDropdown.value) {
        showProdutoDropdown.value = true;
    }
}

function selectProduto(produto) {
    formData.value.produto = produto.value;
    produtoSearchTerm.value = produto.label;
    showProdutoDropdown.value = false;
}

function getProdutoLabel(value) {
    const produto = produtos.find(p => p.value === value);
    return produto ? produto.label : '';
}

// Funções para o select com pesquisa (múltipla seleção)
function handleFocusHabilidades() {
    nextTick(() => {
        showHabilidadesDropdown.value = true;
    });
}

function filterHabilidades() {
    // A filtragem acontece automaticamente pelo computed
    if (!showHabilidadesDropdown.value) {
        showHabilidadesDropdown.value = true;
    }
}

function toggleHabilidade(habilidade) {
    const index = formData.value.habilidades.indexOf(habilidade.value);
    if (index === -1) {
        // Adicionar habilidade
        formData.value.habilidades.push(habilidade.value);
    } else {
        // Remover habilidade
        formData.value.habilidades.splice(index, 1);
    }
    // Mantém o dropdown aberto e não limpa o termo de pesquisa
    showHabilidadesDropdown.value = true;
}

function removeHabilidade(value) {
    const index = formData.value.habilidades.indexOf(value);
    if (index !== -1) {
        formData.value.habilidades.splice(index, 1);
    }
}

function isHabilidadeSelected(value) {
    return formData.value.habilidades.includes(value);
}

function getHabilidadeLabel(value) {
    const habilidade = habilidades.find(h => h.value === value);
    return habilidade ? habilidade.label : '';
}

function validateFile(file) {
    fileError.value = '';

    if (!isValidFileType(file)) {
        fileError.value = 'Tipo de arquivo não suportado';
        return false;
    }

    if (!isValidFileSize(file)) {
        fileError.value = 'O arquivo deve ter no máximo 2MB';
        return false;
    }

    return true;
}

function handleFileDrop(event) {
    dragOver.value = false;
    const file = event.dataTransfer.files[0];
    if (file && validateFile(file)) {
        formData.value.arquivo = file;
    }
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file && validateFile(file)) {
        formData.value.arquivo = file;
    }
}

function removeFile() {
    formData.value.arquivo = null;
    fileError.value = '';
}

function isValidFileType(file) {
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    return validTypes.includes(file.type);
}

function isValidFileSize(file) {
    const maxSize = 2 * 1024 * 1024; // 2MB
    return file.size <= maxSize;
}

function getTodayFormatted() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Fechar dropdowns quando clicar fora
function handleClickOutside(event) {
    // Verificar se o clique foi dentro dos dropdowns
    const produtoDropdown = document.querySelector('#produto')?.closest('.relative');
    const habilidadesDropdown = document.querySelector('#habilidades')?.closest('.relative');

    if (produtoDropdown && !produtoDropdown.contains(event.target)) {
        showProdutoDropdown.value = false;
        // Garantir que o texto do produto selecionado seja exibido após fechar o dropdown
        if (formData.value.produto) {
            produtoSearchTerm.value = getProdutoLabel(formData.value.produto);
        } else {
            produtoSearchTerm.value = '';
        }
    }

    if (habilidadesDropdown && !habilidadesDropdown.contains(event.target)) {
        showHabilidadesDropdown.value = false;
        habilidadesSearchTerm.value = '';
    }
}

// Se o produto for selecionado, preencher a pesquisa com o nome
watch(() => formData.value.produto, (newValue) => {
    if (newValue) {
        produtoSearchTerm.value = getProdutoLabel(newValue);
    }
}, {immediate: true});

// Lifecycle hooks
onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);

    // Inicializar os valores dos inputs de pesquisa se tiverem valores iniciais
    if (formData.value.produto) {
        produtoSearchTerm.value = getProdutoLabel(formData.value.produto);
    }
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// Garantir que o texto de pesquisa sempre reflita os itens selecionados
watch(
    () => [formData.value.produto, showProdutoDropdown.value],
    ([produto, showDropdown]) => {
        if (!showDropdown && produto) {
            // Quando o dropdown fecha e há um produto selecionado, garantir que o texto seja exibido
            nextTick(() => {
                produtoSearchTerm.value = getProdutoLabel(produto);
            });
        }
    }
);

// Adicione estas novas propriedades e funções no script
const dragOver = ref(false);

</script> 