<template>
    <div class="rich-text-editor font-normal">
        <label v-if="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Barra de ferramentas -->
        <div class="flex flex-wrap gap-1 p-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-t-lg">
            <!-- Formatação de texto -->
            <button
                v-for="format in textFormats"
                :key="format.command"
                @click="executeCommand(format.command)"
                :class="[
                    'p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors',
                    isActive(format.command) ? 'bg-gray-200 dark:bg-gray-700' : ''
                ]"
                :title="format.label"
            >
                <font-awesome-icon :icon="format.icon" class="w-4 h-4" />
            </button>

            <!-- Separador -->
            <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

            <!-- Alinhamento -->
            <button
                v-for="align in alignments"
                :key="align.command"
                @click="executeCommand(align.command)"
                :class="[
                    'p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors',
                    isActive(align.command) ? 'bg-gray-200 dark:bg-gray-700' : ''
                ]"
                :title="align.label"
            >
                <font-awesome-icon :icon="align.icon" class="w-4 h-4" />
            </button>

            <!-- Separador -->
            <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

            <!-- Listas -->
            <button
                v-for="list in lists"
                :key="list.command"
                @click="executeCommand(list.command)"
                :class="[
                    'p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors',
                    isActive(list.command) ? 'bg-gray-200 dark:bg-gray-700' : ''
                ]"
                :title="list.label"
            >
                <font-awesome-icon :icon="list.icon" class="w-4 h-4" />
            </button>

            <!-- Separador -->
            <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>

            <!-- Imagem -->
            <button
                @click="triggerImageUpload"
                :class="[
                    'p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors'
                ]"
                title="Inserir imagem"
            >
                <font-awesome-icon :icon="faImage" class="w-4 h-4" />
            </button>

            <!-- Input de arquivo oculto -->
            <input
                type="file"
                ref="imageInput"
                class="hidden"
                accept="image/*"
                @change="handleImageUpload"
            />
        </div>

        <!-- Área editável -->
        <div
            ref="editor"
            class="editor-content p-4 min-h-[200px] bg-white dark:bg-gray-900 border border-t-0 border-gray-200 dark:border-gray-700 rounded-b-lg focus:outline-none text-gray-900 dark:text-gray-100"
            contenteditable="true"
            @input="handleInput"
            @paste="handlePaste"
            @drop.prevent="handleDrop"
            @dragover.prevent
        ></div>

        <!-- Modal de redimensionamento de imagem -->
        <InnovModal v-model="showImageModal" size="md">
            <template #default>
                <div class="p-4">
                    <h3 class="text-lg font-medium mb-4 text-gray-900 dark:text-white">
                        Configurar Imagem
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Preview da imagem -->
                        <div class="flex justify-center">
                            <img 
                                :src="currentImageSrc" 
                                ref="imagePreview"
                                class="max-w-full max-h-[300px] object-contain"
                            />
                        </div>

                        <!-- Controles de tamanho -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Largura (px)
                                </label>
                                <input 
                                    type="number" 
                                    v-model="imageWidth"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Altura (px)
                                </label>
                                <input 
                                    type="number" 
                                    v-model="imageHeight"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md"
                                />
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="flex justify-end gap-2 mt-4">
                            <button 
                                @click="showImageModal = false"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Cancelar
                            </button>
                            <button 
                                @click="insertImage"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                Inserir
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </InnovModal>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { 
    faBold, faItalic, faUnderline, faStrikethrough,
    faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify,
    faListUl, faListOl, faImage
} from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InnovModal from '@/components/base/InnovModal.vue'

library.add(
    faBold, faItalic, faUnderline, faStrikethrough,
    faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify,
    faListUl, faListOl, faImage
)

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    required: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue'])

const editor = ref(null)
const imageInput = ref(null)
const showImageModal = ref(false)
const currentImageSrc = ref('')
const imageWidth = ref(0)
const imageHeight = ref(0)
const imageFile = ref(null)

// Definição dos botões de formatação
const textFormats = [
    { command: 'bold', icon: 'bold', label: 'Negrito' },
    { command: 'italic', icon: 'italic', label: 'Itálico' },
    { command: 'underline', icon: 'underline', label: 'Sublinhado' },
    { command: 'strikeThrough', icon: 'strikethrough', label: 'Tachado' }
]

const alignments = [
    { command: 'justifyLeft', icon: 'align-left', label: 'Alinhar à esquerda' },
    { command: 'justifyCenter', icon: 'align-center', label: 'Centralizar' },
    { command: 'justifyRight', icon: 'align-right', label: 'Alinhar à direita' },
    { command: 'justifyFull', icon: 'align-justify', label: 'Justificar' }
]

const lists = [
    { command: 'insertUnorderedList', icon: 'list-ul', label: 'Lista não ordenada' },
    { command: 'insertOrderedList', icon: 'list-ol', label: 'Lista ordenada' }
]

// Executa um comando de formatação
const executeCommand = (command) => {
    document.execCommand(command, false, null)
    editor.value.focus()
    // Atualiza o v-model após comandos que alteram o conteúdo, como listas
    if (['insertUnorderedList', 'insertOrderedList'].includes(command)) {
        handleInput()
    }
}

// Verifica se um comando está ativo
const isActive = (command) => {
    return document.queryCommandState(command)
}

// Manipula a entrada de texto
const handleInput = () => {
    const content = editor.value.innerHTML
    emit('update:modelValue', content)
}

// Manipula a colagem de texto
const handlePaste = (e) => {
    e.preventDefault()
    
    // Se houver imagens no clipboard
    const items = e.clipboardData.items
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            const file = items[i].getAsFile()
            handleImageFile(file)
            return
        }
    }
    
    // Se for apenas texto
    const text = e.clipboardData.getData('text/plain')
    document.execCommand('insertText', false, text)
}

// Manipula o drop de imagens
const handleDrop = async (e) => {
    const files = e.dataTransfer.files
    if (files.length > 0 && files[0].type.startsWith('image/')) {
        handleImageFile(files[0])
    }
}

// Trigger para o input de imagem
const triggerImageUpload = () => {
    imageInput.value.click()
}

// Manipula o upload de imagem via input
const handleImageUpload = (e) => {
    const file = e.target.files[0]
    if (file) {
        handleImageFile(file)
    }
    // Limpa o input para permitir selecionar a mesma imagem novamente
    e.target.value = ''
}

// Processa o arquivo de imagem
const handleImageFile = (file) => {
    if (!file || !file.type.startsWith('image/')) return
    
    imageFile.value = file
    const reader = new FileReader()
    
    reader.onload = (e) => {
        currentImageSrc.value = e.target.result
        
        // Cria uma imagem temporária para obter dimensões
        const img = new Image()
        img.onload = () => {
            imageWidth.value = img.width
            imageHeight.value = img.height
            showImageModal.value = true
        }
        img.src = e.target.result
    }
    
    reader.readAsDataURL(file)
}

// Insere a imagem no editor
const insertImage = () => {
    if (!currentImageSrc.value) return
    
    editor.value.focus() // Garante que o editor está focado antes de inserir
    const selection = window.getSelection()
    
    // Verifica se há uma seleção válida e um range
    if (!selection.rangeCount) {
        // Se não houver range (ex: editor vazio e não focado), cria um no final
        const range = document.createRange()
        range.selectNodeContents(editor.value)
        range.collapse(false) // Colapsa para o final
        selection.removeAllRanges()
        selection.addRange(range)
    }
    
    const range = selection.getRangeAt(0)
    range.deleteContents()

    const img = document.createElement('img')
    img.src = currentImageSrc.value
    img.width = imageWidth.value
    img.height = imageHeight.value
    img.style.maxWidth = '100%'
    
    range.insertNode(img)
    
    // Move o cursor para depois da imagem (opcional, mas bom para UX)
    const newRange = document.createRange()
    newRange.setStartAfter(img)
    newRange.setEndAfter(img)
    selection.removeAllRanges()
    selection.addRange(newRange)
    
    // Atualiza o v-model
    emit('update:modelValue', editor.value.innerHTML)
    
    // Fecha o modal
    showImageModal.value = false
    currentImageSrc.value = ''
    imageFile.value = null
}

// Observa mudanças no modelValue
watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.innerHTML !== newValue) {
        const selection = window.getSelection()
        const isEditorFocused = document.activeElement === editor.value
        let savedRange = null
        
        // Salva a seleção atual se o editor estiver focado e houver uma seleção
        if (isEditorFocused && selection.rangeCount > 0) {
            savedRange = selection.getRangeAt(0).cloneRange() // Clonar o range é importante
        }
        
        editor.value.innerHTML = newValue
        
        // Restaura a seleção e o foco
        if (isEditorFocused && savedRange) {
            selection.removeAllRanges()
            selection.addRange(savedRange)
            // Não é estritamente necessário focar explicitamente se já estava focado
            // e o range foi restaurado corretamente, mas pode ajudar em alguns casos.
            // editor.value.focus(); 
        }
    }
}, { immediate: true })

onMounted(() => {
    // O watch com immediate: true já deve cuidar da inicialização.
    // Se props.modelValue for vazio, editor.value.innerHTML já será "" (ou o placeholder CSS)
    // Se props.modelValue tiver conteúdo, o watch o definirá.
    // Esta verificação explícita pode ser redundante se o watch estiver funcionando como esperado.
    if (editor.value && editor.value.innerHTML !== props.modelValue) {
        editor.value.innerHTML = props.modelValue
    }
})
</script>

<style>
/* Estilização do conteúdo editável */
.editor-content {
    line-height: 1.5;
}

.editor-content:empty:before {
    content: 'Digite seu texto aqui...';
    color: #9ca3af;
}

.editor-content:focus {
    outline: none;
}

/* Estilização do conteúdo HTML */
.editor-content p {
    margin-bottom: 1rem;
}

.editor-content ul, 
.editor-content ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.editor-content ul {
    list-style-type: disc;
}

.editor-content ol {
    list-style-type: decimal;
}

.editor-content ul ul,
.editor-content ol ol,
.editor-content ul ol,
.editor-content ol ul {
    margin-bottom: 0;
}

/* Estilização das imagens */
.editor-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
}

/* Estilização dos botões */
.rich-text-editor button {
    color: #374151;
}

.rich-text-editor button:hover {
    color: #111827;
}

.dark .rich-text-editor button {
    color: #d1d5db;
}

.dark .rich-text-editor button:hover {
    color: #f3f4f6;
}

.rich-text-editor button.active {
    background-color: #e5e7eb;
}

.dark .rich-text-editor button.active {
    background-color: #374151;
}
</style> 