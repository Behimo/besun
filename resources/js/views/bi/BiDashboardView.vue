<script setup>
import BiDealFlowChart from '@/views/bi/BiDealFlowChart.vue'
import BiDepartmentChart from '@/views/bi/BiDepartmentChart.vue'
import BiForecastChart from '@/views/bi/BiForecastChart.vue'
import BiLeadSourceChart from '@/views/bi/BiLeadSourceChart.vue'
import BiQuoteFunnelChart from '@/views/bi/BiQuoteFunnelChart.vue'
import BiRevenueTrendChart from '@/views/bi/BiRevenueTrendChart.vue'
import BiTopContactsTable from '@/views/bi/BiTopContactsTable.vue'
import BiTopProductsTable from '@/views/bi/BiTopProductsTable.vue'

const { fetchDashboard } = useCrmBi()

const loading = ref(true)
const loadError = ref('')
const dashboard = ref(null)
const dateFrom = ref('')
const dateTo = ref('')
const granularity = ref('month')

const granularityItems = [
  { title: 'ماهانه', value: 'month' },
  { title: 'هفتگی', value: 'week' },
]

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const loadDashboard = async () => {
  loading.value = true
  loadError.value = ''

  try {
    dashboard.value = await fetchDashboard({
      from: dateFrom.value || undefined,
      to: dateTo.value || undefined,
      granularity: granularity.value,
    })
  } catch (e) {
    console.error(e)
    dashboard.value = null
    loadError.value = e?.data?.message ?? e?.message ?? 'بارگذاری داشبورد BI ناموفق بود.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)

const summaryCards = computed(() => {
  const s = dashboard.value?.summary
  if (!s)
    return []

  return [
    {
      icon: 'tabler-trophy',
      color: 'success',
      title: 'درآمد برنده',
      stat: formatMoney(s.won_revenue),
      subtitle: s.revenue_growth != null ? `رشد ${s.revenue_growth}٪ نسبت به دوره قبل` : '',
    },
    {
      icon: 'tabler-chart-line',
      color: 'primary',
      title: 'ارزش قیف فعال',
      stat: formatMoney(s.active_pipeline_value),
      subtitle: 'معاملات در جریان',
    },
    {
      icon: 'tabler-receipt',
      color: 'info',
      title: 'نرخ پذیرش پیش‌فاکتور',
      stat: `${s.quote_acceptance_rate}٪`,
      subtitle: `میانگین معامله: ${formatMoney(s.avg_deal_size)}`,
    },
    {
      icon: 'tabler-clock',
      color: 'warning',
      title: 'میانگین زمان بستن',
      stat: `${s.avg_close_days} روز`,
      subtitle: `زمان کار: ${(s.total_work_minutes ?? 0).toLocaleString('fa-IR')} دقیقه`,
    },
  ]
})

const activityLabel = type => ({
  call: 'تماس',
  meeting: 'جلسه',
  note: 'یادداشت',
}[type] ?? type)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h5 class="text-h5 mb-1">
          داشبورد هوش تجاری
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          روند درآمد، پیش‌بینی، محصولات و بهره‌وری تیم
        </p>
      </div>
      <div class="d-flex flex-wrap align-center gap-3">
        <AppJalaliDatePicker
          v-model="dateFrom"
          label="از تاریخ"
        />
        <AppJalaliDatePicker
          v-model="dateTo"
          label="تا تاریخ"
        />
        <VSelect
          v-model="granularity"
          :items="granularityItems"
          label="دانه‌بندی"
          style="min-inline-size: 140px;"
        />
        <VBtn
          color="primary"
          :loading="loading"
          prepend-icon="tabler-refresh"
          @click="loadDashboard"
        >
          بروزرسانی
        </VBtn>
      </div>
    </div>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <VAlert
      v-if="loadError"
      type="error"
      variant="tonal"
      class="mb-4"
      :text="loadError"
    />

    <VRow
      v-if="summaryCards.length"
      class="mb-4"
    >
      <VCol
        v-for="card in summaryCards"
        :key="card.title"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center gap-3">
              <VAvatar
                :color="card.color"
                variant="tonal"
                rounded
                size="44"
              >
                <VIcon
                  :icon="card.icon"
                  size="26"
                />
              </VAvatar>
              <div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ card.title }}
                </div>
                <div class="text-h5">
                  {{ card.stat }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ card.subtitle }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <BiRevenueTrendChart :data="dashboard?.revenue_trend ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <BiLeadSourceChart :data="dashboard?.lead_sources ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiDealFlowChart :data="dashboard?.deal_flow ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiForecastChart :data="dashboard?.forecast ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiQuoteFunnelChart :data="dashboard?.quote_funnel ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiDepartmentChart :data="dashboard?.department_kpis ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiTopProductsTable :items="dashboard?.top_products ?? []" />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <BiTopContactsTable :items="dashboard?.top_contacts ?? []" />
      </VCol>
      <VCol
        v-if="dashboard?.activity_breakdown?.length"
        cols="12"
        md="6"
      >
        <VCard title="فعالیت‌ها">
          <VCardText>
            <VList density="compact">
              <VListItem
                v-for="item in dashboard.activity_breakdown"
                :key="item.type"
              >
                <VListItemTitle>{{ activityLabel(item.type) }}</VListItemTitle>
                <template #append>
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ item.count?.toLocaleString('fa-IR') }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        v-if="dashboard?.task_productivity?.length"
        cols="12"
        md="6"
      >
        <VCard title="بهره‌وری تسک">
          <VDataTable
            :headers="[
              { title: 'نام', key: 'name' },
              { title: 'تکمیل', key: 'completed' },
              { title: 'نرخ (٪)', key: 'completion_rate' },
              { title: 'زمان (دقیقه)', key: 'work_minutes' },
            ]"
            :items="dashboard.task_productivity"
            :items-per-page="5"
            density="compact"
            hide-default-footer
          />
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
