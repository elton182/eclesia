<template>
  <div class="space-y-4">
    <!-- Cabeçalho do Calendário -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div v-if="showHeader" class="flex-1">
          <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ titulo }}</h1>
          <p v-if="subtitulo" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitulo }}</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
          <!-- Botão Novo Evento -->
          <button 
            v-if="showNovoEventoBtn"
            @click="$emit('novo-evento')"
            class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-sm hover:shadow font-medium"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            {{ textoNovoEvento }}
          </button>
          
          <!-- Controles de Navegação -->
          <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden shadow-sm">
            <button
              @click="irParaHoje"
              class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200"
            >
              {{ $t('calendar.today') }}
            </button>
            <div class="w-px bg-gray-300 dark:bg-gray-600"></div>
            <button 
              @click="mesAnterior"
              class="px-2 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div class="w-px bg-gray-300 dark:bg-gray-600"></div>
            <button 
              @click="proximoMes"
              class="px-2 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
          
          <!-- Seletor de Visualização -->
          <div v-if="showTipoVisualizacao" class="relative">
            <select
              v-model="tipoVisualizacao"
              @change="$emit('mudou-visualizacao', tipoVisualizacao)"
              class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm min-w-[100px]"
            >
              <option value="mes">{{ $t('calendar.month') }}</option>
              <option value="semana">{{ $t('calendar.week') }}</option>
              <option value="dia">{{ $t('calendar.day') }}</option>
              <option value="lista">{{ $t('calendar.list') }}</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700 dark:text-gray-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mês e Ano -->
      <div class="mt-4 text-center">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ mesAnoAtual }}</h2>
      </div>
    </div>

    <!-- Calendário Principal -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-200 dark:border-gray-700">
      <!-- Dias da Semana -->
      <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
        <div 
          v-for="dia in diasSemana" 
          :key="dia"
          class="py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"
        >
          {{ dia }}
        </div>
      </div>
      
      <!-- Dias do Mês -->
      <div v-for="(semana, indexSemana) in semanasDoMes" :key="indexSemana" class="grid grid-cols-7" :class="{ 'border-b border-gray-100 dark:border-gray-700': indexSemana < semanasDoMes.length - 1 }">
        <div 
          v-for="(dia, indexDia) in semana" 
          :key="`${indexSemana}-${indexDia}`"
          @click="clicouNoDia(dia)"
          @drop="onDrop($event, dia)"
          @dragover="onDragOver($event, dia)"
          @dragleave="onDragLeave($event, dia)"
          :class="[
            'p-2 border-r border-gray-100 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-200 relative group',
            {
              'bg-gray-50/50 dark:bg-gray-900/50': !dia.mesAtual,
              'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': dia.isHoje,
              'last:border-r-0': indexDia === 6,
              'bg-blue-100 dark:bg-blue-900/30': dragOverDay === dia,
              'min-h-[80px]': !dia.eventos || dia.eventos.length === 0,
              'min-h-[100px]': dia.eventos && dia.eventos.length >= 1 && dia.eventos.length <= 2,
              'min-h-[120px]': dia.eventos && dia.eventos.length >= 3 && dia.eventos.length <= 4,
              'min-h-[140px]': dia.eventos && dia.eventos.length > 4,
            }
          ]"
        >
          <!-- Número do dia -->
          <div :class="[
            'text-sm font-medium mb-1 flex items-center justify-between',
            {
              'text-gray-400 dark:text-gray-500': !dia.mesAtual,
              'text-gray-700 dark:text-gray-300': dia.mesAtual && !dia.isHoje,
              'text-blue-700 dark:text-blue-300 font-semibold': dia.isHoje
            }
          ]">
            <span :class="[
              'rounded-full w-6 h-6 flex items-center justify-center text-xs',
              {
                'bg-blue-600 text-white shadow-sm': dia.isHoje,
                'hover:bg-gray-200 dark:hover:bg-gray-600': !dia.isHoje && dia.mesAtual
              }
            ]">
              {{ dia.numero }}
            </span>
            <!-- Indicador de eventos -->
            <div v-if="dia.eventos && dia.eventos.length > 0" class="flex items-center space-x-1">
              <div class="w-1.5 h-1.5 bg-blue-500 rounded-full"></div>
              <span v-if="dia.eventos.length > 1" class="text-xs text-gray-500 dark:text-gray-400">{{ dia.eventos.length }}</span>
            </div>
          </div>
          
          <!-- Eventos do dia -->
          <div v-if="dia.eventos && dia.eventos.length > 0" class="space-y-1">
            <div 
              v-for="(evento, indexEvento) in dia.eventos.slice(0, maxEventosPorDia)" 
              :key="indexEvento"
              @click.stop="clicouNoEvento(evento)"
              :class="[
                'text-xs px-2 py-1 rounded-md truncate cursor-pointer transition-all duration-200 hover:shadow-sm transform hover:scale-105',
                evento.classe || 'bg-blue-100 dark:bg-blue-900/70 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800'
              ]"
              :title="evento.titulo"
            >
              <div class="flex items-center space-x-1">
                <div class="w-1 h-1 bg-current rounded-full flex-shrink-0"></div>
                <span class="truncate font-medium">{{ evento.titulo }}</span>
              </div>
            </div>
            
            <!-- Indicador de mais eventos -->
            <div
              v-if="dia.eventos.length > maxEventosPorDia"
              @click.stop="verMaisEventos(dia)"
              class="text-xs text-blue-600 dark:text-blue-400 cursor-pointer hover:text-blue-800 dark:hover:text-blue-200 font-medium px-2 py-1 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors duration-200 text-center"
            >
              +{{ dia.eventos.length - maxEventosPorDia }} {{ $t('calendar.more') }}
            </div>
          </div>
          
          <!-- Área de drop hover -->
          <div v-if="dragOverDay === dia" class="absolute inset-0 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-800/50 dark:to-blue-900/50 opacity-90 rounded-md pointer-events-none border-2 border-blue-400 dark:border-blue-500 border-dashed">
            <div class="flex items-center justify-center h-full">
              <div class="text-blue-700 dark:text-blue-300 text-xs font-semibold bg-white dark:bg-gray-800 px-2 py-1 rounded-md shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                {{ $t('calendar.dropHere') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Próximos Eventos -->
    <div v-if="showProximosEventos && proximosEventos.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('calendar.upcomingEvents') }}</h3>
        <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
          {{ proximosEventos.length }} {{ $t('calendar.events') }}
        </div>
      </div>
      
      <div class="space-y-3">
        <div 
          v-for="(evento, index) in proximosEventos" 
          :key="index"
          @click="clicouNoEvento(evento)"
          class="flex items-center space-x-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg p-2 -m-2 transition-all duration-200 group border border-transparent hover:border-gray-200 dark:hover:border-gray-600"
        >
          <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/30 dark:to-blue-800/50 rounded-lg flex flex-col items-center justify-center shadow-sm group-hover:shadow">
            <span class="text-xs font-medium text-blue-700 dark:text-blue-300">{{ evento.mesAbrev }}</span>
            <span class="text-sm font-bold text-blue-700 dark:text-blue-300">{{ evento.dia }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ evento.titulo }}</h4>
            <p v-if="evento.horario" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ evento.horario }}
            </p>
            <div v-if="evento.participantes && evento.participantes.length > 0" class="flex items-center mt-2">
              <div class="flex -space-x-1">
                <div 
                  v-for="(participante, pIndex) in evento.participantes.slice(0, 3)" 
                  :key="pIndex"
                  :class="[
                    'w-5 h-5 rounded-full flex items-center justify-center font-semibold text-xs ring-2 ring-white dark:ring-gray-800 border',
                    participante.classe || 'bg-blue-100 text-blue-700 border-blue-200'
                  ]"
                  :title="participante.nome"
                >
                  {{ participante.iniciais }}
                </div>
              </div>
              <span v-if="evento.participantes.length > 3" class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                +{{ evento.participantes.length - 3 }}
              </span>
            </div>
          </div>
          <div v-if="evento.categoria" class="flex-shrink-0">
            <span :class="[
              'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border',
              evento.categoria.classe || 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'
            ]">
              {{ evento.categoria.nome }}
            </span>
          </div>
          <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Mais Eventos -->
    <InnovModal v-model="showMaisEventosModal" size="lg">
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            {{ $t('calendar.eventsOfDay') }} {{ diaModalAtual?.dataStr.split('-')[2] }}/{{ diaModalAtual?.dataStr.split('-')[1] }}/{{ diaModalAtual?.dataStr.split('-')[0] }}
          </h3>
          <button 
            @click="showMaisEventosModal = false"
            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-3">
          <div 
            v-for="(evento, index) in diaModalAtual?.eventos" 
            :key="index"
            @click="clicouNoEvento(evento)"
            :class="[
              'p-3 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md transform hover:scale-[1.01]',
              evento.classe || 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800'
            ]"
          >
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <div :class="[
                  'w-2 h-2 rounded-full',
                  evento.classe ? 'bg-current' : 'bg-blue-500 dark:bg-blue-400'
                ]"></div>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ evento.titulo }}
                </h4>
                <p v-if="evento.horario" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  {{ evento.horario }}
                </p>
              </div>
              <div v-if="evento.categoria" class="flex-shrink-0">
                <span :class="[
                  'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                  evento.categoria.classe || 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200'
                ]">
                  {{ evento.categoria.nome }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </InnovModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import InnovModal from '@/components/base/InnovModal.vue'

const { t } = useI18n()

// Props
const props = defineProps({
  // Configurações de aparência
  titulo: {
    type: String,
    default: 'Calendário'
  },
  subtitulo: {
    type: String,
    default: ''
  },
  showHeader: {
    type: Boolean,
    default: true
  },
  showNovoEventoBtn: {
    type: Boolean,
    default: true
  },
  textoNovoEvento: {
    type: String,
    default: 'Novo Evento'
  },
  showTipoVisualizacao: {
    type: Boolean,
    default: true
  },
  showProximosEventos: {
    type: Boolean,
    default: true
  },
  maxEventosPorDia: {
    type: Number,
    default: 2
  },
  droppable: {
    type: Boolean,
    default: false
  },
  
  // Dados
  eventos: {
    type: Array,
    default: () => []
  },
  dataInicial: {
    type: Date,
    default: () => new Date()
  }
})

// Emits
const emit = defineEmits([
  'novo-evento',
  'clicou-dia',
  'clicou-evento',
  'ver-mais-eventos',
  'mudou-mes',
  'mudou-visualizacao',
  'evento-dropado'
])

// Estado reativo
const dataAtual = ref(new Date(props.dataInicial))
const tipoVisualizacao = ref('mes')
const dragOverDay = ref(null)
const showMaisEventosModal = ref(false)
const diaModalAtual = ref(null)

// Arrays traduzidos
const diasSemana = computed(() => [
  t('calendar.weekdays.sun'),
  t('calendar.weekdays.mon'),
  t('calendar.weekdays.tue'),
  t('calendar.weekdays.wed'),
  t('calendar.weekdays.thu'),
  t('calendar.weekdays.fri'),
  t('calendar.weekdays.sat')
])

const meses = computed(() => [
  t('calendar.months.january'),
  t('calendar.months.february'),
  t('calendar.months.march'),
  t('calendar.months.april'),
  t('calendar.months.may'),
  t('calendar.months.june'),
  t('calendar.months.july'),
  t('calendar.months.august'),
  t('calendar.months.september'),
  t('calendar.months.october'),
  t('calendar.months.november'),
  t('calendar.months.december')
])

const mesesAbrev = computed(() => [
  t('calendar.monthsShort.jan'),
  t('calendar.monthsShort.feb'),
  t('calendar.monthsShort.mar'),
  t('calendar.monthsShort.apr'),
  t('calendar.monthsShort.may'),
  t('calendar.monthsShort.jun'),
  t('calendar.monthsShort.jul'),
  t('calendar.monthsShort.aug'),
  t('calendar.monthsShort.sep'),
  t('calendar.monthsShort.oct'),
  t('calendar.monthsShort.nov'),
  t('calendar.monthsShort.dec')
])

// Computed
const mesAnoAtual = computed(() => {
  const mes = meses.value[dataAtual.value.getMonth()]
  const ano = dataAtual.value.getFullYear()
  return `${mes} ${ano}`
})

const hoje = computed(() => {
  const agora = new Date()
  return {
    ano: agora.getFullYear(),
    mes: agora.getMonth(),
    dia: agora.getDate()
  }
})

const semanasDoMes = computed(() => {
  const ano = dataAtual.value.getFullYear()
  const mes = dataAtual.value.getMonth()
  
  // Primeiro dia do mês
  const primeiroDia = new Date(ano, mes, 1)
  // Último dia do mês
  const ultimoDia = new Date(ano, mes + 1, 0)
  
  // Dia da semana do primeiro dia (0 = domingo, 6 = sábado)
  const primeiroDiaSemana = primeiroDia.getDay()
  
  const semanas = []
  let diaAtual = new Date(primeiroDia)
  
  // Volta para o início da semana
  diaAtual.setDate(diaAtual.getDate() - primeiroDiaSemana)
  
  while (diaAtual <= ultimoDia || diaAtual.getDay() !== 0) {
    const semana = []
    
    for (let i = 0; i < 7; i++) {
      const dia = {
        numero: diaAtual.getDate(),
        mesAtual: diaAtual.getMonth() === mes,
        data: new Date(diaAtual),
        dataStr: formatDateToString(diaAtual),
        isHoje: diaAtual.getFullYear() === hoje.value.ano && 
                diaAtual.getMonth() === hoje.value.mes && 
                diaAtual.getDate() === hoje.value.dia,
        eventos: getEventosParaDia(diaAtual)
      }
      
      semana.push(dia)
      diaAtual.setDate(diaAtual.getDate() + 1)
    }
    
    semanas.push(semana)
    
    // Se estamos em uma nova semana e já passamos do último dia do mês
    if (diaAtual > ultimoDia && diaAtual.getDay() === 0) {
      break
    }
  }
  
  return semanas
})

const proximosEventos = computed(() => {
  if (!props.eventos || props.eventos.length === 0) return []
  
  const agora = new Date()
  agora.setHours(0, 0, 0, 0)
  
  return props.eventos
    .filter(evento => {
      const dataEvento = parseLocalDate(evento.data)
      if (!dataEvento) return false
      dataEvento.setHours(0, 0, 0, 0)
      return dataEvento >= agora
    })
    .sort((a, b) => {
      const dataA = parseLocalDate(a.data)
      const dataB = parseLocalDate(b.data)
      return dataA - dataB
    })
    .slice(0, 5)
    .map(evento => {
      const dataEvento = parseLocalDate(evento.data)
      return {
        ...evento,
        dia: dataEvento.getDate(),
        mesAbrev: mesesAbrev.value[dataEvento.getMonth()]
      }
    })
})

// Métodos
function formatDateToString(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Função para converter string YYYY-MM-DD para Date sem problemas de timezone
function parseLocalDate(dateString) {
  if (!dateString) return null
  
  // Se já é uma string com horário, usar apenas a parte da data
  const dateOnly = dateString.split('T')[0]
  
  // Separar os componentes da data
  const [year, month, day] = dateOnly.split('-').map(Number)
  
  // Criar Date com horário local (sem conversão UTC)
  return new Date(year, month - 1, day)
}

function getEventosParaDia(data) {
  if (!props.eventos || props.eventos.length === 0) return []
  
  const eventos = props.eventos.filter(evento => {
    const dataEvento = parseLocalDate(evento.data)
    if (!dataEvento) return false
    
    const match = dataEvento.getFullYear() === data.getFullYear() &&
           dataEvento.getMonth() === data.getMonth() &&
           dataEvento.getDate() === data.getDate()
    return match
  })
  
  return eventos
}

function mesAnterior() {
  const novaData = new Date(dataAtual.value)
  novaData.setMonth(novaData.getMonth() - 1)
  dataAtual.value = novaData
  emit('mudou-mes', novaData)
}

function proximoMes() {
  const novaData = new Date(dataAtual.value)
  novaData.setMonth(novaData.getMonth() + 1)
  dataAtual.value = novaData
  emit('mudou-mes', novaData)
}

function irParaHoje() {
  const novaData = new Date()
  dataAtual.value = novaData
  emit('mudou-mes', novaData)
}

function clicouNoDia(dia) {
  emit('clicou-dia', {
    data: dia.data,
    dataStr: dia.dataStr,
    eventos: dia.eventos
  })
}

function clicouNoEvento(evento) {
  emit('clicou-evento', evento)
}

function verMaisEventos(dia) {
  diaModalAtual.value = dia
  showMaisEventosModal.value = true
}

function onDrop(event, dia) {
  if (!props.droppable) return
  
  event.preventDefault()
  dragOverDay.value = null
  
  const data = event.dataTransfer.getData('text')
  
  try {
    const eventData = JSON.parse(data)
    emit('evento-dropado', {
      eventData,
      dia: dia.data,
      dataStr: dia.dataStr
    })
  } catch (error) {
    console.error('Erro ao processar dados do drag and drop:', error)
  }
}

function onDragOver(event, dia) {
  if (!props.droppable) return
  event.preventDefault()
  dragOverDay.value = dia
}

function onDragLeave(event, dia) {
  if (!props.droppable) return
  // Só remove o highlight se realmente saiu da célula
  const rect = event.currentTarget.getBoundingClientRect()
  const x = event.clientX
  const y = event.clientY
  
  if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
    dragOverDay.value = null
  }
}

// Métodos expostos
function getCurrentMonth() {
  return dataAtual.value.getMonth() + 1
}

function getCurrentYear() {
  return dataAtual.value.getFullYear()
}

function setCurrentDate(date) {
  dataAtual.value = new Date(date)
}

function updateToDate(year, month) {
  
  const newDate = new Date(year, month - 1, 1) // month - 1 porque Date usa 0-based months
  dataAtual.value = newDate
  
}

// Expose métodos para uso externo
defineExpose({
  getCurrentMonth,
  getCurrentYear,
  setCurrentDate,
  updateToDate
})

// Watchers
watch(() => props.dataInicial, (novaData) => {
  
  if (novaData) {
    dataAtual.value = new Date(novaData)
  }
})

// Forçar reatividade para eventos quando a data muda ou eventos mudam
watch([() => dataAtual.value, () => props.eventos], () => {
  // Força recálculo dos eventos quando o mês ou eventos mudam
}, { deep: true })

onMounted(() => {
  // Não faz nada - deixa o watcher de dataInicial cuidar da sincronização
})
</script> 