<script setup>
const props = defineProps({
  data: { type: Array, default: () => [] },
  title: { type: String, default: 'منبع لیدها' },
})

const sourceLabel = source => ({
  social: 'شبکه اجتماعی',
  email: 'ایمیل',
  sms: 'پیامک',
  web: 'وب',
  event: 'رویداد',
  referral: 'معرف',
  'نامشخص': 'نامشخص',
}[source] ?? source)

const series = computed(() => props.data.map(d => d.leads_count ?? 0))

const chartOptions = computed(() => ({
  chart: { type: 'donut', fontFamily: 'inherit' },
  labels: props.data.map(d => sourceLabel(d.source)),
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
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
        type="donut"
        height="300"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
