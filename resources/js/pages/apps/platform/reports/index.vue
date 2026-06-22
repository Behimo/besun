<script setup>
import { exportTableCsv, formatRial } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const loading = ref(true)
const reports = ref(null)
const dateFrom = ref('')
const dateTo = ref('')

const fetchReports = async () => {
  loading.value = true
  try {
    reports.value = await $api('/platform/reports', {
      query: {
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
      },
    })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchReports)

const revenueChart = computed(() => {
  const trend = reports.value?.revenue_trend ?? []

  return {
    chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
    xaxis: { categories: trend.map(r => r.month) },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    series: [{ name: 'درآمد', data: trend.map(r => r.revenue) }],
  }
})

const tenantGrowthChart = computed(() => {
  const growth = reports.value?.tenant_growth ?? []

  return {
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    xaxis: { categories: growth.map(r => r.month) },
    series: [{ name: 'مجموعه جدید', data: growth.map(r => r.count) }],
  }
})

const exportReports = () => {
  const top = reports.value?.top_tenants ?? []

  exportTableCsv('platform-top-tenants.csv', [
    { key: 'tenant_name', label: 'مجموعه' },
    { key: 'revenue', label: 'درآمد' },
    { key: 'transactions', label: 'تراکنش' },
  ], top)
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          گزارش‌های کسب‌وکار
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          درآمد، رشد مجموعه‌ها و پذیرش ماژuleها
        </p>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <AppJalaliDatePicker
          v-model="dateFrom"
          label="از تاریخ"
          style="max-inline-size: 160px;"
        />
        <AppJalaliDatePicker
          v-model="dateTo"
          label="تا تاریخ"
          style="max-inline-size: 160px;"
        />
        <VBtn
          color="primary"
          :loading="loading"
          @click="fetchReports"
        >
          اعمال
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          @click="exportReports"
        >
          CSV
        </VBtn>
      </div>
    </div>

    <VRow v-if="loading">
      <VCol
        v-for="i in 2"
        :key="i"
        cols="12"
        md="6"
      >
        <VSkeletonLoader type="card" />
      </VCol>
    </VRow>

    <template v-else-if="reports">
      <VRow class="mb-4">
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="روند درآمد">
            <VCardText>
              <VueApexCharts
                v-if="revenueChart.series[0].data.length"
                type="area"
                height="280"
                :options="revenueChart"
                :series="revenueChart.series"
              />
              <div
                v-else
                class="text-medium-emphasis text-center py-8"
              >
                داده‌ای موجود نیست
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="رشد مجموعه‌ها">
            <VCardText>
              <VueApexCharts
                v-if="tenantGrowthChart.series[0].data.length"
                type="bar"
                height="280"
                :options="tenantGrowthChart"
                :series="tenantGrowthChart.series"
              />
              <div
                v-else
                class="text-medium-emphasis text-center py-8"
              >
                داده‌ای موجود نیست
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="پذیرش ماژuleها">
            <VDataTable
              :headers="[
                { title: 'ماژول', key: 'name' },
                { title: 'مجموعه', key: 'tenants' },
                { title: 'نرخ', key: 'rate' },
              ]"
              :items="reports.module_adoption ?? []"
              hide-default-footer
            >
              <template #item.rate="{ item }">
                {{ item.rate }}٪
              </template>
            </VDataTable>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VCard title="برترین مجموعه‌ها (درآمد)">
            <VDataTable
              :headers="[
                { title: 'مجموعه', key: 'tenant_name' },
                { title: 'درآمد', key: 'revenue' },
                { title: 'تراکنش', key: 'transactions' },
              ]"
              :items="reports.top_tenants ?? []"
              hide-default-footer
            >
              <template #item.revenue="{ item }">
                {{ formatRial(item.revenue) }}
              </template>
            </VDataTable>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
