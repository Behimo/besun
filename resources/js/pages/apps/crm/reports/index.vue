<script setup>
import CrmDropoffTable from '@/views/reports/CrmDropoffTable.vue'
import CrmMarketingFunnelChart from '@/views/reports/CrmMarketingFunnelChart.vue'
import CrmPipelineValueChart from '@/views/reports/CrmPipelineValueChart.vue'
import CrmEmployeePerformanceTable from '@/views/reports/CrmEmployeePerformanceTable.vue'
import CrmMonthlyPerformanceTable from '@/views/reports/CrmMonthlyPerformanceTable.vue'
import CrmTaskPerformanceTable from '@/views/reports/CrmTaskPerformanceTable.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Reports',
  },
})

const { userData } = useAppShell()

const hasCore = computed(() => Boolean(userData.value?.hasCoreModule))
const canViewHrReports = computed(() =>
  Boolean(userData.value?.tenant?.isOwner) || Boolean(userData.value?.isManager),
)

const loading = ref(true)
const reports = ref(null)
const monthlyPerformance = ref([])
const performanceMonth = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const selectedEmployee = ref(null)
const detailDialog = ref(false)

const { moment, formatDate } = useJalaliDate()

const currentJYear = moment().jYear()
const currentJMonth = moment().jMonth() + 1
const filterYear = ref(null)
const filterMonth = ref(null)
const filterDay = ref(null)

const yearItems = computed(() =>
  Array.from({ length: 5 }, (_, i) => {
    const y = currentJYear - 2 + i

    return { title: y.toLocaleString('fa-IR'), value: y }
  }),
)

const monthItems = computed(() =>
  Array.from({ length: 12 }, (_, i) => ({
    title: moment().jYear(filterYear.value || currentJYear).jMonth(i).format('jMMMM'),
    value: i + 1,
  })),
)

const dayItems = computed(() => {
  if (!filterMonth.value)
    return []

  const year = filterYear.value || currentJYear
  const daysInMonth = moment(`${year}/${filterMonth.value}/1`, 'jYYYY/jM/jD').endOf('jMonth').jDate()

  return Array.from({ length: daysInMonth }, (_, i) => ({
    title: (i + 1).toLocaleString('fa-IR'),
    value: i + 1,
  }))
})

const applyDateFilters = () => {
  if (!filterYear.value && !filterMonth.value && !filterDay.value) {
    dateFrom.value = ''
    dateTo.value = ''

    return
  }

  const year = filterYear.value || currentJYear

  if (filterDay.value && filterMonth.value) {
    const d = moment(`${year}/${filterMonth.value}/${filterDay.value}`, 'jYYYY/jM/jD')

    dateFrom.value = d.format('YYYY-MM-DD')
    dateTo.value = d.format('YYYY-MM-DD')

    return
  }

  if (filterMonth.value) {
    const start = moment(`${year}/${filterMonth.value}/1`, 'jYYYY/jM/jD')

    dateFrom.value = start.format('YYYY-MM-DD')
    dateTo.value = start.endOf('jMonth').format('YYYY-MM-DD')

    return
  }

  const start = moment(`${year}/1/1`, 'jYYYY/jM/jD')

  dateFrom.value = start.format('YYYY-MM-DD')
  dateTo.value = start.endOf('jYear').format('YYYY-MM-DD')
}

const clearDateFilters = () => {
  filterYear.value = null
  filterMonth.value = null
  filterDay.value = null
  dateFrom.value = ''
  dateTo.value = ''
}

watch([filterYear, filterMonth, filterDay], () => {
  if (!filterMonth.value)
    filterDay.value = null

  applyDateFilters()
})

const derivePerformanceMonth = () => {
  if (dateTo.value)
    return dateTo.value.slice(0, 7)
  if (dateFrom.value)
    return dateFrom.value.slice(0, 7)

  return new Date().toISOString().slice(0, 7)
}

const fetchMonthlyPerformance = async () => {
  if (!canViewHrReports.value)
    return

  const month = derivePerformanceMonth()
  const res = await $api('/daily-work-reports/performance', { query: { month } })

  monthlyPerformance.value = res.rows ?? []
  performanceMonth.value = res.month ?? month
}

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const fetchReports = async () => {
  if (!hasCore.value) {
    loading.value = false

    return
  }

  loading.value = true

  try {
    const params = {}

    if (dateFrom.value)
      params.from = dateFrom.value
    if (dateTo.value)
      params.to = dateTo.value

    reports.value = await $api('/reports', { query: params })

    if (canViewHrReports.value)
      await fetchMonthlyPerformance()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const openEmployeeDetail = row => {
  selectedEmployee.value = row
  detailDialog.value = true
}

const formatWorkMinutes = minutes => {
  const m = Number(minutes ?? 0)
  if (!m)
    return '—'

  const hours = Math.floor(m / 60)
  const mins = m % 60

  if (hours > 0)
    return `${hours.toLocaleString('fa-IR')} ساعت و ${mins.toLocaleString('fa-IR')} دقیقه`

  return `${mins.toLocaleString('fa-IR')} دقیقه`
}

onMounted(fetchReports)

const summaryCards = computed(() => {
  const s = reports.value?.summary
  if (!s)
    return []

  return [
    {
      icon: 'tabler-currency-dollar',
      color: 'primary',
      title: 'ارزش فعال قیف',
      stat: formatMoney(s.active_pipeline_value),
      subtitle: 'معاملات در جریان',
    },
    {
      icon: 'tabler-trophy',
      color: 'success',
      title: 'درآمد برنده',
      stat: formatMoney(s.won_revenue),
      subtitle: `${s.won_deals?.toLocaleString('fa-IR') ?? 0} معامله`,
    },
    {
      icon: 'tabler-percentage',
      color: 'info',
      title: 'نرخ تبدیل لید',
      stat: `${s.lead_conversion_rate}٪`,
      subtitle: `${s.total_leads?.toLocaleString('fa-IR') ?? 0} لید`,
    },
    {
      icon: 'tabler-speakerphone',
      color: 'warning',
      title: 'کمپین فعال',
      stat: s.active_campaigns?.toLocaleString('fa-IR') ?? 0,
      subtitle: 'در حال اجرا',
    },
  ]
})

const worstSalesDropoff = computed(() => {
  const items = reports.value?.sales_dropoff ?? []
  if (!items.length)
    return null

  return [...items].sort((a, b) => b.dropoff_rate - a.dropoff_rate)[0]
})

const bestCampaign = computed(() => {
  const items = reports.value?.campaigns ?? []
  if (!items.length)
    return null

  return [...items].sort((a, b) => b.conversion_rate - a.conversion_rate)[0]
})

const topRep = computed(() => reports.value?.team_performance?.[0] ?? null)

const channelLabel = channel => ({
  social: 'شبکه اجتماعی',
  email: 'ایمیل',
  sms: 'پیامک',
  web: 'وب',
  event: 'رویداد',
  referral: 'معرف',
}[channel] ?? channel ?? '—')

const statusColor = status => ({
  active: 'success',
  draft: 'secondary',
  paused: 'warning',
  completed: 'info',
}[status] ?? 'secondary')

</script>

<template>
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
              گزارش‌های فروش و بازاریابی بخشی از ماژول پایه CRM هستند.
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

  <div v-else>
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          گزارش فروش و بازاریابی
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          ارزش قیف، نرخ ریزش، عملکرد کمپین و مقایسه تیم
        </p>
      </div>
      <div class="d-flex flex-wrap align-center gap-3">
        <VSelect
          v-model="filterYear"
          :items="yearItems"
          item-title="title"
          item-value="value"
          label="سال"
          density="compact"
          style="max-inline-size: 120px;"
          hide-details
          clearable
        />
        <VSelect
          v-model="filterMonth"
          :items="monthItems"
          item-title="title"
          item-value="value"
          label="ماه"
          density="compact"
          style="max-inline-size: 160px;"
          hide-details
          clearable
        />
        <VSelect
          v-model="filterDay"
          :items="dayItems"
          item-title="title"
          item-value="value"
          label="روز"
          density="compact"
          style="max-inline-size: 100px;"
          hide-details
          clearable
          :disabled="!filterMonth"
        />
        <VBtn
          variant="tonal"
          size="small"
          @click="clearDateFilters"
        >
          پاک کردن
        </VBtn>
        <AppJalaliDatePicker
          v-model="dateFrom"
          label="از تاریخ"
        />
        <AppJalaliDatePicker
          v-model="dateTo"
          label="تا تاریخ"
        />
        <VBtn
          color="primary"
          :loading="loading"
          prepend-icon="tabler-refresh"
          @click="fetchReports"
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

    <VRow
      v-if="worstSalesDropoff || bestCampaign || topRep"
      class="mb-4"
    >
      <VCol
        v-if="worstSalesDropoff"
        cols="12"
        md="4"
      >
        <VAlert
          type="warning"
          variant="tonal"
          title="بیشترین ریزش فروش"
        >
          مرحله «{{ worstSalesDropoff.from_stage }}» — {{ worstSalesDropoff.dropoff_rate }}٪ ریزش
        </VAlert>
      </VCol>
      <VCol
        v-if="bestCampaign"
        cols="12"
        md="4"
      >
        <VAlert
          type="success"
          variant="tonal"
          title="موفق‌ترین کمپین"
        >
          «{{ bestCampaign.name }}» — {{ bestCampaign.conversion_rate }}٪ تبدیل
        </VAlert>
      </VCol>
      <VCol
        v-if="topRep"
        cols="12"
        md="4"
      >
        <VAlert
          type="info"
          variant="tonal"
          title="بهترین فروشنده"
        >
          {{ topRep.name }} — {{ formatMoney(topRep.won_amount) }} ریال فروش
        </VAlert>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="7"
      >
        <CrmPipelineValueChart
          :stages="reports?.sales_pipeline ?? []"
          title="ارزش معاملات در هر مرحله فروش"
        />
      </VCol>
      <VCol
        cols="12"
        lg="5"
      >
        <VCard title="جزئیات مراحل فروش">
          <VCardText class="pa-0">
            <VTable>
              <thead>
                <tr>
                  <th>مرحله</th>
                  <th>تعداد</th>
                  <th>ارزش</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="stage in reports?.sales_pipeline ?? []"
                  :key="stage.id"
                >
                  <td>
                    <VChip
                      size="x-small"
                      :color="stage.color"
                      variant="flat"
                    >
                      {{ stage.name }}
                    </VChip>
                  </td>
                  <td>{{ stage.deals_count?.toLocaleString('fa-IR') }}</td>
                  <td>{{ formatMoney(stage.total_amount) }}</td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <CrmDropoffTable
          :items="reports?.sales_dropoff ?? []"
          title="ریزش و تبدیل بین مراحل فروش"
        />
      </VCol>

      <VCol
        cols="12"
        lg="6"
      >
        <CrmMarketingFunnelChart
          :stages="reports?.marketing_funnel ?? []"
        />
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <CrmDropoffTable
          :items="reports?.marketing_dropoff ?? []"
          title="ریزش بین مراحل بازاریابی"
        />
      </VCol>

      <VCol cols="12">
        <VCard title="عملکرد کمپین‌ها">
          <VCardText class="pa-0">
            <div
              v-if="!(reports?.campaigns?.length)"
              class="text-medium-emphasis text-center py-8"
            >
              کمپینی ثبت نشده است.
            </div>
            <VTable v-else>
              <thead>
                <tr>
                  <th>کمپین</th>
                  <th>کانال</th>
                  <th>وضعیت</th>
                  <th>بودجه</th>
                  <th>لید</th>
                  <th>تبدیل</th>
                  <th>نرخ تبدیل</th>
                  <th>هزینه هر لید</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="c in reports.campaigns"
                  :key="c.id"
                >
                  <td class="font-weight-medium">
                    {{ c.name }}
                  </td>
                  <td>{{ channelLabel(c.channel) }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="statusColor(c.status)"
                      variant="tonal"
                    >
                      {{ c.status }}
                    </VChip>
                  </td>
                  <td>{{ c.budget ? formatMoney(c.budget) : '—' }}</td>
                  <td>{{ c.leads_count?.toLocaleString('fa-IR') }}</td>
                  <td>{{ c.converted_count?.toLocaleString('fa-IR') }}</td>
                  <td>
                    <VChip
                      size="small"
                      :color="c.conversion_rate >= 20 ? 'success' : c.conversion_rate >= 10 ? 'warning' : 'secondary'"
                      variant="tonal"
                    >
                      {{ c.conversion_rate }}٪
                    </VChip>
                  </td>
                  <td>{{ c.cost_per_lead ? formatMoney(c.cost_per_lead) : '—' }}</td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard title="عملکرد تیم — فروش و پیگیری">
          <VCardText class="pa-0">
            <div
              v-if="!(reports?.team_performance?.length)"
              class="text-medium-emphasis text-center py-8"
            >
              معامله یا لیدی با مسئول مشخص ثبت نشده است.
            </div>
            <VTable v-else>
              <thead>
                <tr>
                  <th>عضو تیم</th>
                  <th>معاملات</th>
                  <th>برنده</th>
                  <th>باخته</th>
                  <th>نرخ برد</th>
                  <th>فروش (ریال)</th>
                  <th>ارزش قیف</th>
                  <th>لیدها</th>
                  <th>تبدیل لید</th>
                  <th>نرخ تبدیل لید</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="rep in reports.team_performance"
                  :key="rep.user_id"
                >
                  <td class="font-weight-medium">
                    {{ rep.name }}
                  </td>
                  <td>{{ rep.deals_count?.toLocaleString('fa-IR') }}</td>
                  <td>{{ rep.won_deals?.toLocaleString('fa-IR') }}</td>
                  <td>{{ rep.lost_deals?.toLocaleString('fa-IR') }}</td>
                  <td>
                    <VChip
                      size="small"
                      :color="rep.win_rate >= 50 ? 'success' : 'secondary'"
                      variant="tonal"
                    >
                      {{ rep.win_rate }}٪
                    </VChip>
                  </td>
                  <td>{{ formatMoney(rep.won_amount) }}</td>
                  <td>{{ formatMoney(rep.pipeline_value) }}</td>
                  <td>{{ rep.leads_count?.toLocaleString('fa-IR') }}</td>
                  <td>{{ rep.converted_leads?.toLocaleString('fa-IR') }}</td>
                  <td>{{ rep.lead_conversion_rate }}٪</td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        v-if="canViewHrReports"
        cols="12"
      >
        <VCard title="عملکرد پرسنل — تسک‌ها (HR)">
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-4">
              نرخ تکمیل، سررسید گذشته و عملکرد به‌موقع هر عضو تیم بر اساس تسک‌های واگذارشده
            </p>
            <CrmTaskPerformanceTable :rows="reports?.task_performance ?? []" />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        v-if="canViewHrReports"
        cols="12"
      >
        <VCard title="عملکرد ماهانه — بازبینی گزارش کار">
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-4">
              رتبه‌بندی کیفیت کار نیروها بر اساس امتیاز و فیدبک مدیر روی گزارش‌های روزانه ارسال‌شده
            </p>
            <CrmMonthlyPerformanceTable
              :rows="monthlyPerformance"
              :month="performanceMonth"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        v-if="canViewHrReports"
        cols="12"
      >
        <VCard title="عملکرد پرسنل — گزارش روزانه و زمان کار (HR)">
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-4">
              رتبه‌بندی کلی بر اساس تکمیل تسک، گزارش روزانه، زمان کار و امتیاز مدیر
            </p>
            <CrmEmployeePerformanceTable
              :rows="reports?.employee_performance ?? []"
              @select="openEmployeeDetail"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="detailDialog"
      max-width="720"
    >
      <VCard
        v-if="selectedEmployee"
        :title="`جزئیات عملکرد — ${selectedEmployee.name}`"
      >
        <VCardText>
          <VRow class="mb-4">
            <VCol cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                نمره کلی
              </div>
              <div class="text-h6">
                {{ selectedEmployee.performance_score }}٪
              </div>
            </VCol>
            <VCol cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                رتبه
              </div>
              <div class="text-h6">
                {{ selectedEmployee.rank?.toLocaleString('fa-IR') }}
              </div>
            </VCol>
            <VCol cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                زمان کار
              </div>
              <div class="text-body-1">
                {{ formatWorkMinutes(selectedEmployee.total_work_minutes) }}
              </div>
            </VCol>
            <VCol cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                گزارش‌های ارسالی
              </div>
              <div class="text-body-1">
                {{ selectedEmployee.daily_reports_submitted?.toLocaleString('fa-IR') ?? 0 }}
              </div>
            </VCol>
          </VRow>

          <h6 class="text-subtitle-1 mb-2">
            توضیحات تکمیل تسک‌ها
          </h6>
          <div
            v-if="selectedEmployee.completion_notes?.length"
            class="mb-4"
          >
            <VAlert
              v-for="note in selectedEmployee.completion_notes"
              :key="note.task_id"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-2"
            >
              <div class="font-weight-medium">
                {{ note.title }}
              </div>
              <div class="text-caption text-medium-emphasis mb-1">
                {{ note.completed_at }}
              </div>
              {{ note.note }}
            </VAlert>
          </div>
          <p
            v-else
            class="text-medium-emphasis mb-4"
          >
            توضیحات تکمیل ثبت نشده است.
          </p>

          <h6 class="text-subtitle-1 mb-2">
            بازخوردهای مدیر روی گزارش کار
          </h6>
          <div
            v-if="selectedEmployee.manager_feedbacks?.length"
            class="mb-4"
          >
            <VAlert
              v-for="(fb, idx) in selectedEmployee.manager_feedbacks"
              :key="idx"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-2"
            >
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="font-weight-medium">{{ fb.report_date_jalali || formatDate(fb.report_date) }}</span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  امتیاز {{ fb.score }}
                </VChip>
              </div>
              <div
                v-if="fb.reviewer?.name"
                class="text-caption text-medium-emphasis mb-1"
              >
                بازخورد توسط: {{ fb.reviewer.name }}
              </div>
              {{ fb.feedback }}
            </VAlert>
          </div>
          <p
            v-else
            class="text-medium-emphasis mb-4"
          >
            بازخوردی از مدیر ثبت نشده است.
          </p>

          <h6 class="text-subtitle-1 mb-2">
            آیتم‌های گزارش کار روزانه
          </h6>
          <div v-if="selectedEmployee.daily_entries?.length">
            <div
              v-for="(entry, idx) in selectedEmployee.daily_entries"
              :key="idx"
              class="mb-3 pa-3 rounded border"
            >
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="font-weight-medium">{{ entry.title }}</span>
                <VChip
                  size="x-small"
                  variant="tonal"
                >
                  {{ entry.report_date_jalali || formatDate(entry.report_date) }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis mb-1">
                {{ formatWorkMinutes(entry.minutes) }} — نمره {{ entry.effort_score }}
              </div>
              <div
                v-if="entry.description"
                class="text-body-2"
              >
                {{ entry.description }}
              </div>
            </div>
          </div>
          <p
            v-else
            class="text-medium-emphasis"
          >
            گزارش روزانه‌ای در این بازه ثبت نشده است.
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="detailDialog = false">
            بستن
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
