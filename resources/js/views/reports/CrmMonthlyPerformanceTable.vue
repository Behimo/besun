<script setup>
const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  month: {
    type: String,
    default: '',
  },
})

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

const qualityColor = label => ({
  'عالی': 'success',
  'خوب': 'info',
  'متوسط': 'warning',
  'نیاز به بهبود': 'error',
}[label] ?? 'secondary')

const totalPending = computed(() =>
  props.rows.reduce((sum, row) => sum + (row.pending_review ?? 0), 0),
)
</script>

<template>
  <div>
    <VAlert
      v-if="totalPending > 0"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ totalPending.toLocaleString('fa-IR') }} گزارش در انتظار بازبینی مدیر —
      <RouterLink
        :to="{ name: 'apps-crm-daily-reports' }"
        class="text-primary font-weight-medium"
      >
        بازبینی در گزارش کار روزانه
      </RouterLink>
    </VAlert>

    <p
      v-if="month"
      class="text-caption text-medium-emphasis mb-3"
    >
      ماه گزارش: {{ month }}
    </p>

    <VTable v-if="rows.length">
      <thead>
        <tr>
          <th>رتبه</th>
          <th>کارمند</th>
          <th>میانگین امتیاز مدیر</th>
          <th>کیفیت</th>
          <th>گزارش ارسالی</th>
          <th>بازبینی‌شده</th>
          <th>در انتظار</th>
          <th>زمان کار</th>
          <th>ثبات ارسال</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.user_id"
        >
          <td>{{ row.rank?.toLocaleString('fa-IR') }}</td>
          <td class="font-weight-medium">
            {{ row.name }}
          </td>
          <td>
            <VChip
              v-if="row.avg_manager_score"
              size="small"
              :color="qualityColor(row.quality_label)"
              variant="tonal"
            >
              {{ row.avg_manager_score }}
            </VChip>
            <span v-else>—</span>
          </td>
          <td>
            <VChip
              v-if="row.quality_label"
              size="small"
              :color="qualityColor(row.quality_label)"
              variant="tonal"
            >
              {{ row.quality_label }}
            </VChip>
            <span
              v-else
              class="text-medium-emphasis"
            >بدون بازبینی</span>
          </td>
          <td>{{ row.reports_submitted?.toLocaleString('fa-IR') }}</td>
          <td>{{ row.reports_reviewed?.toLocaleString('fa-IR') }}</td>
          <td>
            <VChip
              v-if="row.pending_review"
              size="small"
              color="warning"
              variant="tonal"
            >
              {{ row.pending_review?.toLocaleString('fa-IR') }}
            </VChip>
            <span v-else>۰</span>
          </td>
          <td>{{ formatMinutes(row.total_work_minutes) }}</td>
          <td>{{ row.submission_rate }}٪</td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-else
      class="text-medium-emphasis text-center py-8"
    >
      گزارش ارسال‌شده‌ای در این ماه برای رتبه‌بندی کیفیت وجود ندارد.
    </div>
  </div>
</template>
