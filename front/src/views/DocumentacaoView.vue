<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Documentação dos Componentes</h1>

    <!-- Componentes Base -->
    <section class="mb-8">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Componentes Base</h2>
      
      <InnovPanel>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">InnovView</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Componente base para views, fornecendo um layout padrão com padding e espaçamento.
        </p>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;InnovView&gt;
  Conteúdo da view
&lt;/InnovView&gt;</code></pre>
        </div>
      </InnovPanel>

      <InnovPanel class="mt-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">InnovStatusBadge</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Badge para exibir status com cores diferentes.
        </p>
        <div class="flex gap-2 mb-4">
          <InnovStatusBadge status="success">Sucesso</InnovStatusBadge>
          <InnovStatusBadge status="warning">Atenção</InnovStatusBadge>
          <InnovStatusBadge status="error">Erro</InnovStatusBadge>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;InnovStatusBadge status="success"&gt;Sucesso&lt;/InnovStatusBadge&gt;</code></pre>
        </div>
      </InnovPanel>

      <InnovPanel class="mt-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">InnovStatCard</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Card para exibir estatísticas com ícone e valor.
        </p>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <InnovStatCard
            title="Total de Usuários"
            value="1,234"
            icon="users"
            color="primary"
          />
          <InnovStatCard
            title="Vendas Hoje"
            value="R$ 5.678"
            icon="chart"
            color="success"
          />
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;InnovStatCard
  title="Total de Usuários"
  value="1,234"
  icon="users"
  color="primary"
/&gt;</code></pre>
        </div>
      </InnovPanel>

      <InnovPanel class="mt-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">InnovRow e InnovCol</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Sistema de grid responsivo para layout.
        </p>
        <InnovRow cols="2" gap="4" class="mb-4">
          <InnovCol>
            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">Coluna 1</div>
          </InnovCol>
          <InnovCol>
            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">Coluna 2</div>
          </InnovCol>
        </InnovRow>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;InnovRow cols="2" gap="4"&gt;
  &lt;InnovCol&gt;Conteúdo 1&lt;/InnovCol&gt;
  &lt;InnovCol&gt;Conteúdo 2&lt;/InnovCol&gt;
&lt;/InnovRow&gt;</code></pre>
        </div>
      </InnovPanel>
    </section>

    <!-- Componentes de Formulário -->
    <section class="mb-8">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Componentes de Formulário</h2>
      
      <InnovPanel>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">TextInput</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Campo de entrada de texto com suporte a validação e feedback.
        </p>
        <div class="max-w-md mb-4">
          <TextInput
            v-model="exemploText"
            label="Nome"
            placeholder="Digite seu nome"
            :error="exemploTextError"
          />
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;TextInput
  v-model="valor"
  label="Nome"
  placeholder="Digite seu nome"
  :error="erro"
/&gt;</code></pre>
        </div>
      </InnovPanel>

      <InnovPanel class="mt-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">SelectSearch</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Select com suporte a pesquisa e múltipla seleção.
        </p>
        <div class="max-w-md mb-4">
          <SelectSearch
            v-model="exemploSelect"
            :options="opcoes"
            label="Categoria"
            placeholder="Selecione uma categoria"
          />
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;SelectSearch
  v-model="valor"
  :options="opcoes"
  label="Categoria"
  placeholder="Selecione uma categoria"
/&gt;</code></pre>
        </div>
      </InnovPanel>

      <InnovPanel class="mt-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">CheckboxGroup</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Grupo de checkboxes com layout personalizado.
        </p>
        <div class="max-w-md mb-4">
          <CheckboxGroup
            v-model="exemploCheckbox"
            :options="opcoesCheckbox"
            label="Interesses"
          />
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;CheckboxGroup
  v-model="valor"
  :options="opcoes"
  label="Interesses"
/&gt;</code></pre>
        </div>
      </InnovPanel>
    </section>

    <!-- Componentes de Dados -->
    <section class="mb-8">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Componentes de Dados</h2>
      
      <InnovPanel>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">DataTable</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          Tabela de dados com paginação, ordenação e filtros.
        </p>
        <DataTable
          :data="dadosExemplo"
          :columns="colunasExemplo"
          :items-per-page="5"
        />
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mt-4">
          <pre class="text-sm text-gray-800 dark:text-gray-200">
<code>&lt;DataTable
  :data="dados"
  :columns="colunas"
  :items-per-page="5"
/&gt;</code></pre>
        </div>
      </InnovPanel>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import InnovView from '@/components/base/InnovView.vue';
import InnovPanel from '@/components/base/InnovPanel.vue';
import InnovRow from '@/components/base/InnovRow.vue';
import InnovCol from '@/components/base/InnovCol.vue';
import InnovStatusBadge from '@/components/base/InnovStatusBadge.vue';
import InnovStatCard from '@/components/base/InnovStatCard.vue';
import TextInput from '@/components/form/TextInput.vue';
import SelectSearch from '@/components/form/SelectSearch.vue';
import CheckboxGroup from '@/components/form/CheckboxGroup.vue';
import DataTable from '@/components/data/DataTable.vue';

// Estados para exemplos
const exemploText = ref('');
const exemploTextError = ref('');
const exemploSelect = ref('');
const exemploCheckbox = ref([]);

// Opções para exemplos
const opcoes = [
  { value: '1', label: 'Opção 1' },
  { value: '2', label: 'Opção 2' },
  { value: '3', label: 'Opção 3' }
];

const opcoesCheckbox = [
  { value: '1', label: 'Opção 1' },
  { value: '2', label: 'Opção 2' },
  { value: '3', label: 'Opção 3' }
];

// Dados para exemplo do DataTable
const dadosExemplo = ref([
  { id: 1, nome: 'Item 1', status: 'Ativo' },
  { id: 2, nome: 'Item 2', status: 'Inativo' },
  { id: 3, nome: 'Item 3', status: 'Ativo' }
]);

const colunasExemplo = [
  { key: 'id', label: 'ID' },
  { key: 'nome', label: 'Nome' },
  { 
    key: 'status', 
    label: 'Status',
    template: (item) => `
      <span class="px-2 py-1 text-xs font-medium rounded-full ${
        item.status === 'Ativo' 
          ? 'bg-green-100 text-green-800' 
          : 'bg-red-100 text-red-800'
      }">
        ${item.status}
      </span>
    `
  }
];
</script> 