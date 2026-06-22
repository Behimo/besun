export const useCrmDailyReports = () => {
  const { userData } = useAppShell()

  const isOwner = computed(() => Boolean(userData.value?.tenant?.isOwner))

  const permissions = computed(() => userData.value?.permissions ?? [])

  // Can see the reports of department members (team tab, performance, ...)
  const isManager = computed(() =>
    isOwner.value
    || Boolean(userData.value?.isManager)
    || permissions.value.includes('daily_reports.view_team'),
  )

  const canReview = computed(() =>
    isOwner.value || permissions.value.includes('daily_reports.review'),
  )

  const reports = ref([])
  const todayReport = ref(null)
  const performanceRows = ref([])
  const performanceMonth = ref('')
  const total = ref(0)
  const loading = ref(false)
  const saving = ref(false)
  const page = ref(1)
  const perPage = ref(15)
  const filter = ref('all')
  const statusFilter = ref(null)
  const reviewStatusFilter = ref(null)
  const dateFrom = ref('')
  const dateTo = ref('')

  const fetchToday = async () => {
    loading.value = true
    try {
      const res = await $api('/daily-work-reports/today')
      todayReport.value = res.report ?? res
    } finally {
      loading.value = false
    }
  }

  const fetchReports = async () => {
    loading.value = true
    try {
      const query = {
        page: page.value,
        per_page: perPage.value,
      }

      if (filter.value === 'mine')
        query.mine = 1
      if (statusFilter.value)
        query.status = statusFilter.value
      if (reviewStatusFilter.value)
        query.review_status = reviewStatusFilter.value
      if (dateFrom.value)
        query.from = dateFrom.value
      if (dateTo.value)
        query.to = dateTo.value

      const res = await $api('/daily-work-reports', { query })
      const rows = res.data ?? []

      reports.value = rows
      total.value = res.meta?.total ?? res.total ?? rows.length
    } finally {
      loading.value = false
    }
  }

  const fetchPerformance = async month => {
    if (!isManager.value)
      return

    loading.value = true
    try {
      const res = await $api('/daily-work-reports/performance', {
        query: month ? { month } : {},
      })

      performanceRows.value = res.rows ?? []
      performanceMonth.value = res.month ?? month ?? ''
    } finally {
      loading.value = false
    }
  }

  const saveReport = async (id, payload) => {
    saving.value = true
    try {
      if (id) {
        const { report_date, ...body } = payload
        const res = await $api(`/daily-work-reports/${id}`, { method: 'PUT', body })

        return res.report ?? res
      }

      const res = await $api('/daily-work-reports', { method: 'POST', body: payload })

      return res.report ?? res
    } finally {
      saving.value = false
    }
  }

  const submitReport = async id => {
    saving.value = true
    try {
      const res = await $api(`/daily-work-reports/${id}/submit`, { method: 'POST' })

      return res.report ?? res
    } finally {
      saving.value = false
    }
  }

  const reviewReport = async (id, payload) => {
    saving.value = true
    try {
      const res = await $api(`/daily-work-reports/${id}/review`, { method: 'POST', body: payload })

      return res.report ?? res
    } finally {
      saving.value = false
    }
  }

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

  const statusLabel = status => ({
    draft: 'پیش‌نویس',
    submitted: 'ارسال‌شده',
  }[status] ?? status)

  const statusColor = status => ({
    draft: 'warning',
    submitted: 'success',
  }[status] ?? 'secondary')

  const reviewLabel = report => {
    if (report.status !== 'submitted')
      return '—'
    if (report.manager_score != null) {
      const reviewer = report.reviewer?.name ? ` (${report.reviewer.name})` : ''

      return `امتیاز ${report.manager_score}${reviewer}`
    }

    return 'در انتظار بازبینی'
  }

  const reviewerLabel = report => report.reviewer?.name ?? null

  const reviewColor = report => {
    if (report.manager_score == null)
      return 'warning'
    if (report.manager_score >= 4)
      return 'success'
    if (report.manager_score >= 3)
      return 'info'

    return 'error'
  }

  const qualityColor = label => ({
    'عالی': 'success',
    'خوب': 'info',
    'متوسط': 'warning',
    'نیاز به بهبود': 'error',
  }[label] ?? 'secondary')

  return {
    isManager,
    canReview,
    reports,
    todayReport,
    performanceRows,
    performanceMonth,
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
    fetchPerformance,
    saveReport,
    submitReport,
    reviewReport,
    formatMinutes,
    statusLabel,
    statusColor,
    reviewLabel,
    reviewColor,
    reviewerLabel,
    qualityColor,
  }
}

