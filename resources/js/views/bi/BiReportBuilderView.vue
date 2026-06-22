<script setup>
import BiLeadSourceChart from '@/views/bi/BiLeadSourceChart.vue'
import BiQuoteFunnelChart from '@/views/bi/BiQuoteFunnelChart.vue'
import BiRevenueTrendChart from '@/views/bi/BiRevenueTrendChart.vue'
import CrmMarketingFunnelChart from '@/views/reports/CrmMarketingFunnelChart.vue'
import CrmPipelineValueChart from '@/views/reports/CrmPipelineValueChart.vue'

const { fetchTemplates, fetchReport, exportTableCsv } = useCrmBi()

const loading = ref(false)
const templates = ref([])
const report = ref(null)

const selectedTemplate = ref(null)
const dateFrom = ref('')
const dateTo = ref('')
const department = ref(null)
const assigneeId = ref(null)
const assignees = ref([])

const departmentItems = [
  { title: 'همه بخش‌ها', value: null },
  { title: 'فروش', value: 'sales' },
  { title: 'بازاریابی', value: 'marketing' },
  { title: 'مالی', value: 'finance' },
]

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const formatSummaryValue = (key, val) => {
  if (['won_revenue', 'pipeline_value', 'top_ltv'].includes(key))
    return formatMoney(val)

  if (key === 'quote_acceptance_rate')
    return `${val}٪`

  return val?.toLocaleString?.('fa-IR') ?? val
}

const summaryLabel = key => ({
  won_revenue: 'درآمد برنده',
  pipeline_value: 'ارزش قیف',
  total_deals: 'کل معاملات',
  total_leads: 'کل لیدها',
  converted_leads: 'لید تبدیل‌شده',
  active_campaigns: 'کمپین فعال',
  total_contacts: 'کل مخاطبین',
  top_ltv: 'بیشترین LTV',
  dormant_contacts: 'مخاطبین کم‌فعالیت',
  total_tasks: 'کل تسک‌ها',
  completed_tasks: 'تسک تکمیل‌شده',
  total_activities: 'کل فعالیت‌ها',
  total_work_minutes: 'زمان کار (دقیقه)',
  total_quotes: 'کل پیش‌فاکتور',
  accepted_quotes: 'پذیرفته‌شده',
  quote_acceptance_rate: 'نرخ پذیرش (٪)',
}[key] ?? key)

const loadTemplates = async () => {
  try {
    templates.value = await fetchTemplates()
    if (templates.value.length && !selectedTemplate.value)
      selectedTemplate.value = templates.value[0].slug
  } catch (e) {
    console.error(e)
  }
}

const loadAssignees = async () => {
  try {
    const res = await $api('/tasks/assignees')

    assignees.value = [
      { title: 'همه', value: null },
      ...(res.users ?? []).map(u => ({ title: u.name, value: u.id })),
    ]
  } catch (e) {
    console.error(e)
  }
}

const generateReport = async () => {
  if (!selectedTemplate.value)
    return

  loading.value = true

  try {
    report.value = await fetchReport({
      template: selectedTemplate.value,
      from: dateFrom.value || undefined,
      to: dateTo.value || undefined,
      department: department.value || undefined,
      assignee_id: assigneeId.value || undefined,
    })
  } catch (e) {
    console.error(e)
    report.value = null
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadTemplates(), loadAssignees()])
})

const summaryEntries = computed(() => {
  const summary = report.value?.summary
  if (!summary)
    return []

  return Object.entries(summary).map(([key, val]) => ({
    key,
    label: summaryLabel(key),
    value: formatSummaryValue(key, val),
  }))
})

const currentTemplateMeta = computed(() =>
  templates.value.find(t => t.slug === selectedTemplate.value),
)

const showDepartmentFilter = computed(() =>
  currentTemplateMeta.value?.filters?.includes('department'),
)

const showAssigneeFilter = computed(() =>
  currentTemplateMeta.value?.filters?.includes('assignee_id'),
)
</script>

<template>
  <div>
    <div class="mb-6">
      <h5 class="text-h5 mb-1">
        گزارش‌ساز BI
      </h5>
      <p class="text-body-2 text-medium-emphasis mb-0">
        انتخاب قالب، اعمال فیلتر و دریافت خروجی
      </p>
    </div>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="selectedTemplate"
              :items="templates"
              item-title="title"
              item-value="slug"
              label="قالب گزارش"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppJalaliDatePicker
              v-model="dateFrom"
              label="از تاریخ"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppJalaliDatePicker
              v-model="dateTo"
              label="تا تاریخ"
            />
          </VCol>
          <VCol
            v-if="showDepartmentFilter"
            cols="12"
            md="4"
          >
            <VSelect
              v-model="department"
              :items="departmentItems"
              label="بخش"
            />
          </VCol>
          <VCol
            v-if="showAssigneeFilter"
            cols="12"
            md="4"
          >
            <VSelect
              v-model="assigneeId"
              :items="assignees"
              label="مسئول"
            />
          </VCol>
          <VCol
            cols="12"
            class="d-flex gap-3"
          >
            <VBtn
              color="primary"
              :loading="loading"
              prepend-icon="tabler-report-analytics"
              @click="generateReport"
            >
              تولید گزارش
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <template v-if="report">
      <VRow
        v-if="summaryEntries.length"
        class="mb-4"
      >
        <VCol
          v-for="item in summaryEntries"
          :key="item.key"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard variant="tonal">
            <VCardText>
              <div class="text-body-2 text-medium-emphasis">
                {{ item.label }}
              </div>
              <div class="text-h5">
                {{ item.value }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow class="mb-4">
        <VCol
          v-if="report.sales_pipeline?.length"
          cols="12"
          lg="6"
        >
          <CrmPipelineValueChart
            :stages="report.sales_pipeline"
            title="ارزش قیف فروش"
          />
        </VCol>
        <VCol
          v-if="report.marketing_funnel?.length"
          cols="12"
          lg="6"
        >
          <CrmMarketingFunnelChart
            :stages="report.marketing_funnel"
            title="قیف بازاریابی"
          />
        </VCol>
        <VCol
          v-if="report.revenue_trend?.length"
          cols="12"
        >
          <BiRevenueTrendChart
            :data="report.revenue_trend"
            title="روند درآمد"
          />
        </VCol>
        <VCol
          v-if="report.lead_sources?.length"
          cols="12"
          md="6"
        >
          <BiLeadSourceChart :data="report.lead_sources" />
        </VCol>
        <VCol
          v-if="report.quote_funnel?.length"
          cols="12"
          md="6"
        >
          <BiQuoteFunnelChart :data="report.quote_funnel" />
        </VCol>
      </VRow>

      <div
        v-for="table in report.tables ?? []"
        :key="table.key"
        class="mb-4"
      >
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2">
            <span>{{ table.title }}</span>
            <VBtn
              size="small"
              variant="tonal"
              prepend-icon="tabler-download"
              :disabled="!table.rows?.length"
              @click="exportTableCsv(table, `${table.key}.csv`)"
            >
              خروجی CSV
            </VBtn>
          </VCardTitle>
          <VDataTable
            :headers="table.columns.map(c => ({ title: c.title, key: c.key }))"
            :items="table.rows"
            :items-per-page="10"
            density="comfortable"
          >
            <template
              v-for="col in table.columns.filter(c => ['won_amount', 'pipeline_value', 'ltv', 'quote_revenue', 'cost_per_lead'].includes(c.key))"
              :key="col.key"
              #[`item.${col.key}`]="{ item }"
            >
              {{ formatMoney(item[col.key]) }}
            </template>
            <template #no-data>
              <div class="text-medium-emphasis text-center py-6">
                داده‌ای برای نمایش وجود ندارد.
              </div>
            </template>
          </VDataTable>
        </VCard>
      </div>
    </template>

    <VAlert
      v-else-if="!loading"
      type="info"
      variant="tonal"
      title="گزارشی تولید نشده"
      text="یک قالب انتخاب کنید و دکمه «تولید گزارش» را بزنید."
    />
  </div>
</template>
