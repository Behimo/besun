<script setup>
import { useDisplay } from 'vuetify'
import CrmSalesTargetsPanel from '@/views/reports/CrmSalesTargetsPanel.vue'
import CrmSalesTargetsProgressChart from '@/views/reports/CrmSalesTargetsProgressChart.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Reports',
  },
})

const { smAndDown } = useDisplay()
const { userData } = useAppShell()
const { moment } = useJalaliDate()

const hasCore = computed(() => Boolean(userData.value?.hasCoreModule))
const canView = computed(() =>
  Boolean(userData.value?.tenant?.isOwner)
  || userData.value?.role === 'sales_manager'
  || userData.value?.department === 'sales',
)

const currentJYear = moment().jYear()
const currentJMonth = moment().jMonth() + 1
const filterYear = ref(currentJYear)
const filterMonth = ref(currentJMonth)
const progressRows = ref([])

const yearItems = computed(() =>
  Array.from({ length: 5 }, (_, i) => {
    const y = currentJYear - 2 + i

    return { title: y.toLocaleString('fa-IR'), value: y }
  }),
)

const monthItems = computed(() =>
  Array.from({ length: 12 }, (_, i) => ({
    title: moment().jYear(filterYear.value).jMonth(i).format('jMMMM'),
    value: i + 1,
  })),
)

const monthLabel = computed(() =>
  moment(`${filterYear.value}/${filterMonth.value}/1`, 'jYYYY/jM/jD').format('jMMMM jYYYY'))

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const summary = computed(() => {
  const rows = progressRows.value ?? []
  const totalTarget = rows.reduce((sum, row) => sum + Number(row.revenue_target ?? 0), 0)
  const totalActual = rows.reduce((sum, row) => sum + Number(row.actual_revenue ?? 0), 0)
  const progress = totalTarget > 0
    ? Math.round(Math.min(100, (totalActual / totalTarget) * 100))
    : null

  return { totalTarget, totalActual, progress }
})

const summaryCards = computed(() => [
  {
    key: 'target',
    label: 'تارگت کل دوره',
    value: formatMoney(summary.value.totalTarget),
    icon: 'tabler-target',
    color: 'primary',
  },
  {
    key: 'actual',
    label: 'فروش واقعی',
    value: formatMoney(summary.value.totalActual),
    icon: 'tabler-currency-dollar',
    color: 'success',
  },
  {
    key: 'progress',
    label: 'درصد تحقق کلی',
    value: summary.value.progress != null ? `${summary.value.progress.toLocaleString('fa-IR')}٪` : '—',
    icon: 'tabler-chart-line',
    color: summary.value.progress != null && summary.value.progress >= 100 ? 'success' : 'info',
    progress: summary.value.progress,
  },
])

const onTargetsLoaded = data => {
  progressRows.value = data?.rows ?? []
}
</script>

<template>
  <div
    class="sales-targets-page"
    :class="{ 'sales-targets-page--mobile': smAndDown }"
  >
    <div v-if="!hasCore">
      <VRow justify="center">
        <VCol
          cols="12"
          md="8"
        >
          <VCard>
            <VCardText class="text-center pa-8">
              <VAvatar
                color="warning"
                variant="tonal"
                size="72"
                rounded
                class="mb-4"
              >
                <VIcon
                  icon="tabler-lock"
                  size="36"
                />
              </VAvatar>
              <h4 class="text-h4 mb-2">
                ماژول پایه فعال نیست
              </h4>
              <p class="text-body-1 text-medium-emphasis mb-6">
                هدف‌گذاری فروش بخشی از ماژول پایه CRM است.
              </p>
              <VBtn
                color="primary"
                :to="{ name: 'apps-tenant-modules' }"
              >
                وضعیت ماژول‌ها
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <div v-else-if="!canView">
      <VAlert
        type="warning"
        variant="tonal"
        title="دسترسی محدود"
      >
        فقط مالک، مدیر فروش و نیروهای فروش می‌توانند هدف‌گذاری را مشاهده کنند.
      </VAlert>
    </div>

    <div v-else>
      <VCard
        class="sales-targets-hero mb-4"
        variant="tonal"
        color="primary"
      >
        <VCardText class="pa-4 pa-sm-6">
          <div class="d-flex flex-wrap align-center justify-space-between gap-4">
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="elevated"
                :size="smAndDown ? 44 : 52"
                rounded
              >
                <VIcon
                  icon="tabler-target"
                  :size="smAndDown ? 22 : 28"
                />
              </VAvatar>
              <div>
                <h4 class="text-h5 text-sm-h4 mb-1">
                  هدف‌گذاری فروش
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  تعیین تارگت و پیگیری تحقق — {{ monthLabel }}
                </p>
              </div>
            </div>

            <div class="sales-targets-filters d-flex flex-wrap align-center gap-2">
              <VSelect
                v-model="filterYear"
                :items="yearItems"
                item-title="title"
                item-value="value"
                label="سال"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar"
                class="sales-targets-filter"
              />
              <VSelect
                v-model="filterMonth"
                :items="monthItems"
                item-title="title"
                item-value="value"
                label="ماه"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-month"
                class="sales-targets-filter sales-targets-filter--month"
              />
            </div>
          </div>
        </VCardText>
      </VCard>

      <VRow class="mb-4">
        <VCol
          v-for="card in summaryCards"
          :key="card.key"
          cols="12"
          sm="4"
        >
          <VCard class="sales-targets-summary-card h-100">
            <VCardText class="d-flex align-center gap-3 pa-4">
              <VAvatar
                :color="card.color"
                variant="tonal"
                size="44"
                rounded
              >
                <VIcon
                  :icon="card.icon"
                  size="22"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <div class="text-body-2 text-medium-emphasis mb-1">
                  {{ card.label }}
                </div>
                <div class="text-h5 font-weight-medium">
                  {{ card.value }}
                </div>
                <VProgressLinear
                  v-if="card.progress != null"
                  :model-value="card.progress"
                  :color="card.progress >= 100 ? 'success' : 'primary'"
                  height="6"
                  rounded
                  class="mt-2"
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol cols="12">
          <CrmSalesTargetsPanel
            :jyear="filterYear"
            :jmonth="filterMonth"
            @loaded="onTargetsLoaded"
          />
        </VCol>
        <VCol cols="12">
          <CrmSalesTargetsProgressChart :rows="progressRows" />
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.sales-targets-page {
  max-inline-size: 100%;
}

.sales-targets-page--mobile {
  touch-action: pan-y;
  -webkit-overflow-scrolling: touch;
}

.sales-targets-hero {
  overflow: hidden;
}

.sales-targets-filters {
  inline-size: 100%;
}

@media (min-width: 600px) {
  .sales-targets-filters {
    inline-size: auto;
    flex-shrink: 0;
  }
}

.sales-targets-filter {
  flex: 1 1 120px;
  min-inline-size: 0;
}

.sales-targets-filter--month {
  flex: 1 1 150px;
}

@media (min-width: 600px) {
  .sales-targets-filter {
    flex: 0 0 auto;
    inline-size: 120px;
  }

  .sales-targets-filter--month {
    inline-size: 160px;
  }
}

.sales-targets-summary-card {
  transition: box-shadow 0.2s ease;
}

.sales-targets-summary-card:hover {
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.08);
}
</style>
