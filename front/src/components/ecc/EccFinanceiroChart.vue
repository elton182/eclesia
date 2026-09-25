<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
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
/** @type {import('vue').ShallowRef<Chart|null>} */
const chart = ref(null)

function render() {
  if (!canvasRef.value) return
  const series = chartSeriesFromLivro(props.livro)
  const config = buildFinanceiroChartConfig(series, { formatMoney })

  if (chart.value) {
    chart.value.data = config.data
    chart.value.options = config.options
    chart.value.update()
    return
  }

  chart.value = new Chart(canvasRef.value, config)
}

function destroy() {
  if (chart.value) {
    chart.value.destroy()
    chart.value = null
  }
}

watch(
  () => props.livro,
  () => render(),
  { deep: true },
)

onMounted(render)
onBeforeUnmount(destroy)
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
