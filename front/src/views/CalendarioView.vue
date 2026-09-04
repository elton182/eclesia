<template>
  <div class="space-y-6">
    <!-- Cabeçalho do Calendário -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Calendário</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gerencie seus eventos e compromissos</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <button class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
            Novo Evento
          </button>
          <div class="flex rounded-md shadow-sm">
            <button class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-l-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
              Hoje
            </button>
            <button class="px-3 py-2 bg-white dark:bg-gray-700 border-t border-b border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button class="px-3 py-2 bg-white dark:bg-gray-700 border-t border-b border-r border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-r-md hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
          <div class="relative">
            <select class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-md px-4 py-2 appearance-none focus:outline-none focus:ring-primary-500 focus:border-primary-500">
              <option>Mês</option>
              <option>Semana</option>
              <option>Dia</option>
              <option>Lista</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700 dark:text-gray-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mês e Ano -->
      <div class="mt-6 text-center">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Julho 2023</h2>
      </div>
    </div>

    <!-- Calendário Principal -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
      <!-- Dias da Semana -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Dom</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Seg</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Ter</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Qua</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Qui</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Sex</div>
        <div class="py-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Sáb</div>
      </div>
      
      <!-- Dias do Mês - Semana 1 -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <!-- Dias do mês anterior -->
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">25</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">26</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">27</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">28</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">29</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">30</div>
        </div>
        
        <!-- Dia 1 -->
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">1</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded truncate">
              Início do Sprint
            </div>
          </div>
        </div>
      </div>
      
      <!-- Dias do Mês - Semana 2 -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">2</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">3</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded truncate">
              Reunião de Equipe
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">4</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">5</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded truncate">
              Review de Design
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">6</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">7</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">8</div>
        </div>
      </div>
      
      <!-- Dias do Mês - Semana 3 -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">9</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">10</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded truncate">
              Reunião de Equipe
            </div>
            <div class="text-xs p-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded truncate">
              Revisão de Código
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">11</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">12</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-primary-50 dark:bg-primary-900/20">
          <div class="text-sm text-primary-700 dark:text-primary-300 font-medium">13</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded truncate">
              Prazo Final - Módulo 1
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">14</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">15</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded truncate">
              Entrega do Módulo Financeiro
            </div>
          </div>
        </div>
      </div>
      
      <!-- Dias do Mês - Semana 4 -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">16</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">17</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded truncate">
              Reunião de Equipe
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">18</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">19</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">20</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded truncate">
              Apresentação para Cliente
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">21</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">22</div>
        </div>
      </div>
      
      <!-- Dias do Mês - Semana 5 -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">23</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">24</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded truncate">
              Reunião de Equipe
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">25</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">26</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">27</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">28</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 rounded truncate">
              Retrospectiva do Sprint
            </div>
          </div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">29</div>
        </div>
      </div>
      
      <!-- Dias do Mês - Semana 6 (parcial) -->
      <div class="grid grid-cols-7">
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">30</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700">
          <div class="text-sm text-gray-900 dark:text-white">31</div>
          <div class="mt-1 space-y-1">
            <div class="text-xs p-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded truncate">
              Início do Novo Sprint
            </div>
          </div>
        </div>
        
        <!-- Dias do próximo mês -->
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">1</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">2</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">3</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">4</div>
        </div>
        <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
          <div class="text-sm text-gray-400 dark:text-gray-500">5</div>
        </div>
      </div>
    </div>

    <!-- Próximos Eventos -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Próximos Eventos</h3>
      
      <div class="space-y-4">
        <!-- Evento 1 -->
        <div class="flex items-start space-x-4">
          <div class="flex-shrink-0 w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex flex-col items-center justify-center">
            <span class="text-xs font-medium text-primary-700 dark:text-primary-300">JUL</span>
            <span class="text-lg font-bold text-primary-700 dark:text-primary-300">13</span>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Prazo Final - Módulo 1</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">09:00 - 10:00</p>
            <div class="flex items-center mt-2">
              <div class="flex -space-x-2">
                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  AM
                </div>
                <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  JC
                </div>
                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  RL
                </div>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400 ml-3">+2 participantes</span>
            </div>
          </div>
          <div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
              Importante
            </span>
          </div>
        </div>
        
        <!-- Evento 2 -->
        <div class="flex items-start space-x-4">
          <div class="flex-shrink-0 w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex flex-col items-center justify-center">
            <span class="text-xs font-medium text-primary-700 dark:text-primary-300">JUL</span>
            <span class="text-lg font-bold text-primary-700 dark:text-primary-300">15</span>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Entrega do Módulo Financeiro</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">14:00 - 15:30</p>
            <div class="flex items-center mt-2">
              <div class="flex -space-x-2">
                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  AM
                </div>
                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  RL
                </div>
              </div>
            </div>
          </div>
          <div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
              Entrega
            </span>
          </div>
        </div>
        
        <!-- Evento 3 -->
        <div class="flex items-start space-x-4">
          <div class="flex-shrink-0 w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex flex-col items-center justify-center">
            <span class="text-xs font-medium text-primary-700 dark:text-primary-300">JUL</span>
            <span class="text-lg font-bold text-primary-700 dark:text-primary-300">20</span>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Apresentação para Cliente</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">10:00 - 12:00</p>
            <div class="flex items-center mt-2">
              <div class="flex -space-x-2">
                <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  AM
                </div>
                <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  JC
                </div>
                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  RL
                </div>
                <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-semibold text-xs ring-2 ring-white dark:ring-gray-800">
                  MS
                </div>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400 ml-3">+1 participante</span>
            </div>
          </div>
          <div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
              Reunião
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Aqui você pode adicionar a lógica necessária para a página
// como carregar eventos, adicionar novos eventos, etc.
</script> 