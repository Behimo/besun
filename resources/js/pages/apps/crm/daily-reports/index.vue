<script setup>
import { useDisplay } from 'vuetify'

definePage({ meta: { action: 'read', subject: 'DailyReports' } })

const { smAndDown } = useDisplay()

const activeTab = ref('today')
const snackbar = ref(false)
const feedback = ref('')
const feedbackType = ref('success')

const {
  isManager,
  canReview,
  reports,
  todayReport,
  total,
  loading,
  saving,
  page,
  perPage,
  filter,
  statusFilter,
  reviewStatusFilter,
  dateFrom,
  dateTo,
  fetchToday,
  fetchReports,
  saveReport,
  submitReport,
  reviewReport,
  formatMinutes,
  statusLabel,
  statusColor,
  reviewLabel,
  reviewColor,
  reviewerLabel,
} = useCrmDailyReports()

const { moment, formatDate, formatDateTime } = useJalaliDate()

const reviewDialog = ref(false)
const selectedReport = ref(null)
const reviewForm = ref({ manager_score: 4, manager_feedback: '' })
const myRecentReports = ref([])
const editingReport = ref(null)
const entriesDialog = ref(false)
const selectedReportForEntries = ref(null)

const currentJYear = moment().jYear()
const filterYear = ref(null)
const filterMonth = ref(null)
const filterDay = ref(null)

const yearItems = computed(() =>
  Array.from({ length: 5 }, (_, i) => {
    const y = currentJYear - 2 + i

    return { title: y.toLocaleString('fa-IR'), value: y }
  }),
)

const monthItems = computed(() => {
  const year = filterYear.value || currentJYear

  return Array.from({ length: 12 }, (_, i) => ({
    title: moment().jYear(year).jMonth(i).format('jMMMM'),
    value: i + 1,
  }))
})

const dayItems = computed(() => {
  if (!filterMonth.value)
    return []

  const daysInMonth = moment(`${filterYear.value || currentJYear}/${filterMonth.value}/1`, 'jYYYY/jM/jD').endOf('jMonth').jDate()

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

const formatReportDate = report =>
  report?.report_date_jalali || formatDate(report?.report_date)

const openEntries = report => {
  selectedReportForEntries.value = report
  entriesDialog.value = true
}

const scoreItems = [1, 2, 3, 4, 5].map(v => ({
  title: `${v.toLocaleString('fa-IR')} — ${['ضعیف', 'نیاز به بهبود', 'متوسط', 'خوب', 'عالی'][v - 1]}`,
  value: v,
}))

const emptyEntry = () => ({
  title: '',
  description: '',
  minutes: 30,
  effort_score: 3,
})

const form = ref({
  summary: '',
  entries: [emptyEntry()],
})

const showFeedback = (message, type = 'success') => {
  feedback.value = message
  feedbackType.value = type
  snackbar.value = true
}

const loadFormFromReport = report => {
  if (!report) {
    form.value = { summary: '', entries: [emptyEntry()] }

    return
  }

  form.value = {
    summary: report.summary ?? '',
    entries: report.entries?.length
      ? report.entries.map(e => ({
        title: e.title ?? '',
        description: e.description ?? '',
        minutes: e.minutes ?? 0,
        effort_score: e.effort_score ?? 3,
      }))
      : [emptyEntry()],
  }
}

const addEntry = () => {
  form.value.entries.push(emptyEntry())
}

const removeEntry = index => {
  if (form.value.entries.length <= 1)
    return

  form.value.entries.splice(index, 1)
}

const buildPayload = () => ({
  summary: form.value.summary?.trim() || null,
  entries: form.value.entries
    .filter(e => e.title?.trim())
    .map(e => ({
      title: e.title.trim(),
      description: e.description?.trim() || null,
      minutes: Number(e.minutes) || 0,
      effort_score: Number(e.effort_score) || 3,
    })),
})

const saveToday = async () => {
  const payload = buildPayload()

  if (!payload.entries.length) {
    showFeedback('حداقل یک آیتم کار وارد کنید.', 'error')

    return
  }

  const activeReport = editingReport.value ?? todayReport.value

  try {
    const report = await saveReport(activeReport?.id, {
      ...payload,
      report_date: activeReport?.report_date ?? todayReport.value?.report_date ?? moment().locale('en').format('YYYY-MM-DD'),
    })

    if (editingReport.value?.id === report.id)
      editingReport.value = report
    else
      todayReport.value = report

    showFeedback('گزارش ذخیره شد.')
  } catch (e) {
    showFeedback(e?.data?.message ?? 'خطا در ذخیره گزارش', 'error')
  }
}

const sendToManager = async () => {
  await saveToday()

  const activeReport = editingReport.value ?? todayReport.value

  if (!activeReport?.id)
    return

  try {
    const report = await submitReport(activeReport.id)

    if (editingReport.value?.id === report.id)
      editingReport.value = report
    else
      todayReport.value = report

    showFeedback('گزارش به مدیر ارسال شد.')
    await fetchMyRecent()
  } catch (e) {
    showFeedback(e?.data?.message ?? 'خطا در ارسال گزارش', 'error')
  }
}

const totalMinutes = computed(() =>
  form.value.entries.reduce((sum, e) => sum + (Number(e.minutes) || 0), 0),
)

const activeReport = computed(() => editingReport.value ?? todayReport.value)

const canEditForm = computed(() => {
  const report = activeReport.value

  if (!report)
    return true

  if (report.can_edit !== undefined)
    return Boolean(report.can_edit)

  return report.status !== 'submitted'
})

const loadReportForEdit = report => {
  editingReport.value = report
  loadFormFromReport(report)
  activeTab.value = 'today'
}

const resetToToday = async () => {
  editingReport.value = null
  await fetchToday()
  loadFormFromReport(todayReport.value)
}

const openReview = report => {
  selectedReport.value = report
  reviewForm.value = {
    manager_score: report.manager_score ?? 4,
    manager_feedback: report.manager_feedback ?? '',
  }
  reviewDialog.value = true
}

const saveReview = async () => {
  if (!selectedReport.value?.id)
    return

  try {
    const updated = await reviewReport(selectedReport.value.id, reviewForm.value)
    const idx = reports.value.findIndex(r => r.id === updated.id)

    if (idx >= 0)
      reports.value[idx] = updated

    reviewDialog.value = false
    showFeedback('بازخورد و امتیاز ثبت شد.')
  } catch (e) {
    showFeedback(e?.data?.message ?? 'خطا در ثبت بازخورد', 'error')
  }
}

const fetchMyRecent = async () => {
  const res = await $api('/daily-work-reports', {
    query: { mine: 1, per_page: 15 },
  })

  myRecentReports.value = (res.data ?? []).filter(r =>
    r.report_date !== todayReport.value?.report_date,
  )
}

onMounted(async () => {
  await fetchToday()
  loadFormFromReport(todayReport.value)
  await fetchMyRecent()
})

watch(isManager, manager => {
  if (manager && activeTab.value === 'team')
    fetchReports()
})

watch(activeTab, tab => {
  if (tab === 'team' && isManager.value)
    fetchReports()
})

watch([filter, statusFilter, reviewStatusFilter, dateFrom, dateTo, page, perPage], () => {
  if (activeTab.value === 'team')
    fetchReports()
})

watch([filterYear, filterMonth, filterDay], () => {
  if (activeTab.value !== 'team')
    return

  if (!filterMonth.value)
    filterDay.value = null

  applyDateFilters()
  page.value = 1
  fetchReports()
})

watch(todayReport, report => {
  if (report && !editingReport.value)
    loadFormFromReport(report)
})
</script>

<template>
  <VCard class="daily-reports-card">
    <VCardText>
      <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
        <div>
          <h5 class="text-h5">
            گزارش کار روزانه
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-0">
            کارهای روزانه، زمان صرف‌شده و توضیحات را به مدیر اعلام کنید
          </p>
        </div>
      </div>

      <VTabs
        v-model="activeTab"
        class="mb-4"
      >
        <VTab value="today">
          گزارش امروز
        </VTab>
        <VTab
          v-if="isManager"
          value="team"
        >
          گزارش‌های تیم
        </VTab>
      </VTabs>

      <VWindow
        v-model="activeTab"
        :touch="false"
      >
        <VWindowItem value="today">
          <VAlert
            v-if="editingReport"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <span>
                در حال ویرایش گزارش {{ formatReportDate(editingReport) }}
              </span>
              <VBtn
                size="small"
                variant="tonal"
                @click="resetToToday"
              >
                بازگشت به گزارش امروز
              </VBtn>
            </div>
          </VAlert>

          <VAlert
            v-else-if="todayReport?.status === 'submitted' && !canEditForm"
            type="success"
            variant="tonal"
            class="mb-4"
          >
            این گزارش در {{ formatDateTime(todayReport.submitted_at) || formatReportDate(todayReport) }} به مدیر ارسال شده است.
          </VAlert>

          <VAlert
            v-else-if="todayReport?.status === 'submitted' && canEditForm"
            type="info"
            variant="tonal"
            class="mb-4"
          >
            گزارش ارسال شده — تا ۲۴ ساعت پس از ارسال و قبل از بازخورد مدیر می‌توانید ویرایش کنید.
          </VAlert>

          <VAlert
            v-if="todayReport?.manager_score"
            type="info"
            variant="tonal"
            class="mb-4"
          >
            <div class="font-weight-medium mb-1">
              بازخورد {{ reviewerLabel(todayReport) ?? 'مدیر' }}
              — امتیاز {{ todayReport.manager_score?.toLocaleString('fa-IR') }} از ۵
            </div>
            <div
              v-if="todayReport.reviewed_at"
              class="text-caption text-medium-emphasis mb-1"
            >
              {{ formatDateTime(todayReport.reviewed_at) }}
            </div>
            <div v-if="todayReport.manager_feedback">
              {{ todayReport.manager_feedback }}
            </div>
          </VAlert>

          <AppTextarea
            v-model="form.summary"
            label="خلاصه روز (اختیاری)"
            rows="2"
            class="mb-4"
            :readonly="!canEditForm"
          />

          <div
            v-for="(entry, index) in form.entries"
            :key="index"
            class="mb-4 pa-4 rounded border"
          >
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-subtitle-2">آیتم {{ (index + 1).toLocaleString('fa-IR') }}</span>
              <IconBtn
                v-if="canEditForm && form.entries.length > 1"
                size="small"
                @click="removeEntry(index)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </div>

            <AppTextField
              v-model="entry.title"
              label="عنوان کار *"
              class="mb-3"
              :readonly="!canEditForm"
            />
            <AppTextarea
              v-model="entry.description"
              label="توضیحات انجام‌شده"
              rows="2"
              class="mb-3"
              :readonly="!canEditForm"
            />
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model.number="entry.minutes"
                  label="زمان (دقیقه)"
                  type="number"
                  min="0"
                  :disabled="!canEditForm"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="entry.effort_score"
                  :items="[1, 2, 3, 4, 5]"
                  label="نمره کیفیت/سختی (۱–۵)"
                  :disabled="!canEditForm"
                />
              </VCol>
            </VRow>
          </div>

          <VBtn
            v-if="canEditForm"
            variant="tonal"
            prepend-icon="tabler-plus"
            class="mb-4"
            @click="addEntry"
          >
            افزودن آیتم
          </VBtn>

          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            جمع زمان: {{ formatMinutes(totalMinutes) }}
          </VAlert>

          <div
            v-if="canEditForm"
            class="d-flex gap-3 flex-wrap"
          >
            <VBtn
              color="primary"
              :loading="saving"
              @click="saveToday"
            >
              ذخیره پیش‌نویس
            </VBtn>
            <VBtn
              v-if="activeReport?.status !== 'submitted'"
              color="success"
              :loading="saving"
              @click="sendToManager"
            >
              ارسال به مدیر
            </VBtn>
            <VBtn
              v-else-if="canEditForm"
              color="success"
              variant="tonal"
              :loading="saving"
              @click="saveToday"
            >
              ذخیره ویرایش
            </VBtn>
          </div>

          <div
            v-if="myRecentReports.length"
            class="mt-8"
          >
            <h6 class="text-subtitle-1 mb-3">
              گزارش‌های اخیر و بازخورد مدیر
            </h6>
            <div
              v-for="report in myRecentReports"
              :key="report.id"
              class="mb-3 pa-3 rounded border"
            >
              <div class="d-flex justify-space-between align-center mb-2 flex-wrap gap-2">
                <span class="font-weight-medium">{{ formatReportDate(report) }}</span>
                <div class="d-flex gap-2 align-center">
                  <VBtn
                    v-if="report.can_edit"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-edit"
                    @click="loadReportForEdit(report)"
                  >
                    ویرایش
                  </VBtn>
                  <VChip
                    v-if="report.manager_score"
                    size="small"
                    :color="reviewColor(report)"
                    variant="tonal"
                  >
                    امتیاز {{ report.manager_score }}
                  </VChip>
                  <VChip
                    v-else
                    size="small"
                    color="warning"
                    variant="tonal"
                  >
                    در انتظار بازبینی
                  </VChip>
                </div>
              </div>
              <div
                v-if="report.manager_score && reviewerLabel(report)"
                class="text-caption text-medium-emphasis mb-1"
              >
                بازخورد توسط: {{ reviewerLabel(report) }}
              </div>
              <p
                v-if="report.manager_feedback"
                class="text-body-2 mb-0"
              >
                {{ report.manager_feedback }}
              </p>
              <p
                v-else-if="report.manager_score"
                class="text-body-2 text-medium-emphasis mb-0"
              >
                بازخورد متنی ثبت نشده است.
              </p>
            </div>
          </div>
        </VWindowItem>

        <VWindowItem
          v-if="isManager"
          value="team"
        >
          <div class="team-reports-panel">
            <VRow
              class="mb-4"
              dense
            >
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="filterYear"
                  :items="yearItems"
                  item-title="title"
                  item-value="value"
                  label="سال"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="filterMonth"
                  :items="monthItems"
                  item-title="title"
                  item-value="value"
                  label="ماه"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="filterDay"
                  :items="dayItems"
                  item-title="title"
                  item-value="value"
                  label="روز"
                  density="compact"
                  hide-details
                  clearable
                  :disabled="!filterMonth"
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="filter"
                  :items="[
                    { title: 'تیم واحد من', value: 'all' },
                    { title: 'گزارش‌های من', value: 'mine' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="محدوده"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="statusFilter"
                  :items="[
                    { title: 'همه', value: null },
                    { title: 'پیش‌نویس', value: 'draft' },
                    { title: 'ارسال‌شده', value: 'submitted' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="وضعیت"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
                md="2"
              >
                <VSelect
                  v-model="reviewStatusFilter"
                  :items="[
                    { title: 'همه بازبینی‌ها', value: null },
                    { title: 'در انتظار بازبینی', value: 'pending' },
                    { title: 'بازبینی‌شده', value: 'reviewed' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="بازبینی"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
                md="2"
                class="d-flex align-end"
              >
                <VBtn
                  variant="tonal"
                  size="small"
                  block
                  @click="clearDateFilters"
                >
                  پاک کردن تاریخ
                </VBtn>
              </VCol>
            </VRow>

            <!-- Mobile card list -->
            <div
              v-if="smAndDown"
              class="team-reports-mobile"
            >
              <VProgressLinear
                v-if="loading"
                indeterminate
                class="mb-4"
              />

              <VCard
                v-for="report in reports"
                :key="report.id"
                variant="outlined"
                class="mb-3"
              >
                <VCardText>
                  <div class="d-flex justify-space-between align-start gap-2 mb-2">
                    <div>
                      <div class="font-weight-medium">
                        {{ report.user?.name ?? '—' }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ formatReportDate(report) }}
                      </div>
                    </div>
                    <VChip
                      size="small"
                      :color="statusColor(report.status)"
                      variant="tonal"
                    >
                      {{ statusLabel(report.status) }}
                    </VChip>
                  </div>

                  <div
                    v-if="report.status === 'submitted'"
                    class="mb-2"
                  >
                    <VChip
                      size="small"
                      :color="reviewColor(report)"
                      variant="tonal"
                    >
                      {{ reviewLabel(report) }}
                    </VChip>
                    <div
                      v-if="reviewerLabel(report)"
                      class="text-caption text-medium-emphasis mt-1"
                    >
                      {{ reviewerLabel(report) }}
                    </div>
                  </div>

                  <div
                    v-if="report.manager_feedback"
                    class="text-body-2 mb-2"
                  >
                    {{ report.manager_feedback }}
                  </div>

                  <div class="text-caption text-medium-emphasis mb-3">
                    زمان کل: {{ formatMinutes(report.total_minutes) }}
                  </div>

                  <div class="d-flex flex-wrap gap-2">
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-list-details"
                      @click="openEntries(report)"
                    >
                      {{ (report.entries?.length ?? 0).toLocaleString('fa-IR') }} آیتم
                    </VBtn>
                    <VBtn
                      v-if="canReview && report.status === 'submitted'"
                      size="small"
                      variant="tonal"
                      :color="report.manager_score ? 'secondary' : 'primary'"
                      @click="openReview(report)"
                    >
                      {{ report.manager_score ? 'ویرایش بازخورد' : 'ثبت بازخورد' }}
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>

              <p
                v-if="!loading && !reports.length"
                class="text-medium-emphasis text-center py-6 mb-0"
              >
                گزارشی یافت نشد.
              </p>

              <div
                v-if="total > perPage"
                class="d-flex justify-center mt-4"
              >
                <VPagination
                  v-model="page"
                  :length="Math.ceil(total / perPage)"
                  :total-visible="5"
                  density="compact"
                />
              </div>
            </div>

            <!-- Desktop table -->
            <div
              v-else
              class="team-reports-table"
            >
              <VDataTableServer
                :items="reports"
                item-value="id"
                :items-length="total"
                :loading="loading"
                v-model:page="page"
                v-model:items-per-page="perPage"
                :headers="[
                  { title: 'کارمند', key: 'user' },
                  { title: 'تاریخ', key: 'report_date' },
                  { title: 'وضعیت', key: 'status' },
                  { title: 'بازبینی', key: 'review' },
                  { title: 'بازخورد', key: 'manager_feedback' },
                  { title: 'زمان کل', key: 'total_minutes' },
                  { title: 'آیتم‌ها', key: 'entries' },
                  { title: 'عملیات', key: 'actions', sortable: false },
                ]"
              >
            <template #item.user="{ item }">
              {{ item.user?.name ?? '—' }}
            </template>
            <template #item.report_date="{ item }">
              {{ formatReportDate(item) }}
            </template>
            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="statusColor(item.status)"
                variant="tonal"
              >
                {{ statusLabel(item.status) }}
              </VChip>
            </template>
            <template #item.review="{ item }">
              <div v-if="item.status === 'submitted'">
                <VChip
                  size="small"
                  :color="reviewColor(item)"
                  variant="tonal"
                >
                  {{ reviewLabel(item) }}
                </VChip>
                <div
                  v-if="reviewerLabel(item)"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  {{ reviewerLabel(item) }}
                </div>
              </div>
              <span v-else>—</span>
            </template>
            <template #item.manager_feedback="{ item }">
              <span
                v-if="item.manager_feedback"
                class="text-body-2 text-truncate d-inline-block"
                style="max-inline-size: 180px;"
              >
                {{ item.manager_feedback }}
              </span>
              <span
                v-else
                class="text-medium-emphasis"
              >—</span>
            </template>
            <template #item.total_minutes="{ item }">
              {{ formatMinutes(item.total_minutes) }}
            </template>
            <template #item.entries="{ item }">
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-list-details"
                @click="openEntries(item)"
              >
                {{ (item.entries?.length ?? 0).toLocaleString('fa-IR') }} آیتم
              </VBtn>
            </template>
            <template #item.actions="{ item }">
              <VBtn
                v-if="canReview && item.status === 'submitted'"
                size="small"
                variant="tonal"
                :color="item.manager_score ? 'secondary' : 'primary'"
                @click="openReview(item)"
              >
                {{ item.manager_score ? 'ویرایش بازخورد' : 'ثبت بازخورد' }}
              </VBtn>
            </template>
              </VDataTableServer>
            </div>
          </div>
        </VWindowItem>
      </VWindow>
    </VCardText>

    <VDialog
      v-model="reviewDialog"
      max-width="520"
    >
      <VCard v-if="selectedReport">
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>بازبینی گزارش — {{ selectedReport.user?.name }}</span>
          <DialogCloseBtn @click="reviewDialog = false" />
        </VCardTitle>
        <VCardText>
          <p class="text-caption text-medium-emphasis mb-4">
            تاریخ: {{ formatReportDate(selectedReport) }}
          </p>

          <div
            v-if="selectedReport.summary"
            class="mb-4 pa-3 rounded border text-body-2"
          >
            <span class="font-weight-medium">خلاصه کارمند:</span>
            {{ selectedReport.summary }}
          </div>

          <VSelect
            v-model="reviewForm.manager_score"
            :items="scoreItems"
            item-title="title"
            item-value="value"
            label="امتیاز کیفیت (۱ تا ۵) *"
            class="mb-4"
          />

          <AppTextarea
            v-model="reviewForm.manager_feedback"
            label="فیدبک و توضیحات برای کارمند"
            rows="4"
            placeholder="نقاط قوت، موارد قابل بهبود، پیشنهادها..."
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="reviewDialog = false"
          >
            انصراف
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveReview"
          >
            ثبت بازخورد
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="entriesDialog"
      max-width="640"
      scrollable
    >
      <VCard v-if="selectedReportForEntries">
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>
            آیتم‌های کار — {{ selectedReportForEntries.user?.name }}
          </span>
          <DialogCloseBtn @click="entriesDialog = false" />
        </VCardTitle>
        <VCardSubtitle class="px-6 pb-2">
          {{ formatReportDate(selectedReportForEntries) }}
          — {{ formatMinutes(selectedReportForEntries.total_minutes) }}
        </VCardSubtitle>
        <VCardText>
          <div
            v-if="selectedReportForEntries.summary"
            class="mb-4 pa-3 rounded border text-body-2"
          >
            <span class="font-weight-medium">خلاصه:</span>
            {{ selectedReportForEntries.summary }}
          </div>

          <div
            v-if="selectedReportForEntries.manager_score"
            class="mb-4 pa-3 rounded border"
          >
            <div class="font-weight-medium mb-1">
              بازخورد {{ reviewerLabel(selectedReportForEntries) ?? 'مدیر' }}
              — امتیاز {{ selectedReportForEntries.manager_score?.toLocaleString('fa-IR') }}
            </div>
            <div
              v-if="selectedReportForEntries.manager_feedback"
              class="text-body-2"
            >
              {{ selectedReportForEntries.manager_feedback }}
            </div>
          </div>

          <div
            v-for="(entry, index) in selectedReportForEntries.entries ?? []"
            :key="entry.id ?? index"
            class="mb-3 pa-3 rounded border"
          >
            <div class="d-flex justify-space-between align-center mb-1 flex-wrap gap-2">
              <span class="font-weight-medium">{{ entry.title }}</span>
              <VChip
                size="x-small"
                variant="tonal"
              >
                {{ formatMinutes(entry.minutes) }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis mb-1">
              نمره کیفیت: {{ entry.effort_score?.toLocaleString('fa-IR') ?? '—' }}
            </div>
            <div
              v-if="entry.description"
              class="text-body-2"
            >
              {{ entry.description }}
            </div>
          </div>

          <p
            v-if="!(selectedReportForEntries.entries?.length)"
            class="text-medium-emphasis mb-0"
          >
            آیتمی ثبت نشده است.
          </p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="entriesDialog = false">
            بستن
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar"
      :color="feedbackType"
      location="top end"
    >
      {{ feedback }}
    </VSnackbar>
  </VCard>
</template>

<style scoped>
.daily-reports-card {
  max-inline-size: 100%;
  overflow: hidden;
}

.team-reports-panel {
  max-inline-size: 100%;
  overflow: hidden;
}

.team-reports-table :deep(.v-table) {
  min-inline-size: 720px;
}

.team-reports-table {
  max-inline-size: 100%;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
}

.team-reports-mobile {
  max-inline-size: 100%;
  touch-action: pan-y;
}
</style>
