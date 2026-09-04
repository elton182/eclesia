<script setup>
import {ref} from 'vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {
    faShoppingCart,
    faUsers,
    faPercentage,
    faTag,
    faArrowUp,
    faArrowDown,
    faArrowRight
} from '@fortawesome/free-solid-svg-icons';
import {library} from '@fortawesome/fontawesome-svg-core';

library.add(faShoppingCart, faUsers, faPercentage, faTag, faArrowUp, faArrowDown, faArrowRight);

// Dados para os cartões
const cards = ref([
    {
        title: 'Total de Vendas',
        value: 'R$ 45.680',
        icon: 'fa-shopping-cart',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600',
        trend: '+12,5%',
        trendIcon: 'fa-arrow-up',
        trendColor: 'text-green-600 dark:text-green-400'
    },
    {
        title: 'Novos Clientes',
        value: '385',
        icon: 'fa-users',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600',
        trend: '+8,2%',
        trendIcon: 'fa-arrow-up',
        trendColor: 'text-green-600 dark:text-green-400'
    },
    {
        title: 'Taxa de Conversão',
        value: '3,48%',
        icon: 'fa-percentage',
        bgColor: 'bg-yellow-100',
        textColor: 'text-yellow-600',
        trend: '+2,8%',
        trendIcon: 'fa-arrow-up',
        trendColor: 'text-green-600 dark:text-green-400'
    },
    {
        title: 'Ticket Médio',
        value: 'R$ 118,50',
        icon: 'fa-tag',
        bgColor: 'bg-purple-100',
        textColor: 'text-purple-600',
        trend: '-1,2%',
        trendIcon: 'fa-arrow-down',
        trendColor: 'text-red-600 dark:text-red-400'
    }
]);

// Dados para a tabela
const recentOrders = ref([
    {
        id: '#ORD-00123',
        customer: 'João Silva',
        product: 'Produto Premium',
        date: '12/03/2023',
        amount: 'R$ 1.250,00',
        status: 'Completo'
    },
    {
        id: '#ORD-00124',
        customer: 'Maria Souza',
        product: 'Serviço Anual',
        date: '10/03/2023',
        amount: 'R$ 4.500,00',
        status: 'Pendente'
    },
    {
        id: '#ORD-00125',
        customer: 'Pedro Oliveira',
        product: 'Produto Standard',
        date: '09/03/2023',
        amount: 'R$ 750,00',
        status: 'Processando'
    },
    {
        id: '#ORD-00126',
        customer: 'Ana Costa',
        product: 'Produto Plus',
        date: '08/03/2023',
        amount: 'R$ 1.000,00',
        status: 'Completo'
    },
    {
        id: '#ORD-00127',
        customer: 'Lucas Santos',
        product: 'Serviço Mensal',
        date: '07/03/2023',
        amount: 'R$ 650,00',
        status: 'Cancelado'
    },
]);

const getStatusClass = (status) => {
    switch (status) {
        case 'Completo':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'Pendente':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'Processando':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'Cancelado':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
};

// Dados para o gráfico (seria implementado com uma biblioteca de gráficos como Chart.js)
</script>

<template>
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-3">Dashboard</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Visão geral do sistema e métricas principais</p>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                v-for="(card, index) in cards"
                :key="index"
                class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-md hover:shadow-lg dark:shadow-blue-900/10 transition-all duration-300 transform hover:-translate-y-1 border border-gray-100 dark:border-gray-700"
            >
                <div class="flex items-center">
                    <div :class="`p-3 rounded-full ${card.bgColor} ${card.textColor} mr-3`">
                        <FontAwesomeIcon
                            :icon="card.icon"
                            class='w-5 text-center text-lg'
                        />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ card.title }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ card.value }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center">
                    <span :class="`text-xs font-medium ${card.trendColor}`">
                        <FontAwesomeIcon
                            :icon="card.trendIcon"
                            class='w-5 text-center text-lg'
                        />
                        {{ card.trend }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">vs mês anterior</span>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-4 transition-all duration-300 border border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Vendas Mensais</h2>
                <div class="h-64 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <p class="text-gray-500 dark:text-gray-400">Gráfico de vendas aqui</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-4 transition-all duration-300 border border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Acessos por Dispositivo</h2>
                <div class="h-64 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <p class="text-gray-500 dark:text-gray-400">Gráfico de dispositivos aqui</p>
                </div>
            </div>
        </div>

        <!-- Tabela de Pedidos Recentes -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-blue-900/10 p-4 transition-all duration-300 border border-gray-100 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pedidos Recentes</h2>
                <a
                    href="#"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center"
                >
                    <span>Ver todos</span>
                    <FontAwesomeIcon
                        :icon="faArrowRight"
                        class='w-5 text-center text-lg'
                    />
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 rounded-lg">
                    <tr>
                        <th
                            scope="col"
                            class="px-4 py-3 rounded-l-lg"
                        >ID do Pedido
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3"
                        >Cliente
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3"
                        >Data
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3"
                        >Valor
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 rounded-r-lg"
                        >Status
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr
                        v-for="(order, index) in recentOrders"
                        :key="index"
                        class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
                    >
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ order.id }}</td>
                        <td class="px-4 py-3">{{ order.customer }}</td>
                        <td class="px-4 py-3">{{ order.date }}</td>
                        <td class="px-4 py-3">{{ order.amount }}</td>
                        <td class="px-4 py-3">
                            <span
                                :class="getStatusClass(order.status)"
                                class="px-2 py-1 rounded-full text-xs font-medium"
                            >
                                {{ order.status }}
                            </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template> 