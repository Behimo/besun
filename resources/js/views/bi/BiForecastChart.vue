<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'پیش‌بینی درآمد' },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const series = computed(() => [{
  name: 'ارزش پیش‌بینی',
  data: props.data.map(d => Number(d.total_amount ?? 0)),
}])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
  colors: ['#FF9F43'],
  dataLabels: {
    enabled: true,
    formatter: val => (val > 0 ? formatMoney(val) : ''),
    style: { fontSize: '11px' },
  },
  xaxis: { categories: props.data.map(d => d.label) },
  yaxis: { labels: { formatter: val => formatMoney(val) } },
  tooltip: {
    y: {
      formatter: (val, { dataPointIndex }) => {
        const row = props.data[dataPointIndex]

        return `${formatMoney(val)} ریال — ${row?.deals_count ?? 0} معامله`
      },
    },
  },
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
