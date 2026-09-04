<template>
    <InnovView
      title="Chamado #{{ chamado.id }}"
      subtitle="Criado em {{ formatarData(chamado.data) }}"
    >
      <InnovRow cols="3" gap="6">
        <!-- Coluna Principal -->
        <InnovCol :span="2">
          <!-- Detalhes do Chamado -->
          <InnovPanel>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">{{ chamado.titulo }}</h2>
            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ chamado.descricao }}</p>
            
            <!-- Anexos -->
            <div class="mt-6">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Anexos</h3>
              <div class="space-y-2">
                <div 
                  v-for="anexo in chamado.anexos" 
                  :key="anexo.id"
                  class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                  <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ anexo.nome }}</span>
                  </div>
                  <button class="text-primary-600 hover:text-primary-700 text-sm">
                    Download
                  </button>
                </div>
              </div>
            </div>
          </InnovPanel>
  
          <!-- Timeline de Atividades -->
          <InnovPanel>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Atividades</h2>
            <div class="space-y-6">
              <div 
                v-for="atividade in chamado.atividades" 
                :key="atividade.id"
                class="relative pl-8 pb-6 last:pb-0"
              >
                <!-- Linha vertical -->
                <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                <!-- Círculo -->
                <div class="absolute left-0 top-1.5 w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900 border-2 border-primary-500 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                </div>
                <!-- Conteúdo -->
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-gray-900 dark:text-white">{{ atividade.autor }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatarData(atividade.data) }}</span>
                  </div>
                  <p class="text-gray-700 dark:text-gray-300">{{ atividade.descricao }}</p>
                </div>
              </div>
            </div>
          </InnovPanel>
  
          <!-- Adicionar Comentário -->
          <InnovPanel>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Adicionar Comentário</h2>
            <div class="space-y-4">
              <textarea
                v-model="novoComentario"
                rows="4"
                class="w-full text-black border border-gray-300 rounded-lg p-3 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                placeholder="Digite seu comentário..."
              ></textarea>
              <div class="flex justify-end">
                <button 
                  @click="adicionarComentario"
                  class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors"
                >
                  Enviar Comentário
                </button>
              </div>
            </div>
          </InnovPanel>
        </InnovCol>
  
        <!-- Barra Lateral -->
        <InnovCol>
          <!-- Detalhes -->
          <InnovPanel>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Detalhes</h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select 
                  v-model="chamado.status"
                  class="mt-1 w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="aberto">Aberto</option>
                  <option value="em_andamento">Em Andamento</option>
                  <option value="pendente">Pendente</option>
                  <option value="resolvido">Resolvido</option>
                  <option value="fechado">Fechado</option>
                </select>
              </div>
              
              <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade</label>
                <select 
                  v-model="chamado.prioridade"
                  class="mt-1 w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="baixa">Baixa</option>
                  <option value="media">Média</option>
                  <option value="alta">Alta</option>
                  <option value="critica">Crítica</option>
                </select>
              </div>
  
              <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                <select 
                  v-model="chamado.categoria"
                  class="mt-1 w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="bug">Bug</option>
                  <option value="feature">Nova Funcionalidade</option>
                  <option value="support">Suporte</option>
                  <option value="documentation">Documentação</option>
                </select>
              </div>
  
              <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Responsável</label>
                <select 
                  v-model="chamado.responsavel"
                  class="mt-1 w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="João Silva">João Silva</option>
                  <option value="Ana Costa">Ana Costa</option>
                  <option value="Pedro Oliveira">Pedro Oliveira</option>
                  <option value="Maria Santos">Maria Santos</option>
                </select>
              </div>
  
              <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Data Limite</label>
                <input 
                  type="date"
                  v-model="chamado.dataLimite"
                  class="mt-1 w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
              </div>
            </div>
          </InnovPanel>
  
          <!-- Chamados Relacionados -->
          <InnovPanel>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Chamados Relacionados</h2>
            <div class="space-y-3">
              <div 
                v-for="chamadoRelacionado in chamado.chamadosRelacionados" 
                :key="chamadoRelacionado.id"
                class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <router-link 
                      :to="{ name: 'detalhe-chamado', params: { id: chamadoRelacionado.id }}"
                      class="text-primary-600 hover:text-primary-700 font-medium"
                    >
                      #{{ chamadoRelacionado.id }}
                    </router-link>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ chamadoRelacionado.titulo }}</p>
                  </div>
                  <InnovStatusBadge 
                    :status="chamadoRelacionado.status"
                    :label="statusFormatado(chamadoRelacionado.status)"
                  />
                </div>
              </div>
            </div>
          </InnovPanel>
        </InnovCol>
      </InnovRow>
    </InnovView>
  </template>
  
  <script setup>
  import { ref, onMounted } from 'vue';
  import { useRouter, useRoute } from 'vue-router';
  import InnovView from '@/components/base/InnovView.vue';
  import InnovPanel from '@/components/base/InnovPanel.vue';
  import InnovRow from '@/components/base/InnovRow.vue';
  import InnovCol from '@/components/base/InnovCol.vue';
  import InnovStatusBadge from '@/components/base/InnovStatusBadge.vue';
  
  const router = useRouter();
  const route = useRoute();
  
  // Estado
  const novoComentario = ref('');
  
  // Dados mockados do chamado
  const chamado = ref({
    id: '1001',
    titulo: 'Erro ao salvar formulário',
    descricao: 'Usuários relatam erro 500 ao tentar salvar o formulário de cadastro de clientes. O erro ocorre especificamente quando há campos com caracteres especiais.\n\nPassos para reproduzir:\n1. Acessar o formulário de cadastro\n2. Preencher campos com caracteres especiais\n3. Tentar salvar',
    status: 'aberto',
    prioridade: 'alta',
    categoria: 'bug',
    responsavel: 'João Silva',
    data: '2024-03-15T10:30:00',
    dataLimite: '2024-03-20',
    anexos: [
      { id: 1, nome: 'erro_screenshot.png' },
      { id: 2, nome: 'log_erro.txt' }
    ],
    atividades: [
      {
        id: 1,
        autor: 'Maria Santos',
        data: '2024-03-15T11:00:00',
        descricao: 'Identificado problema na validação do campo CPF'
      },
      {
        id: 2,
        autor: 'João Silva',
        data: '2024-03-15T11:30:00',
        descricao: 'Iniciando análise do código de validação'
      }
    ],
    chamadosRelacionados: [
      {
        id: '1002',
        titulo: 'Atualizar validação de formulários',
        status: 'em_andamento'
      },
      {
        id: '1003',
        titulo: 'Documentar padrões de validação',
        status: 'pendente'
      }
    ]
  });
  
  // Funções
  const voltar = () => {
    router.push({ name: 'chamados' });
  };
  
  const editarChamado = () => {
    // Implementar lógica de edição
  };
  
  const adicionarComentario = () => {
    if (!novoComentario.value.trim()) return;
  
    chamado.value.atividades.unshift({
      id: Date.now(),
      autor: 'Usuário Atual', // Substituir pelo usuário logado
      data: new Date().toISOString(),
      descricao: novoComentario.value
    });
  
    novoComentario.value = '';
  };
  
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
  
  // Lifecycle
  onMounted(() => {
    // Aqui você faria uma chamada à API para buscar os dados do chamado
    // usando o ID da rota (route.params.id)
  });
  </script>
  
  <style scoped>
  .timeline-item:last-child .timeline-line {
    display: none;
  }
  </style> 
  