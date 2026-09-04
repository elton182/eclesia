<template>
    <div class="space-y-6">
      <!-- Cabeçalho -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Chamados</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gerencie seus chamados e solicitações</p>
          </div>
          <div class="flex-1 max-w-md">
            <div class="relative">
              <input
                v-model="termoPesquisa"
                type="text"
                placeholder="Pesquisar chamados..."
                class="w-full text-black pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
            </div>
          </div>
          <button 
            @click="abrirModalNovoChamado"
            class="button px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors flex items-center gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Novo Chamado</span>
          </button>
        </div>
      </div>
  
      <!-- Filtros -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <div class="flex flex-wrap gap-4 justify-between items-center">
          <div class="flex flex-wrap gap-2">
            <div class="relative">
              <select 
                v-model="filtroStatus"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">Todos os Status</option>
                <option value="aberto">Aberto</option>
                <option value="em_andamento">Em Andamento</option>
                <option value="pendente">Pendente</option>
                <option value="resolvido">Resolvido</option>
                <option value="fechado">Fechado</option>
              </select>
            </div>
            <div class="relative">
              <select 
                v-model="filtroPrioridade"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">Todas as Prioridades</option>
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
                <option value="critica">Crítica</option>
              </select>
            </div>
            <div class="relative">
              <select 
                v-model="filtroCategoria"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">Todas as Categorias</option>
                <option value="bug">Bug</option>
                <option value="feature">Nova Funcionalidade</option>
                <option value="support">Suporte</option>
                <option value="documentation">Documentação</option>
              </select>
            </div>
          </div>
          
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ chamadosFiltrados.length }} chamados encontrados
            </span>
            <select 
              v-model="ordenacao"
              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
              <option value="recentes">Mais Recentes</option>
              <option value="antigos">Mais Antigos</option>
              <option value="prioridade">Prioridade</option>
              <option value="status">Status</option>
            </select>
          </div>
        </div>
      </div>
  
      <!-- DataTable -->
      <DataTable 
        :data="chamadosFiltrados" 
        :columns="colunas"
        :items-per-page="10"
      />
    </div>
  </template>
  
  <script setup>
  import { ref, computed } from 'vue';
  import { useRouter } from 'vue-router';
  import DataTable from '@/components/data/DataTable.vue';
  
  const router = useRouter();
  
  // Estados
  const termoPesquisa = ref('');
  const filtroStatus = ref('');
  const filtroPrioridade = ref('');
  const filtroCategoria = ref('');
  const ordenacao = ref('recentes');
  
  // Dados mockados
  const chamados = ref([
    {
      id: '1001',
      titulo: 'Erro ao salvar formulário',
      descricao: 'Usuários relatam erro 500 ao tentar salvar o formulário de cadastro de clientes.',
      status: 'aberto',
      prioridade: 'alta',
      categoria: 'bug',
      responsavel: 'João Silva',
      data: '2024-03-15T10:30:00',
      comentarios: [
        {
          autor: 'Maria Santos',
          texto: 'Identificado problema na validação do campo CPF',
          data: '2024-03-15T11:00:00'
        }
      ]
    },
    {
      id: '1002',
      titulo: 'Implementar dark mode',
      descricao: 'Adicionar suporte ao tema escuro em todas as páginas do sistema.',
      status: 'em_andamento',
      prioridade: 'media',
      categoria: 'feature',
      responsavel: 'Ana Costa',
      data: '2024-03-14T15:45:00',
      comentarios: []
    },
    {
      id: '1003',
      titulo: 'Atualizar documentação API',
      descricao: 'Documentação da API precisa ser atualizada com os novos endpoints.',
      status: 'pendente',
      prioridade: 'baixa',
      categoria: 'documentation',
      responsavel: 'Pedro Oliveira',
      data: '2024-03-13T09:15:00',
      comentarios: []
    }
  ]);
  
  // Computed properties
  const chamadosFiltrados = computed(() => {
    let resultado = chamados.value;
  
    // Aplicar filtro de pesquisa
    if (termoPesquisa.value) {
      const termo = termoPesquisa.value.toLowerCase();
      resultado = resultado.filter(chamado => 
        chamado.titulo.toLowerCase().includes(termo) ||
        chamado.descricao.toLowerCase().includes(termo)
      );
    }
  
    // Aplicar filtros
    if (filtroStatus.value) {
      resultado = resultado.filter(chamado => chamado.status === filtroStatus.value);
    }
    if (filtroPrioridade.value) {
      resultado = resultado.filter(chamado => chamado.prioridade === filtroPrioridade.value);
    }
    if (filtroCategoria.value) {
      resultado = resultado.filter(chamado => chamado.categoria === filtroCategoria.value);
    }
  
    // Aplicar ordenação
    resultado = [...resultado].sort((a, b) => {
      switch (ordenacao.value) {
        case 'recentes':
          return new Date(b.data) - new Date(a.data);
        case 'antigos':
          return new Date(a.data) - new Date(b.data);
        case 'prioridade':
          return getPrioridadePeso(b.prioridade) - getPrioridadePeso(a.prioridade);
        case 'status':
          return getStatusPeso(b.status) - getStatusPeso(a.status);
        default:
          return 0;
      }
    });
  
    return resultado;
  });
  
  // Configuração das colunas
  const colunas = [
    { 
      key: 'id', 
      label: 'ID',
      template: (item) => `#${item.id}`
    },
    { 
      key: 'titulo', 
      label: 'Título',
      template: (item) => `
        <div>
          <div class="font-medium text-gray-900 dark:text-white">${item.titulo}</div>
          <div class="text-sm text-gray-500 dark:text-gray-400">${item.descricao.substring(0, 60)}...</div>
        </div>
      `
    },
    { 
      key: 'status', 
      label: 'Status',
      template: (item) => `
        <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusClass(item.status)}">
          ${statusFormatado(item.status)}
        </span>
      `
    },
    { 
      key: 'prioridade', 
      label: 'Prioridade',
      template: (item) => `
        <span class="px-2 py-1 text-xs font-medium rounded-full ${getPrioridadeClass(item.prioridade)}">
          ${prioridadeFormatada(item.prioridade)}
        </span>
      `
    },
    { 
      key: 'categoria', 
      label: 'Categoria',
      template: (item) => `
        <span class="px-2 py-1 text-xs font-medium rounded-full ${getCategoriaClass(item.categoria)}">
          ${categoriaFormatada(item.categoria)}
        </span>
      `
    },
    { 
      key: 'responsavel', 
      label: 'Responsável',
      template: (item) => `
        <div class="flex items-center">
          <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            ${item.responsavel.split(' ').map(n => n[0]).join('')}
          </div>
          <div class="ml-2">
            <div class="text-sm font-medium text-gray-900 dark:text-white">${item.responsavel}</div>
          </div>
        </div>
      `
    },
    { 
      key: 'data', 
      label: 'Data',
      format: (value) => formatarData(value)
    },
    {
      key: 'acoes',
      label: 'Ações',
      template: (item) => `
        <router-link 
          to="/chamados/${item.id}" 
          class="text-primary-600 hover:text-primary-900 dark:text-primary-500 dark:hover:text-primary-400"
        >
          Ver Detalhes
        </router-link>
      `
    }
  ];
  
  // Funções auxiliares
  const formatarData = (data) => {
    return new Date(data).toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };
  
  const statusFormatado = (status) => {
    const formatos = {
      aberto: 'Aberto',
      em_andamento: 'Em Andamento',
      pendente: 'Pendente',
      resolvido: 'Resolvido',
      fechado: 'Fechado'
    };
    return formatos[status] || status;
  };
  
  const prioridadeFormatada = (prioridade) => {
    const formatos = {
      baixa: 'Baixa',
      media: 'Média',
      alta: 'Alta',
      critica: 'Crítica'
    };
    return formatos[prioridade] || prioridade;
  };
  
  const categoriaFormatada = (categoria) => {
    const formatos = {
      bug: 'Bug',
      feature: 'Nova Funcionalidade',
      support: 'Suporte',
      documentation: 'Documentação'
    };
    return formatos[categoria] || categoria;
  };
  
  const getStatusClass = (status) => {
    const classes = {
      aberto: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      em_andamento: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      pendente: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
      resolvido: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      fechado: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    };
    return classes[status] || '';
  };
  
  const getPrioridadeClass = (prioridade) => {
    const classes = {
      baixa: 'bg-gray-100 text-gray-800',
      media: 'bg-blue-100 text-blue-800',
      alta: 'bg-orange-100 text-orange-800',
      critica: 'bg-red-100 text-red-800'
    };
    return classes[prioridade] || '';
  };
  
  const getCategoriaClass = (categoria) => {
    const classes = {
      bug: 'bg-red-100 text-red-800',
      feature: 'bg-purple-100 text-purple-800',
      support: 'bg-blue-100 text-blue-800',
      documentation: 'bg-green-100 text-green-800'
    };
    return classes[categoria] || '';
  };
  
  const getPrioridadePeso = (prioridade) => {
    const pesos = {
      critica: 4,
      alta: 3,
      media: 2,
      baixa: 1
    };
    return pesos[prioridade] || 0;
  };
  
  const getStatusPeso = (status) => {
    const pesos = {
      aberto: 5,
      em_andamento: 4,
      pendente: 3,
      resolvido: 2,
      fechado: 1
    };
    return pesos[status] || 0;
  };
  
  // Funções de ação
  const abrirModalNovoChamado = () => {
    // Implementar lógica do modal de novo chamado
  };
  </script>
  
  <style scoped>
  .table-row-link {
    cursor: pointer;
  }
  
  .table-row-link:hover {
    background-color: rgba(0, 0, 0, 0.05);
  }
  </style> 