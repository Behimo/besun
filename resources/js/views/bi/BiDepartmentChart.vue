<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'مقایسه بخش‌ها' },
})

const deptLabel = dept => ({
  sales: 'فروش',
  marketing: 'بازاریابی',
  finance: 'مالی',
}[dept] ?? dept)

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const series = computed(() => [
  { name: 'درآمد برنده', data: props.data.map(d => Number(d.won_revenue ?? 0)) },
  { name: 'ارزش قیف', data: props.data.map(d => Number(d.pipeline_value ?? 0)) },
])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
  colors: ['#4A0E17', '#D4AF37'],
  xaxis: { categories: props.data.map(d => deptLabel(d.department)) },
  yaxis: { labels: { formatter: val => formatMoney(val) } },
  legend: { position: 'top' },
  tooltip: { y: { formatter: val => `${formatMoney(val)} ریال` } },
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
        type="bar"
        height="300"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
