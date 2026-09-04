<template>
    <span :class="[
      'px-2 py-1 text-xs font-medium rounded-full',
      statusClasses[status] || statusClasses.default
    ]">
      {{ label }}
    </span>
  </template>
  
  <script setup>
  const props = defineProps({
    status: {
      type: String,
      required: true
    },
    label: {
      type: String,
      default: ''
    }
  })
  
  const statusClasses = {
    // Estados gerais
    aberto: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    em_andamento: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    pendente: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    resolvido: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    fechado: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    ausente: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',

    // Estados específicos de checklist
    em_execucao: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    concluido: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    nao_iniciado: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    atrasado: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    cancelado: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    
    // Estados normalizados (sem acentos e com underscore)
    em_execucao: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    nao_conformidade: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    conforme: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    nao_aplicavel: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    
    // Estado padrão
    default: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
  </script> 
  