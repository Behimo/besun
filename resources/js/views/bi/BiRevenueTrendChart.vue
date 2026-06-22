<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'روند درآمد' },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const series = computed(() => [
  { name: 'درآمد برنده', data: props.data.map(d => Number(d.won_revenue ?? 0)) },
  { name: 'ارزش قیف', data: props.data.map(d => Number(d.pipeline_value ?? 0)) },
])

const chartOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit', stacked: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  colors: ['#4A0E17', '#D4AF37'],
  dataLabels: { enabled: false },
  xaxis: { categories: props.data.map(d => d.label) },
  yaxis: { labels: { formatter: val => formatMoney(val) } },
  tooltip: { y: { formatter: val => `${formatMoney(val)} ریال` } },
  legend: { position: 'top' },
}))
</script>

<template>
  <VCard :title="title">
    <VCardText>
      <div
        v-if="!data.length"
        class="text-medium-emphasis text-center py-8"
      >
        داده‌ای برای نمایش وجود ندارد.
      </div>
      <VueApexCharts
        v-else
        type="area"
        height="320"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
