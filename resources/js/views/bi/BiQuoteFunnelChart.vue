<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'قیف پیش‌فاکتور' },
})

const statusLabel = status => ({
  draft: 'پیش‌نویس',
  sent: 'ارسال‌شده',
  accepted: 'پذیرفته',
  rejected: 'ردشده',
  cancelled: 'لغوشده',
}[status] ?? status)

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const funnelData = computed(() => props.data.filter(d => ['draft', 'sent', 'accepted', 'rejected'].includes(d.status)))

const series = computed(() => [{
  name: 'تعداد',
  data: funnelData.value.map(d => d.count ?? 0),
}])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
  colors: ['#D4AF37'],
  dataLabels: { enabled: true },
  xaxis: { categories: funnelData.value.map(d => statusLabel(d.status)) },
  tooltip: {
    y: {
      formatter: (val, { dataPointIndex }) => {
        const row = funnelData.value[dataPointIndex]

        return `${val} مورد — ${formatMoney(row?.total_amount ?? 0)} ریال`
      },
    },
  },
}))
</script>

<template>
  <VCard :title="title">
    <VCardText>
      <div
        v-if="!funnelData.length"
        class="text-medium-emphasis text-center py-8"
      >
        داده‌ای برای نمایش وجود ندارد.
      </div>
      <VueApexCharts
        v-else
        type="bar"
        height="260"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
