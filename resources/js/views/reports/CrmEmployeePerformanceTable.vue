<script setup>
const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['select'])

const chartSeries = computed(() => [{
  name: 'نمره عملکرد',
  data: props.rows.map(r => r.performance_score ?? 0),
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
  colors: ['#4A0E17'],
  xaxis: {
    categories: props.rows.map(r => r.name),
    max: 100,
  },
  dataLabels: { enabled: false },
  grid: { borderColor: 'rgba(var(--v-border-color), 0.12)' },
}))

const formatMinutes = minutes => {
  const m = Number(minutes ?? 0)
  if (!m)
    return '—'

  const hours = Math.floor(m / 60)
  const mins = m % 60

  if (hours > 0)
    return `${hours.toLocaleString('fa-IR')} ساعت و ${mins.toLocaleString('fa-IR')} دقیقه`

  return `${mins.toLocaleString('fa-IR')} دقیقه`
}

const displayScore = row => {
  const score = row.avg_manager_score ?? row.avg_effort_score

  return score != null ? score : '—'
}
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
          height="360"
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
              <th>رتبه</th>
              <th>عضو تیم</th>
              <th>نمره کلی</th>
              <th>زمان کار</th>
              <th>گزارش روزانه</th>
              <th>امتیاز مدیر</th>
              <th>نرخ تکمیل</th>
              <th>به‌موقع</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.user_id"
            >
              <td>
                <VChip
                  size="small"
                  :color="row.rank === 1 ? 'warning' : row.rank <= 3 ? 'info' : 'secondary'"
                  variant="tonal"
                >
                  {{ row.rank?.toLocaleString('fa-IR') }}
                </VChip>
              </td>
              <td class="font-weight-medium">
                {{ row.name }}
              </td>
              <td>
                <VChip
                  size="small"
                  :color="row.performance_score >= 70 ? 'success' : row.performance_score >= 40 ? 'warning' : 'secondary'"
                  variant="tonal"
                >
                  {{ row.performance_score }}٪
                </VChip>
              </td>
              <td>{{ formatMinutes(row.total_work_minutes) }}</td>
              <td>{{ row.daily_reports_submitted?.toLocaleString('fa-IR') ?? 0 }}</td>
              <td>{{ displayScore(row) }}</td>
              <td>{{ row.completion_rate }}٪</td>
              <td>{{ row.on_time_rate }}٪</td>
              <td>
                <VBtn
                  size="small"
                  variant="text"
                  @click="emit('select', row)"
                >
                  جزئیات
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCol>
    </VRow>

    <div
      v-else
      class="text-medium-emphasis text-center py-8"
    >
      داده‌ای برای رتبه‌بندی در این بازه وجود ندارد.
    </div>
  </div>
</template>
