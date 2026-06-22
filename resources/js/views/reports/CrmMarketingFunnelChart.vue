<script setup>
const props = defineProps({
  stages: {
    type: Array,
    default: () => [],
  },
  title: {
    type: String,
    default: 'قیف بازاریابی',
  },
})

const series = computed(() => [{
  name: 'تعداد لید',
  data: props.stages.map(s => s.leads_count ?? 0),
}])

const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 6,
      barHeight: '65%',
      distributed: true,
    },
  },
  colors: props.stages.map(s => s.color ?? '#4A0E17'),
  dataLabels: {
    enabled: true,
    formatter: val => (val > 0 ? val : ''),
    style: { fontSize: '12px' },
  },
  legend: { show: false },
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    xaxis: { lines: { show: true } },
    yaxis: { lines: { show: false } },
  },
  xaxis: {
    categories: props.stages.map(s => s.name),
  },
  tooltip: {
    y: {
      formatter: (val, { dataPointIndex }) => {
        const stage = props.stages[dataPointIndex]
        const score = stage?.avg_score ?? 0

        return `${val} لید — میانگین امتیاز: ${score}`
      },
    },
  },
}))
</script>

<template>
  <VCard :title="title">
    <VCardText>
      <div
        v-if="!stages.length"
        class="text-medium-emphasis text-center py-8"
      >
        داده‌ای برای نمایش وجود ندارد.
      </div>
      <VueApexCharts
        v-else
        type="bar"
        height="280"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
