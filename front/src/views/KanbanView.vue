<template>
  <div class="space-y-6" >
    <!-- Cabeçalho do Kanban -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quadro Kanban</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gerencie suas tarefas de forma visual</p>
        </div>
        <div class="flex-1 max-w-md">
          <div class="relative">
            <input
              v-model="termoPesquisa"
              type="text"
              placeholder="Pesquisar cards..."
              class="w-full text-black pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors flex items-center gap-2">
            <span>Nova Tarefa</span>
          </button>
          <div class="relative">
            <button 
              @click="toggleNovaColuna"
              class="px-4 py-2 bg-secondary-200 dark:bg-secondary-700 text-secondary-800 dark:text-secondary-200 rounded-md hover:bg-secondary-300 dark:hover:bg-secondary-600 transition-colors flex items-center gap-2"
            >
              <span>Nova Coluna</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </button>
            <div v-if="mostrarNovaColuna" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 z-10">
              <input
                v-model="novaColunaTitulo"
                type="text"
                placeholder="Nome da coluna"
                class="w-full px-3 py-2 border border-gray-300 rounded-md mb-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                @keyup.enter="adicionarColuna"
              >
              <div class="flex justify-end gap-2">
                <button @click="toggleNovaColuna" class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300">
                  Cancelar
                </button>
                <button @click="adicionarColuna" class="px-3 py-1 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-700">
                  Adicionar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtros e Estatísticas -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
      <div class="flex flex-wrap gap-4 justify-between items-center">
        <!-- Filtros -->
        <div class="flex flex-wrap gap-2">
          <div class="relative">
            <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option selected>Todos os Projetos</option>
              <option value="SGE">Sistema de Gestão Empresarial</option>
              <option value="APP">Aplicativo Mobile</option>
              <option value="WEB">Website Institucional</option>
            </select>
          </div>
          <div class="relative">
            <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option selected>Todos os Membros</option>
              <option value="AM">Ana Maria Silva</option>
              <option value="JC">João Carlos Oliveira</option>
              <option value="RL">Roberta Lima</option>
              <option value="MS">Marcos Santos</option>
              <option value="PF">Paula Ferreira</option>
            </select>
          </div>
          <div class="relative">
            <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
              <option selected>Todas as Etiquetas</option>
              <option value="FE">Front-end</option>
              <option value="BE">Back-end</option>
              <option value="UI">UI/UX</option>
              <option value="BUG">Bug</option>
              <option value="DOC">Documentação</option>
            </select>
          </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="flex flex-wrap gap-4">
          <div class="flex items-center">
            <div class="w-3 h-3 bg-gray-300 rounded-full mr-2"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">Pendentes: 8</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-yellow-400 rounded-full mr-2"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">Em Progresso: 5</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-blue-400 rounded-full mr-2"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">Em Revisão: 3</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-green-400 rounded-full mr-2"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">Concluídas: 12</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quadro Kanban -->
    <div class="h-[calc(100vh-16rem)] flex flex-col">
      <div class="overflow-x-auto flex-1">
        <div class="inline-flex gap-3 p-1 min-w-max h-full">
          <!-- Colunas do Kanban -->
          <div
            v-for="coluna in colunas"
            :key="coluna.id"
            :data-coluna="coluna.id"
            class="kanban-column bg-white dark:bg-gray-800 rounded-lg shadow-md flex flex-col h-full w-[350px] flex-shrink-0"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop($event, coluna.id)"
          >
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 rounded-t-lg border-1 border-gray-200 dark:border-gray-700">
              <h3 class="font-medium text-gray-900 dark:text-white flex items-center">
                <span :class="['text-black dark:text-white w-3 h-3 rounded-full mr-2', 'bg-' + coluna.cor]"></span>
                {{ coluna.titulo }}
                <span class="ml-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-medium px-2 py-0.5 rounded-full">
                  {{ contarTarefasPorColuna(coluna.id) }}
                </span>
              </h3>
            </div>
            <div class="kanban-cards p-4 flex-1 overflow-y-auto space-y-3">
              <!-- Cartões de tarefas -->
              <div
                v-for="tarefa in getTarefasPorColuna(coluna.id)"
                :key="tarefa.id"
                :data-tarefa-id="tarefa.id"
                class="kanban-card bg-white dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm hover:shadow-md transition-shadow cursor-move"
                draggable="true"
                @dragstart="handleDragStart($event, tarefa)"
                @dragend="handleDragEnd"
                @click="abrirModal(tarefa)"
              >
                <div class="flex justify-between items-start">
                  <span :class="[
                    'px-2 py-1 text-xs font-medium rounded',
                    'bg-' + tarefa.etiquetaCor + '-100',
                    'text-' + tarefa.etiquetaCor + '-800',
                    'dark:bg-' + tarefa.etiquetaCor + '-900',
                    'dark:text-' + tarefa.etiquetaCor + '-200'
                  ]">
                    {{ tarefa.etiqueta }}
                  </span>
                  <div class="text-gray-500 dark:text-gray-400">
                    <span class="text-xs">#{{ tarefa.id }}</span>
                  </div>
                </div>
                <h4 class="mt-2 text-sm font-medium text-gray-900 text-black dark:text-white">{{ tarefa.titulo }}</h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ tarefa.descricao }}</p>
                <div class="mt-3 flex justify-between items-center">
                  <div class="flex items-center">
                    <div :class="[
                      'flex-shrink-0 w-6 h-6 rounded-full',
                      'bg-' + tarefa.etiquetaCor + '-100',
                      'text-' + tarefa.etiquetaCor + '-700',
                      'font-semibold text-xs',
                      'flex items-center justify-center'
                    ]">
                      {{ tarefa.responsavel }}
                    </div>
                  </div>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ tarefa.data }}</span>
                </div>
                <!-- Barra de Progresso (apenas se houver subtarefas) -->
                <div v-if="tarefa.subtarefas && tarefa.subtarefas.length > 0" class="mt-3">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                      {{ calcularProgressoSubtarefas(tarefa) }}% Completo
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                      {{ tarefa.subtarefas.filter(t => t.concluida).length }}/{{ tarefa.subtarefas.length }}
                    </span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                         :style="{ width: calcularProgressoSubtarefas(tarefa) + '%' }">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Adicionar Card -->
            <div class="p-3 border-t border-gray-200 dark:border-gray-700">
              <div v-if="colunaEmEdicao === coluna.id" class="space-y-2">
                <input
                  v-model="novoCardTitulo"
                  type="text"
                  placeholder="Título do card..."
                  class="w-full text-black px-3 py-2 text-sm border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  @keyup.enter="adicionarCard(coluna.id)"
                >
                <div class="flex justify-end gap-2">
                  <button 
                    @click="colunaEmEdicao = null" 
                    class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                  >
                    Cancelar
                  </button>
                  <button 
                    @click="adicionarCard(coluna.id)"
                    class="px-3 py-1 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-700"
                  >
                    Adicionar
                  </button>
                </div>
              </div>
              <button
                v-else
                @click="colunaEmEdicao = coluna.id"
                class="w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md flex items-center gap-2 transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Adicionar card</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Detalhes -->
    <transition name="modal">
      <div v-if="modalAberto" class="fixed inset-0 flex items-center justify-center z-50" @click="fecharModal">
        <div class="absolute inset-0 backdrop-blur-sm bg-black/30" aria-hidden="true"></div>
        <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto relative z-10" @click.stop>
          <!-- Cabeçalho do Modal -->
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-start">
              <div>
                <span :class="[
                  'px-2 py-1 text-xs font-medium rounded',
                  'bg-' + tarefaSelecionada?.etiquetaCor + '-100',
                  'text-' + tarefaSelecionada?.etiquetaCor + '-800'
                ]">
                  {{ tarefaSelecionada?.etiqueta }}
                </span>
                <h3 class="text-xl font-bold mt-2 text-black dark:text-white">{{ tarefaSelecionada?.titulo }}</h3>
              </div>
              <button @click="fecharModal" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Conteúdo do Modal -->
          <div class="p-6 space-y-6">
            <!-- Etiqueta e Cor -->
            <div class="flex gap-4">
              <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Etiqueta</label>
                <select 
                  v-model="tarefaSelecionada.etiqueta"
                  class="w-full text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="FE">Front-end</option>
                  <option value="BE">Back-end</option>
                  <option value="UI/UX">UI/UX</option>
                  <option value="BUG">Bug</option>
                  <option value="DOC">Documentação</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Cor</label>
                <select 
                  v-model="tarefaSelecionada.etiquetaCor"
                  class="w-full text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="purple">Roxo</option>
                  <option value="blue">Azul</option>
                  <option value="green">Verde</option>
                  <option value="red">Vermelho</option>
                  <option value="yellow">Amarelo</option>
                  <option value="gray">Cinza</option>
                </select>
              </div>
            </div>

            <!-- Descrição -->
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Descrição</label>
              <textarea
                v-model="tarefaSelecionada.descricao"
                rows="3"
                class="w-full text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                placeholder="Descreva a tarefa..."
              ></textarea>
            </div>

            <!-- Data e Responsável -->
            <div class="flex gap-4">
              <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Data</label>
                <input
                  type="date"
                  v-model="tarefaSelecionada.dataRaw"
                  class="w-full text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  @input="atualizarData"
                >
              </div>
              <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Responsável</label>
                <select
                  v-model="tarefaSelecionada.responsavel"
                  class="w-full text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="AM">Ana Maria Silva</option>
                  <option value="JC">João Carlos Oliveira</option>
                  <option value="RL">Roberta Lima</option>
                  <option value="MS">Marcos Santos</option>
                  <option value="PF">Paula Ferreira</option>
                </select>
              </div>
            </div>

            <!-- Lista de Subtarefas -->
            <div>
              <div class="flex justify-between items-center mb-2">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Subtarefas</h4>
                <span class="text-sm text-gray-500">{{ progressoSubtarefas }}%</span>
              </div>
              
              <!-- Barra de Progresso -->
              <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                <div class="bg-blue-600 h-2.5 rounded-full" :style="{ width: progressoSubtarefas + '%' }"></div>
              </div>

              <!-- Lista de Subtarefas -->
              <div class="space-y-2">
                <div v-for="subtarefa in tarefaSelecionada?.subtarefas" 
                     :key="subtarefa.id"
                     class="flex items-center">
                  <input type="checkbox"
                         :checked="subtarefa.concluida"
                         @change="toggleSubtarefa(subtarefa)"
                         class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                  <span :class="[
                    'ml-2 text-sm',
                    subtarefa.concluida ? 'line-through text-gray-400' : 'text-gray-700 dark:text-gray-300'
                  ]">{{ subtarefa.texto }}</span>
                </div>
              </div>

              <!-- Adicionar Nova Subtarefa -->
              <div class="mt-4 flex gap-2">
                <input v-model="novaTarefa"
                       @keyup.enter="adicionarSubtarefa"
                       type="text"
                       placeholder="Nova subtarefa..."
                       class="flex-1 text-black border border-gray-300 rounded-md px-3 py-2 text-sm">
                <button @click="adicionarSubtarefa"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                  Adicionar
                </button>
              </div>
            </div>

            <!-- Cards Vinculados -->
            <div>
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cards Vinculados</h4>
              
              <!-- Lista de Cards Vinculados -->
              <div class="space-y-2 mb-4">
                <div v-for="card in cardsVinculadosDetalhes"
                     :key="card.id"
                     class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg group">
                  <div class="flex items-center">
                    <span :class="[
                      'px-2 py-1 text-xs font-medium rounded mr-2',
                      'bg-' + card.etiquetaCor + '-100',
                      'text-' + card.etiquetaCor + '-800'
                    ]">{{ card.etiqueta }}</span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ card.titulo }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">#{{ card.id }}</span>
                    <button 
                      @click="desvincularCard(card.id)"
                      class="opacity-0 group-hover:opacity-100 text-red-500 hover:text-red-700 transition-opacity"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Vincular Novo Card -->
              <div class="flex gap-2">
                <select
                  v-model="cardParaVincular"
                  class="flex-1 text-black border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Selecione um card...</option>
                  <option 
                    v-for="card in cardsDisponiveis" 
                    :key="card.id" 
                    :value="card.id"
                  >
                    #{{ card.id }} - {{ card.titulo }}
                  </option>
                </select>
                <button 
                  @click="vincularCard"
                  :disabled="!cardParaVincular"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                >
                  Vincular
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const colunas = ref([
  { id: 'pendente', titulo: 'Pendente', cor: 'gray-300' },
  { id: 'em-progresso', titulo: 'Em Progresso', cor: 'yellow-400' },
  { id: 'em-revisao', titulo: 'Em Revisão', cor: 'blue-400' },
  { id: 'concluido', titulo: 'Concluído', cor: 'green-400' }
]);

const tarefas = ref([
  {
    id: '123',
    titulo: 'Redesenhar página de login',
    descricao: 'Atualizar o design da página de login para o novo padrão visual.',
    etiqueta: 'UI/UX', 
    etiquetaCor: 'purple',
    responsavel: 'MS',
    data: '15/07/2023',
    coluna: 'pendente',
    subtarefas: [
      { id: 1, texto: 'Criar wireframes', concluida: true },
      { id: 2, texto: 'Validar com stakeholders', concluida: false },
      { id: 3, texto: 'Implementar novo design', concluida: false }
    ],
    cardsVinculados: ['124', '125']
  },
  {
    id: '124',
    titulo: 'Implementar autenticação',
    descricao: 'Desenvolver sistema de autenticação com JWT e refresh tokens.',
    etiqueta: 'BE',
    etiquetaCor: 'blue',
    responsavel: 'JC',
    data: '20/07/2023',
    coluna: 'em-progresso',
    subtarefas: [
      { id: 4, texto: 'Configurar JWT', concluida: true },
      { id: 5, texto: 'Implementar refresh token', concluida: false },
      { id: 6, texto: 'Testes de segurança', concluida: false }
    ],
    cardsVinculados: ['123']
  },
  {
    id: '125', 
    titulo: 'Otimizar performance',
    descricao: 'Melhorar o tempo de carregamento das páginas principais.',
    etiqueta: 'FE',
    etiquetaCor: 'green',
    responsavel: 'RL',
    data: '25/07/2023',
    coluna: 'em-revisao',
    subtarefas: [
      { id: 7, texto: 'Análise de performance', concluida: true },
      { id: 8, texto: 'Lazy loading de componentes', concluida: true },
      { id: 9, texto: 'Otimização de imagens', concluida: false }
    ],
    cardsVinculados: ['123']
  },
  {
    id: '126',
    titulo: 'Corrigir bug no filtro',
    descricao: 'Resolver problema com filtros não funcionando corretamente.',
    etiqueta: 'BUG',
    etiquetaCor: 'red',
    responsavel: 'PF',
    data: '18/07/2023',
    coluna: 'concluido',
    subtarefas: [
      { id: 10, texto: 'Identificar causa', concluida: true },
      { id: 11, texto: 'Implementar correção', concluida: true },
      { id: 12, texto: 'Testes de regressão', concluida: true }
    ],
    cardsVinculados: []
  }
]);

// Estado do modal
const modalAberto = ref(false);
const tarefaSelecionada = ref(null);
const novaTarefa = ref('');
const termoPesquisa = ref('');
const novaColunaTitulo = ref('');
const novoCardTitulo = ref('');
const colunaEmEdicao = ref(null);
const cardParaVincular = ref('');

// Estado para o modal de nova coluna
const mostrarNovaColuna = ref(false);

// Computed properties para o modal
const progressoSubtarefas = computed(() => {
  if (!tarefaSelecionada.value?.subtarefas?.length) return 0;
  const total = tarefaSelecionada.value.subtarefas.length;
  const concluidas = tarefaSelecionada.value.subtarefas.filter(t => t.concluida).length;
  return Math.round((concluidas / total) * 100);
});

const cardsVinculadosDetalhes = computed(() => {
  if (!tarefaSelecionada.value?.cardsVinculados) return [];
  return tarefaSelecionada.value.cardsVinculados.map(id => 
    tarefas.value.find(t => t.id === id)
  ).filter(Boolean);
});

// Computed para filtrar tarefas baseado na pesquisa
const tarefasFiltradas = computed(() => {
  if (!termoPesquisa.value) return tarefas.value;
  
  const termo = termoPesquisa.value.toLowerCase();
  return tarefas.value.filter(tarefa => 
    tarefa.titulo.toLowerCase().includes(termo) ||
    tarefa.descricao.toLowerCase().includes(termo) ||
    tarefa.etiqueta.toLowerCase().includes(termo)
  );
});

// Computed para cards disponíveis para vincular
const cardsDisponiveis = computed(() => {
  if (!tarefaSelecionada.value) return [];
  return tarefas.value.filter(card => 
    card.id !== tarefaSelecionada.value.id && 
    !tarefaSelecionada.value.cardsVinculados.includes(card.id)
  );
});

// Funções do modal
const abrirModal = (tarefa) => {
  // Converte a data BR para o formato ISO para o input date
  const [dia, mes, ano] = tarefa.data.split('/');
  tarefa.dataRaw = `${ano}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}`;
  
  tarefaSelecionada.value = tarefa;
  modalAberto.value = true;
};

const fecharModal = () => {
  modalAberto.value = false;
  tarefaSelecionada.value = null;
};

const adicionarSubtarefa = () => {
  if (!novaTarefa.value.trim()) return;
  
  tarefaSelecionada.value.subtarefas.push({
    id: Date.now(),
    texto: novaTarefa.value,
    concluida: false
  });
  
  novaTarefa.value = '';
};

const toggleSubtarefa = (subtarefa) => {
  subtarefa.concluida = !subtarefa.concluida;
};

const handleDragStart = (e, tarefa) => {
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', tarefa.id);
  e.target.classList.add('opacity-50');
};

const handleDragEnd = (e) => {
  e.target.classList.remove('opacity-50');
};

const handleDragOver = (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  e.currentTarget.classList.add('drag-over');
};

const handleDragLeave = (e) => {
  e.currentTarget.classList.remove('drag-over');
};

const handleDrop = (e, colunaDestino) => {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
  
  const tarefaId = e.dataTransfer.getData('text/plain');
  const tarefa = tarefas.value.find(t => t.id === tarefaId);
  
  if (tarefa) {
    tarefa.coluna = colunaDestino;
  }
};

// Função para calcular o progresso das subtarefas
const calcularProgressoSubtarefas = (tarefa) => {
  if (!tarefa.subtarefas?.length) return 0;
  const total = tarefa.subtarefas.length;
  const concluidas = tarefa.subtarefas.filter(t => t.concluida).length;
  return Math.round((concluidas / total) * 100);
};

// Função para toggle do modal de nova coluna
const toggleNovaColuna = () => {
  mostrarNovaColuna.value = !mostrarNovaColuna.value;
  if (!mostrarNovaColuna.value) {
    novaColunaTitulo.value = '';
  }
};

// Modificar a função adicionarColuna
const adicionarColuna = () => {
  if (!novaColunaTitulo.value.trim()) return;
  
  const id = novaColunaTitulo.value.toLowerCase().replace(/\s+/g, '-');
  colunas.value.push({
    id,
    titulo: novaColunaTitulo.value,
    cor: 'gray-400'
  });
  
  novaColunaTitulo.value = '';
  mostrarNovaColuna.value = false;
};

// Função para adicionar novo card
const adicionarCard = (colunaId) => {
  if (!novoCardTitulo.value.trim()) return;
  
  const novoCard = {
    id: Date.now().toString(),
    titulo: novoCardTitulo.value,
    descricao: '',
    etiqueta: 'Nova',
    etiquetaCor: 'gray',
    responsavel: '',
    data: new Date().toLocaleDateString('pt-BR'),
    coluna: colunaId,
    subtarefas: [],
    cardsVinculados: []
  };
  
  tarefas.value.push(novoCard);
  novoCardTitulo.value = '';
  colunaEmEdicao.value = null;
};

// Modificar a função getTarefasPorColuna para usar tarefasFiltradas
const getTarefasPorColuna = (colunaId) => {
  return tarefasFiltradas.value.filter(tarefa => tarefa.coluna === colunaId);
};

const contarTarefasPorColuna = (colunaId) => {
  return getTarefasPorColuna(colunaId).length;
};

// Funções para vincular/desvincular cards
const vincularCard = () => {
  if (!cardParaVincular.value || !tarefaSelecionada.value) return;
  
  // Adiciona o vínculo bidirecional
  tarefaSelecionada.value.cardsVinculados.push(cardParaVincular.value);
  const cardVinculado = tarefas.value.find(t => t.id === cardParaVincular.value);
  if (cardVinculado && !cardVinculado.cardsVinculados.includes(tarefaSelecionada.value.id)) {
    cardVinculado.cardsVinculados.push(tarefaSelecionada.value.id);
  }
  
  cardParaVincular.value = '';
};

const desvincularCard = (cardId) => {
  if (!tarefaSelecionada.value) return;
  
  // Remove o vínculo bidirecional
  tarefaSelecionada.value.cardsVinculados = tarefaSelecionada.value.cardsVinculados.filter(id => id !== cardId);
  const cardVinculado = tarefas.value.find(t => t.id === cardId);
  if (cardVinculado) {
    cardVinculado.cardsVinculados = cardVinculado.cardsVinculados.filter(id => id !== tarefaSelecionada.value.id);
  }
};

// Função para atualizar a data formatada
const atualizarData = (event) => {
  if (!tarefaSelecionada.value) return;
  const data = new Date(event.target.value);
  tarefaSelecionada.value.data = data.toLocaleDateString('pt-BR');
};
</script>

<style scoped>
.kanban-card.opacity-50 {
  opacity: 0.5;
  transform: scale(0.98);
  transition: all 0.2s ease;
}

.kanban-column {
  transition: background-color 0.2s ease;
}

.kanban-column.drag-over {
  background-color: rgba(0, 0, 0, 0.05);
}

/* Transições do Modal */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from {
  opacity: 0;
  transform: scale(0.95);
}

.modal-leave-to {
  opacity: 0;
  transform: scale(1.05);
}

.modal-enter-to,
.modal-leave-from {
  opacity: 1;
  transform: scale(1);
}

.overflow-x-auto {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 20px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.7);
}

.kanban-cards {
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.kanban-cards::-webkit-scrollbar {
  width: 6px;
}

.kanban-cards::-webkit-scrollbar-track {
  background: transparent;
}

.kanban-cards::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 20px;
}

.kanban-cards::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.7);
}
</style> 