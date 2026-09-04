<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Exemplo de DataTable</h1>
    
    <DataTable 
      :data="dados" 
      :columns="colunas"
      :items-per-page="5"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import DataTable from '@/components/data/DataTable.vue';

// Dados de exemplo
const dados = ref([
  { id: 1, nome: 'João Silva', email: 'joao@exemplo.com', telefone: '(11) 98765-4321', status: 'Ativo', data: '2023-01-15' },
  { id: 2, nome: 'Maria Souza', email: 'maria@exemplo.com', telefone: '(21) 99876-5432', status: 'Inativo', data: '2023-02-22' },
  { id: 3, nome: 'Pedro Santos', email: 'pedro@exemplo.com', telefone: '(31) 97654-3210', status: 'Pendente', data: '2023-03-10' },
  { id: 4, nome: 'Ana Oliveira', email: 'ana@exemplo.com', telefone: '(41) 98765-0987', status: 'Ativo', data: '2023-04-05' },
  { id: 5, nome: 'Carlos Ferreira', email: 'carlos@exemplo.com', telefone: '(51) 96543-2109', status: 'Ativo', data: '2023-05-18' },
  { id: 6, nome: 'Juliana Costa', email: 'juliana@exemplo.com', telefone: '(61) 95432-1098', status: 'Inativo', data: '2023-06-27' },
  { id: 7, nome: 'Roberto Alves', email: 'roberto@exemplo.com', telefone: '(71) 94321-0987', status: 'Pendente', data: '2023-07-14' },
  { id: 8, nome: 'Patricia Lima', email: 'patricia@exemplo.com', telefone: '(81) 93210-9876', status: 'Ativo', data: '2023-08-09' },
  { id: 9, nome: 'Bruno Martins', email: 'bruno@exemplo.com', telefone: '(91) 92109-8765', status: 'Inativo', data: '2023-09-30' },
  { id: 10, nome: 'Luciana Pereira', email: 'luciana@exemplo.com', telefone: '(12) 91098-7654', status: 'Ativo', data: '2023-10-22' },
  { id: 11, nome: 'Gabriel Nunes', email: 'gabriel@exemplo.com', telefone: '(22) 90987-6543', status: 'Pendente', data: '2023-11-11' },
  { id: 12, nome: 'Fernanda Rocha', email: 'fernanda@exemplo.com', telefone: '(32) 90876-5432', status: 'Ativo', data: '2023-12-03' },
]);

// Definição das colunas
const colunas = ref([
  { key: 'id', label: 'ID' },
  { key: 'nome', label: 'Nome' },
  { key: 'email', label: 'E-mail' },
  { key: 'telefone', label: 'Telefone' },
  { 
    key: 'data', 
    label: 'Data', 
    format: (value) => {
      // Formatando a data para o padrão brasileiro
      const date = new Date(value);
      return date.toLocaleDateString('pt-BR');
    }
  },
  { 
    key: 'status', 
    label: 'Status',
    template: (item) => {
      // Classes personalizadas com base no status
      const classes = {
        'Ativo': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'Inativo': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'Pendente': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
      };
      
      return `<span class="px-2 py-1 rounded-full text-xs font-medium ${classes[item.status]}">${item.status}</span>`;
    }
  }
]);
</script> 