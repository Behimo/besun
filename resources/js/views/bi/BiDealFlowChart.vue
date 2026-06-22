<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'جریان معاملات' },
})

const series = computed(() => [
  { name: 'ایجادشده', data: props.data.map(d => d.created ?? 0) },
  { name: 'برنده', data: props.data.map(d => d.won ?? 0) },
  { name: 'باخته', data: props.data.map(d => d.lost ?? 0) },
])

const chartOptions = computed(() => ({
  chart: { type: 'bar', stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
  colors: ['#4A0E17', '#28C76F', '#EA5455'],
  xaxis: { categories: props.data.map(d => d.label) },
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
        type="bar"
        height="300"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
