<template>
  <div class="space-y-6">

    <InnovPanel>
     
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <div class="flex items-center">
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sistema de Gestão Empresarial</h1>
              <span
                class="ml-3 px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                Em Andamento
              </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Iniciado em 15/03/2023 • Prazo: 30/12/2023</p>
          </div>
          <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <button class="button px-4 py-2 bg-primary-600 text-black dark:text-white rounded-md hover:bg-primary-700 transition-colors">
              Editar Projeto
            </button>
            <button
              class="button px-4 py-2 bg-primary-200 dark:bg-secondary-700 text-secondary-800 dark:text-secondary-200 rounded-md hover:bg-secondary-300 dark:hover:bg-secondary-600 transition-colors">
              Compartilhar
            </button>
          </div>
        </div>
     
    </InnovPanel>



    <!-- Informações do Projeto e Progresso -->
    <InnovRow cols="2" lg="3" gap="6">
      <!-- Detalhes do Projeto -->
      <InnovCol span="1" lg="2">
        <InnovPanel height="full">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Detalhes do Projeto</h2>
          <div class="prose dark:prose-invert max-w-none text-black dark:text-white">
            <p>
              Este projeto visa desenvolver um sistema completo de gestão empresarial que integra módulos de finanças,
              recursos humanos, estoque, vendas e atendimento ao cliente. A solução será implementada utilizando
              arquitetura de microsserviços e interface moderna com Vue.js.
            </p>
            <h3>Objetivos</h3>
            <ul>
              <li>Automatizar processos internos da empresa</li>
              <li>Centralizar informações em um único sistema</li>
              <li>Melhorar a experiência do usuário com interface intuitiva</li>
              <li>Permitir acesso via dispositivos móveis</li>
              <li>Implementar dashboards analíticos para tomada de decisão</li>
            </ul>
            <h3>Escopo</h3>
            <p>
              O sistema incluirá os seguintes módulos: Financeiro, RH, Estoque, Vendas, CRM,
              Relatórios e Administração. Cada módulo terá suas próprias funcionalidades e
              permissões de acesso.
            </p>
          </div>
        </InnovPanel>
      </InnovCol>

      <!-- Progresso e Estatísticas -->
      <InnovCol span="1" lg="1">
        <InnovPanel height="full">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Progresso</h2>

          <!-- Progresso Geral -->
          <div class="mb-6">
            <InnovProgressBar
              :value="projeto.progresso"
              label="Progresso Geral"
              color="primary"
            />
          </div>

          <!-- Estatísticas -->
          <InnovRow cols="2" gap="4">
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
              <div class="text-2xl font-bold text-gray-900 dark:text-white">42</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Tarefas Totais</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">27</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Concluídas</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
              <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">10</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Em Progresso</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
              <div class="text-2xl font-bold text-red-600 dark:text-red-400">5</div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Atrasadas</div>
            </div>
          </InnovRow>
      

          <!-- Próximos Marcos -->
          <div class="mt-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Próximos Marcos</h3>
            <ul class="space-y-3">
              <li class="flex items-center">
                <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                <span class="text-sm text-gray-700 dark:text-gray-300">Entrega do Módulo Financeiro -
                  15/07/2023</span>
              </li>
              <li class="flex items-center">
                <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                <span class="text-sm text-gray-700 dark:text-gray-300">Testes de Integração - 30/08/2023</span>
              </li>
              <li class="flex items-center">
                <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                <span class="text-sm text-gray-700 dark:text-gray-300">Beta Release - 15/10/2023</span>
              </li>
            </ul>
          </div>
        </InnovPanel>
      </InnovCol>
    </InnovRow>


    <!-- Equipe e Tarefas -->
    <InnovRow cols="1" lg="3" gap="6">
      <!-- Membros da Equipe -->
      <InnovCol span="1" lg="1">
        <InnovPanel height="full">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Equipe</h2>
            <button
              class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
              Ver todos
            </button>
          </div>

          <ul class="space-y-4">
            <li class="flex items-center space-x-3">
              <div
                class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold">
                AM
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  Ana Maria Silva
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  Gerente de Projeto
                </p>
              </div>
            </li>
            <li class="flex items-center space-x-3">
              <div
                class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold">
                JC
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  João Carlos Oliveira
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  Desenvolvedor Front-end
                </p>
              </div>
            </li>
            <li class="flex items-center space-x-3">
              <div
                class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold">
                RL
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  Roberta Lima
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  Desenvolvedora Back-end
                </p>
              </div>
            </li>
            <li class="flex items-center space-x-3">
              <div
                class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-semibold">
                MS
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  Marcos Santos
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  UX/UI Designer
                </p>
              </div>
            </li>
            <li class="flex items-center space-x-3">
              <div
                class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-700 font-semibold">
                PF
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  Paula Ferreira
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                  QA Tester
                </p>
              </div>
            </li>
          </ul>
        </InnovPanel>
      </InnovCol>

      <!-- Tarefas Recentes -->
      <InnovCol span="1" lg="2">
        <InnovPanel height="full">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Tarefas Recentes</h2>
            <button
              class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
              Ver todas
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Tarefa
                  </th>
                  <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Responsável
                  </th>
                  <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Prazo
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Implementar autenticação</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Roberta Lima</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                      Concluído
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    10/06/2023
                  </td>
                </tr>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Desenvolver dashboard</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">João Carlos</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                      Em Progresso
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    20/06/2023
                  </td>
                </tr>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Design de interface do módulo
                      financeiro</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Marcos Santos</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                      Em Progresso
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    25/06/2023
                  </td>
                </tr>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Testes de integração API</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Paula Ferreira</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                      Atrasado
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    05/06/2023
                  </td>
                </tr>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Documentação da API</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Roberta Lima</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                      Pendente
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    30/06/2023
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </InnovPanel>
      </InnovCol>
    </InnovRow>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InnovRow from '@/components/base/InnovRow.vue';
import InnovCol from '@/components/base/InnovCol.vue';
import InnovPanel from '@/components/base/InnovPanel.vue';
import InnovStatusBadge from '@/components/base/InnovStatusBadge.vue';
import InnovProgressBar from '@/components/base/InnovProgressBar.vue';
import InnovStatCard from '@/components/base/InnovStatCard.vue';

const router = useRouter();
const route = useRoute();

// Estado do projeto
const projeto = ref({
  id: 'PRJ-001',
  nome: 'Sistema de Gestão Empresarial',
  status: 'em_andamento',
  dataInicio: '2024-03-15',
  dataFim: '2024-12-30',
  progresso: 65,
  descricao: `Este projeto visa desenvolver um sistema completo de gestão empresarial que integra módulos de finanças, 
    recursos humanos, estoque, vendas e atendimento ao cliente. A solução será implementada utilizando 
    arquitetura de microsserviços e interface moderna com Vue.js.`,
  objetivos: [
    'Automatizar processos internos da empresa',
    'Centralizar informações em um único sistema',
    'Melhorar a experiência do usuário com interface intuitiva',
    'Permitir acesso via dispositivos móveis',
    'Implementar dashboards analíticos para tomada de decisão'
  ],
  escopo: `O sistema incluirá os seguintes módulos: Financeiro, RH, Estoque, Vendas, CRM, 
    Relatórios e Administração. Cada módulo terá suas próprias funcionalidades e 
    permissões de acesso.`,
  estatisticas: {
    total: 42,
    concluidas: 27,
    emProgresso: 10,
    atrasadas: 5
  },
  marcos: [
    {
      id: 1,
      titulo: 'Entrega do Módulo Financeiro',
      data: '2024-07-15',
      status: 'pendente'
    },
    {
      id: 2,
      titulo: 'Testes de Integração',
      data: '2024-08-30',
      status: 'pendente'
    },
    {
      id: 3,
      titulo: 'Beta Release',
      data: '2024-10-15',
      status: 'pendente'
    }
  ],
  equipe: [
    {
      id: 1,
      nome: 'Ana Maria Silva',
      cargo: 'Gerente de Projeto',
      iniciais: 'AM',
      cor: 'primary'
    },
    {
      id: 2,
      nome: 'João Carlos Oliveira',
      cargo: 'Desenvolvedor Front-end',
      iniciais: 'JC',
      cor: 'green'
    },
    {
      id: 3,
      nome: 'Roberta Lima',
      cargo: 'Desenvolvedora Back-end',
      iniciais: 'RL',
      cor: 'blue'
    },
    {
      id: 4,
      nome: 'Marcos Santos',
      cargo: 'UX/UI Designer',
      iniciais: 'MS',
      cor: 'purple'
    },
    {
      id: 5,
      nome: 'Paula Ferreira',
      cargo: 'QA Tester',
      iniciais: 'PF',
      cor: 'yellow'
    }
  ],
  tarefas: [
    {
      id: 1,
      titulo: 'Implementar autenticação',
      responsavel: 'Roberta Lima',
      status: 'concluido',
      prazo: '2024-06-10'
    },
    {
      id: 2,
      titulo: 'Desenvolver dashboard',
      responsavel: 'João Carlos',
      status: 'em_progresso',
      prazo: '2024-06-20'
    },
    {
      id: 3,
      titulo: 'Design de interface do módulo financeiro',
      responsavel: 'Marcos Santos',
      status: 'em_progresso',
      prazo: '2024-06-25'
    },
    {
      id: 4,
      titulo: 'Testes de integração API',
      responsavel: 'Paula Ferreira',
      status: 'atrasado',
      prazo: '2024-06-05'
    },
    {
      id: 5,
      titulo: 'Documentação da API',
      responsavel: 'Roberta Lima',
      status: 'pendente',
      prazo: '2024-06-30'
    }
  ]
});

// Funções
const formatarData = (data) => {
  return new Date(data).toLocaleDateString('pt-BR');
};

const editarProjeto = () => {
  // Implementar lógica de edição
  router.push({ name: 'editar-projeto', params: { id: projeto.value.id } });
};

const compartilharProjeto = () => {
  // Implementar lógica de compartilhamento
};

const verTodasTarefas = () => {
  // Implementar navegação para lista completa de tarefas
  router.push({ name: 'tarefas-projeto', params: { id: projeto.value.id } });
};

const verTodosMembros = () => {
  // Implementar navegação para lista completa de membros
  router.push({ name: 'equipe-projeto', params: { id: projeto.value.id } });
};

// Lifecycle
onMounted(() => {
  // Aqui você faria uma chamada à API para buscar os dados do projeto
  // usando o ID da rota (route.params.id)
});
</script>