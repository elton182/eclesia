<template>
  <div class="flex flex-col h-[calc(100vh-12rem)]">
    <!-- Cabeçalho do Chat -->
    <div class="bg-white dark:bg-gray-800 rounded-t-lg shadow-md p-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <div class="relative">
            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold">
              RL
            </div>
            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></span>
          </div>
          <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Roberta Lima</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Online</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
          </button>
          <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </button>
          <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Layout Principal -->
    <div class="flex flex-1 overflow-hidden">
      <!-- Lista de Contatos -->
      <div class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 hidden md:block">
        <!-- Barra de Pesquisa -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
              </svg>
            </div>
            <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Pesquisar contatos...">
          </div>
        </div>

        <!-- Lista de Conversas -->
        <div class="overflow-y-auto h-full">
          <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Conversa Ativa -->
            <li class="px-4 py-3 bg-primary-50 dark:bg-primary-900/20 hover:bg-primary-100 dark:hover:bg-primary-900/30 cursor-pointer">
              <div class="flex items-center space-x-3">
                <div class="relative flex-shrink-0">
                  <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold">
                    RL
                  </div>
                  <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      Roberta Lima
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      10:42
                    </p>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Vou enviar os arquivos hoje à tarde
                  </p>
                </div>
              </div>
            </li>
            
            <!-- Outras Conversas -->
            <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
              <div class="flex items-center space-x-3">
                <div class="relative flex-shrink-0">
                  <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold">
                    JC
                  </div>
                  <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      João Carlos
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      09:30
                    </p>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Terminei a implementação do dashboard
                  </p>
                </div>
              </div>
            </li>
            
            <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
              <div class="flex items-center space-x-3">
                <div class="relative flex-shrink-0">
                  <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-semibold">
                    MS
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      Marcos Santos
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      Ontem
                    </p>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Os protótipos estão prontos para revisão
                  </p>
                </div>
              </div>
            </li>
            
            <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
              <div class="flex items-center space-x-3">
                <div class="relative flex-shrink-0">
                  <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-700 font-semibold">
                    PF
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      Paula Ferreira
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      Ontem
                    </p>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Encontrei alguns bugs na última versão
                  </p>
                </div>
              </div>
            </li>
            
            <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
              <div class="flex items-center space-x-3">
                <div class="relative flex-shrink-0">
                  <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold">
                    AM
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      Ana Maria Silva
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      Seg
                    </p>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Reunião de equipe amanhã às 10h
                  </p>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Área de Mensagens -->
      <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900">
        <!-- Mensagens -->
        <div class="flex-1 p-4 overflow-y-auto">
          <div class="space-y-4">
            <!-- Data -->
            <div class="flex justify-center">
              <span class="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-3 py-1 rounded-full shadow-sm">
                Hoje, 10:30
              </span>
            </div>
            
            <!-- Mensagem Recebida -->
            <div class="flex items-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-2 items-start">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                    Bom dia! Como está o andamento do projeto?
                  </span>
                </div>
              </div>
              <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs order-1">
                RL
              </div>
            </div>
            
            <!-- Mensagem Enviada -->
            <div class="flex items-end justify-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-1 items-end">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-br-none bg-primary-600 text-white">
                    Bom dia! Estamos avançando bem. Já concluímos 65% das tarefas planejadas.
                  </span>
                </div>
              </div>
            </div>
            
            <!-- Mensagem Recebida -->
            <div class="flex items-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-2 items-start">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                    Ótimo! E quanto ao módulo financeiro? Conseguiremos entregar na data prevista?
                  </span>
                </div>
              </div>
              <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs order-1">
                RL
              </div>
            </div>
            
            <!-- Mensagem Enviada -->
            <div class="flex items-end justify-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-1 items-end">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-br-none bg-primary-600 text-white">
                    Sim, estamos dentro do cronograma. A equipe está focada nessa entrega para o dia 15/07.
                  </span>
                </div>
              </div>
            </div>
            
            <!-- Mensagem Enviada -->
            <div class="flex items-end justify-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-1 items-end">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-br-none bg-primary-600 text-white">
                    Inclusive, acabei de receber o relatório de testes e os resultados estão positivos.
                  </span>
                </div>
              </div>
            </div>
            
            <!-- Mensagem Recebida -->
            <div class="flex items-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-2 items-start">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                    Perfeito! Pode me enviar esse relatório para que eu possa apresentar na reunião com os stakeholders?
                  </span>
                </div>
              </div>
              <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs order-1">
                RL
              </div>
            </div>
            
            <!-- Mensagem Recebida com Horário -->
            <div class="flex items-end">
              <div class="flex flex-col space-y-2 text-sm max-w-xs mx-2 order-2 items-start">
                <div>
                  <span class="px-4 py-2 rounded-lg inline-block rounded-bl-none bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                    Vou precisar dele até o final do dia.
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400 leading-none">10:42</span>
                </div>
              </div>
              <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-xs order-1">
                RL
              </div>
            </div>
          </div>
        </div>

        <!-- Área de Digitação -->
        <div class="bg-white dark:bg-gray-800 p-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center space-x-2">
            <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
            </button>
            <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <input type="text" class="flex-1 border border-gray-300 dark:border-gray-600 rounded-full px-4 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500" placeholder="Digite sua mensagem...">
            <button class="p-2 rounded-full bg-primary-600 hover:bg-primary-700 text-white">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Aqui você pode adicionar a lógica necessária para a página
// como carregar mensagens, enviar novas mensagens, etc.
</script> 