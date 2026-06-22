<script setup>
const props = defineProps({
  rows: { type: Array, default: () => [] },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const progressRows = computed(() =>
  props.rows.filter(row => row.revenue_target > 0 || row.actual_revenue > 0))

const progressColor = progress => {
  if (progress == null)
    return 'secondary'

  if (progress >= 100)
    return 'success'

  if (progress >= 50)
    return 'primary'

  return 'warning'
}

const roleColor = level => {
  if (level === 'manager')
    return 'info'

  return 'primary'
}
</script>

<template>
  <VCard class="sales-targets-progress">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          size="40"
          rounded
        >
          <VIcon
            icon="tabler-chart-bar"
            size="20"
          />
        </VAvatar>
      </template>
      <VCardTitle>پیشرفت تحقق تارگت</VCardTitle>
      <VCardSubtitle>
        مقایسه فروش واقعی با تارگت تعیین‌شده
      </VCardSubtitle>
    </VCardItem>

    <VCardText>
      <div
        v-if="!progressRows.length"
        class="text-medium-emphasis text-center py-8"
      >
        <VIcon
          icon="tabler-chart-off"
          size="40"
          class="mb-2 text-disabled"
        />
        <div>داده‌ای برای نمایش پیشرفت وجود ندارد.</div>
      </div>

      <div
        v-for="row in progressRows"
        :key="`${row.scope}-${row.user_id ?? 'team'}`"
        class="sales-targets-progress-row"
      >
        <div class="d-flex align-center justify-space-between gap-2 mb-2">
          <div class="d-flex align-center gap-2 min-w-0">
            <VAvatar
              :color="roleColor(row.target_level)"
              variant="tonal"
              size="32"
            >
              <VIcon
                :icon="row.target_level === 'manager' ? 'tabler-briefcase' : 'tabler-user'"
                size="16"
              />
            </VAvatar>
            <span class="font-weight-medium text-truncate">{{ row.label }}</span>
          </div>
          <VChip
            v-if="row.revenue_progress != null"
            size="small"
            :color="progressColor(row.revenue_progress)"
            variant="tonal"
          >
            {{ row.revenue_progress.toLocaleString('fa-IR') }}٪
          </VChip>
        </div>

        <VProgressLinear
          :model-value="row.revenue_progress ?? 0"
          :color="progressColor(row.revenue_progress)"
          height="10"
          rounded
        />

        <div class="d-flex align-center justify-space-between gap-2 mt-2">
          <span class="text-caption text-medium-emphasis">
            {{ row.revenue_progress != null ? 'درصد تحقق' : 'تارگت تعیین نشده' }}
          </span>
          <span class="text-caption font-weight-medium text-no-wrap">
            {{ formatMoney(row.actual_revenue) }} / {{ formatMoney(row.revenue_target) }}
          </span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.sales-targets-progress {
  max-inline-size: 100%;
}

.sales-targets-progress-row {
  padding: 14px 16px;
  margin-block-end: 12px;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.sales-targets-progress-row:last-child {
  margin-block-end: 0;
}
</style>
