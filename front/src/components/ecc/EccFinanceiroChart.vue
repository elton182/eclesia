<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Legend,
  Tooltip,
  Filler,
} from 'chart.js'
import {
  chartSeriesFromLivro,
  buildFinanceiroChartConfig,
  formatMoney,
} from '@/utils/eccFinanceiro'

Chart.register(
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Legend,
  Tooltip,
  Filler,
)

const props = defineProps({
  livro: {
    type: Object,
    default: null,
  },
})

const canvasRef = ref(null)
/** @type {Chart | null} */
let chartInstance = null
let renderSeq = 0

function destroy() {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
}

async function render() {
  const seq = ++renderSeq
  await nextTick()
  if (seq !== renderSeq) return
  if (!canvasRef.value || !props.livro) {
    destroy()
    return
  }

  const series = chartSeriesFromLivro(props.livro)
  const config = buildFinanceiroChartConfig(series, { formatMoney })

  // Recriar sempre: update() + troca de options falha com frequência ao mudar o ano.
  destroy()
  if (seq !== renderSeq || !canvasRef.value) return
  chartInstance = new Chart(canvasRef.value, config)
}

watch(
  () => props.livro?.ano,
  () => {
    render()
  },
)

watch(
  () => props.livro,
  () => {
    render()
  },
)

onMounted(() => {
  render()
})

onBeforeUnmount(() => {
  renderSeq += 1
  destroy()
})
</script>

<template>
  <div class="card p-4 mb-8" data-testid="ecc-financeiro-grafico">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
      <h2 class="font-serif text-xl font-normal" style="color: var(--color-ink)">
        Movimento por mês
      </h2>
      <p class="text-sm" style="color: var(--color-muted)">
        Entradas e saídas (barras) · acumulado (linha)
      </p>
    </div>
    <div class="relative w-full h-[280px] md:h-[320px]">
      <canvas ref="canvasRef" role="img" aria-label="Gráfico mensal do financeiro ECC" />
    </div>
  </div>
</template>
