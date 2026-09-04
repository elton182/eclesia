<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Exemplo de Formulário</h1>
    
    <div class="grid gap-6 mb-6 md:grid-cols-1">
      <!-- Card com informações sobre o formulário -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-6 transition-all duration-300 border border-gray-100 dark:border-gray-700">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Sobre este componente</h2>
        
        <div class="space-y-4 text-gray-600 dark:text-gray-300">
          <p>Este componente de formulário demonstra diferentes tipos de campos de entrada que podem ser usados em suas aplicações.</p>
          
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Campos incluídos:</h3>
            <ul class="list-disc pl-5 space-y-1">
              <li>Campos de texto simples</li>
              <li>Campos de texto com ícones</li>
              <li>Áreas de texto</li>
              <li>Select dropdown</li>
              <li>Select com pesquisa</li>
              <li>Botões de rádio</li>
              <li>Checkboxes</li>
              <li>Seletor de data</li>
              <li>Controles deslizantes (range)</li>
              <li>Interruptores (toggle switches)</li>
              <li>Upload de arquivos</li>
            </ul>
          </div>
          
          <p>Todos os campos suportam:</p>
          <ul class="list-disc pl-5 space-y-1">
            <li>Validação de formulário</li>
            <li>Feedback de erros</li>
            <li>Modo desativado</li>
            <li>Temas claro/escuro</li>
            <li>Responsividade</li>
          </ul>
          
          <div class="flex space-x-2 mt-4">
            <button 
              @click="isFormDisabled = !isFormDisabled" 
              class="px-4 py-2 text-sm font-medium rounded-lg border"
              :class="isFormDisabled ? 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800' : 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600'"
            >
              {{ isFormDisabled ? 'Habilitar formulário' : 'Desabilitar formulário' }}
            </button>
            
            <button 
              @click="areFieldsRequired = !areFieldsRequired" 
              class="px-4 py-2 text-sm font-medium rounded-lg border"
              :class="areFieldsRequired ? 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900 dark:text-blue-300 dark:border-blue-800' : 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600'"
            >
              {{ areFieldsRequired ? 'Tornar campos opcionais' : 'Tornar campos obrigatórios' }}
            </button>
          </div>
        </div>
      </div>
      
      <!-- Card com dados submetidos -->
      <div v-if="formSubmitted" class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-6 transition-all duration-300 border border-gray-100 dark:border-gray-700">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Dados Enviados</h2>
        
        <div class="space-y-3">
          <div v-for="(value, key) in submittedData" :key="key" class="border-b border-gray-200 dark:border-gray-700 pb-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ formatFieldName(key) }}</div>
            <div v-if="key === 'interesses' && Array.isArray(value)" class="text-gray-900 dark:text-gray-200">
              <span v-for="(interesse, i) in value" :key="i" class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                {{ interesse }}
              </span>
              <span v-if="value.length === 0" class="text-gray-500 dark:text-gray-400">Nenhum selecionado</span>
            </div>
            <div v-else-if="key === 'arquivo' && value" class="text-gray-900 dark:text-gray-200">
              {{ value.name }} ({{ formatFileSize(value.size) }})
            </div>
            <div v-else-if="key === 'ativo'" class="text-gray-900 dark:text-gray-200">
              <span :class="value ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'" class="px-2 py-1 rounded-full text-xs font-medium">
                {{ value ? 'Sim' : 'Não' }}
              </span>
            </div>
            <div v-else class="text-gray-900 dark:text-gray-200">
              {{ value || 'Não informado' }}
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Formulário -->
    <FormElements 
      title="Formulário de Exemplo"
      :isDisabled="isFormDisabled"
      :isRequired="areFieldsRequired"
      @submit="handleFormSubmit"
      @reset="handleFormReset"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import FormElements from '@/components/form/FormElements.vue';

// Estado
const isFormDisabled = ref(false);
const areFieldsRequired = ref(false);
const formSubmitted = ref(false);
const submittedData = ref({});

// Métodos
function handleFormSubmit(data) {
  submittedData.value = data;
  formSubmitted.value = true;
  
  // Exemplo: você poderia fazer uma chamada API aqui
  console.log('Formulário enviado:', data);
}

function handleFormReset() {
  formSubmitted.value = false;
  submittedData.value = {};
  
  console.log('Formulário resetado');
}

function formatFieldName(key) {
  // Mapeamento de chaves para nomes amigáveis
  const fieldNames = {
    nome: 'Nome',
    email: 'E-mail',
    mensagem: 'Mensagem',
    categoria: 'Categoria',
    produto: 'Produto',
    prioridade: 'Prioridade',
    interesses: 'Interesses',
    data: 'Data',
    avaliacao: 'Avaliação',
    ativo: 'Ativo',
    arquivo: 'Arquivo'
  };
  
  return fieldNames[key] || key;
}

function formatFileSize(size) {
  if (size < 1024) {
    return size + ' bytes';
  } else if (size < 1024 * 1024) {
    return (size / 1024).toFixed(2) + ' KB';
  } else {
    return (size / (1024 * 1024)).toFixed(2) + ' MB';
  }
}
</script> 