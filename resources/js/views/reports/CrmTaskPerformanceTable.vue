<script setup>
const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
})

const chartSeries = computed(() => [{
  name: 'تکمیل‌شده',
  data: props.rows.map(r => r.completed ?? 0),
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
      borderRadius: 4,
      barHeight: '60%',
    },
  },
  colors: ['#D4AF37'],
  xaxis: {
    categories: props.rows.map(r => r.name),
  },
  dataLabels: { enabled: false },
  grid: { borderColor: 'rgba(var(--v-border-color), 0.12)' },
}))
</script>

<template>
  <div>
    <VRow v-if="rows.length">
      <VCol
        cols="12"
        lg="5"
      >
        <VueApexCharts
          type="bar"
          height="320"
          :options="chartOptions"
          :series="chartSeries"
        />
      </VCol>

      <VCol
        cols="12"
        lg="7"
      >
        <VTable>
          <thead>
            <tr>
              <th>عضو تیم</th>
              <th>کل</th>
              <th>انجام‌شده</th>
              <th>در جریان</th>
              <th>سررسید گذشته</th>
              <th>نرخ تکمیل</th>
              <th>به‌موقع</th>
              <th>میانگین روز</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.user_id"
            >
              <td class="font-weight-medium">
                {{ row.name }}
              </td>
              <td>{{ row.total?.toLocaleString('fa-IR') }}</td>
              <td>{{ row.completed?.toLocaleString('fa-IR') }}</td>
              <td>{{ row.in_progress?.toLocaleString('fa-IR') }}</td>
              <td>
                <VChip
                  size="small"
                  :color="row.overdue > 0 ? 'error' : 'success'"
                  variant="tonal"
                >
                  {{ row.overdue?.toLocaleString('fa-IR') }}
                </VChip>
              </td>
              <td>
                <VChip
                  size="small"
                  :color="row.completion_rate >= 70 ? 'success' : row.completion_rate >= 40 ? 'warning' : 'secondary'"
                  variant="tonal"
                >
                  {{ row.completion_rate }}٪
                </VChip>
              </td>
              <td>{{ row.on_time_rate }}٪</td>
              <td>{{ row.avg_days_to_complete?.toLocaleString('fa-IR') }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCol>
    </VRow>

    <div
      v-else
      class="text-medium-emphasis text-center py-8"
    >
      تسکی با مسئول مشخص در این بازه ثبت نشده است.
    </div>
  </div>
</template>
