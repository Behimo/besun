<script setup>
const props = defineProps({
  stages: {
    type: Array,
    default: () => [],
  },
})

const series = computed(() => [{
  name: 'تعداد معاملات',
  data: props.stages.map(s => s.deals_count ?? 0),
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
    labels: {
      style: { fontSize: '13px' },
    },
  },
  yaxis: {
    labels: {
      formatter: val => Number(val).toLocaleString('fa-IR'),
    },
  },
  tooltip: {
    y: {
      formatter: (val, { dataPointIndex }) => {
        const stage = props.stages[dataPointIndex]
        const amount = Number(stage?.deals_sum_amount ?? 0).toLocaleString('fa-IR')

        return `${val} معامله — ${amount} ریال`
      },
    },
  },
}))
</script>

<template>
  <VCard title="قیف فروش">
    <VCardText>
      <div v-if="!stages.length" class="text-medium-emphasis text-center py-8">
        هنوز مرحله‌ای در قیف فروش ثبت نشده است.
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
