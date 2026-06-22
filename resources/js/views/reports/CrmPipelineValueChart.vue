<script setup>
const props = defineProps({
  stages: {
    type: Array,
    default: () => [],
  },
  valueKey: {
    type: String,
    default: 'total_amount',
  },
  countKey: {
    type: String,
    default: 'deals_count',
  },
  title: {
    type: String,
    default: 'ارزش قیف',
  },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const series = computed(() => [{
  name: 'ارزش (ریال)',
  data: props.stages.map(s => Number(s[props.valueKey] ?? 0)),
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
    formatter: val => (val > 0 ? formatMoney(val) : ''),
    style: { fontSize: '11px' },
  },
  legend: { show: false },
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    xaxis: { lines: { show: true } },
    yaxis: { lines: { show: false } },
  },
  xaxis: {
    categories: props.stages.map(s => s.name),
    labels: {
      formatter: val => formatMoney(val),
      style: { fontSize: '12px' },
    },
  },
  tooltip: {
    y: {
      formatter: (val, { dataPointIndex }) => {
        const stage = props.stages[dataPointIndex]
        const count = stage?.[props.countKey] ?? 0

        return `${formatMoney(val)} ریال — ${count} مورد`
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
        height="300"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
