const STATUS_LABELS = {
  pending: 'در انتظار',
  in_progress: 'در حال انجام',
  completed: 'انجام‌شده',
}

const PRIORITY_LABELS = {
  low: 'کم',
  medium: 'متوسط',
  high: 'بالا',
}

const STATUS_COLORS = {
  pending: 'secondary',
  in_progress: 'info',
  completed: 'success',
}

const PRIORITY_COLORS = {
  low: 'info',
  medium: 'warning',
  high: 'error',
}

export function useCrmTasks() {
  const { userData } = useAppShell()

  const isOwner = computed(() => Boolean(userData.value?.tenant?.isOwner))

  const permissions = computed(() => userData.value?.permissions ?? [])

  const isManager = computed(() =>
    isOwner.value || Boolean(userData.value?.isManager),
  )

  const canViewTeam = computed(() =>
    isOwner.value || permissions.value.includes('tasks.view_team'),
  )

  const canAssignTasks = computed(() =>
    isOwner.value || permissions.value.includes('tasks.assign'),
  )

  const currentUserId = computed(() => userData.value?.id ?? null)

  const tasks = ref([])
  const total = ref(0)
  const loading = ref(false)
  const users = ref([])
  const filter = ref('all')
  const statusFilter = ref(null)
  const overdueOnly = ref(false)
  const page = ref(1)
  const perPage = ref(15)

  const fetchUsers = async () => {
    if (!canAssignTasks.value)
      return

    try {
      const res = await $api('/tasks/assignees')
      users.value = res.users ?? []
    } catch {
      users.value = []
    }
  }

  const buildQuery = () => {
    const query = {
      page: page.value,
      per_page: perPage.value,
    }

    if (filter.value === 'mine')
      query.mine = 1
    else if (filter.value === 'assigned_by_me' && (canAssignTasks.value || canViewTeam.value))
      query.assigned_by_me = 1

    if (statusFilter.value)
      query.status = statusFilter.value

    if (overdueOnly.value)
      query.overdue = 1

    return query
  }

  const fetchTasks = async () => {
    loading.value = true
    try {
      const res = await $api('/tasks', { query: buildQuery() })
      tasks.value = res.data ?? []
      total.value = res.meta?.total ?? res.total ?? tasks.value.length
    } finally {
      loading.value = false
    }
  }

  const saveTask = async (payload, id = null) => {
    if (id)
      return await $api(`/tasks/${id}`, { method: 'PUT', body: payload })

    return await $api('/tasks', { method: 'POST', body: payload })
  }

  const deleteTask = async id => {
    await $api(`/tasks/${id}`, { method: 'DELETE' })
  }

  const fetchTask = async id => {
    const res = await $api(`/tasks/${id}`)

    return res.task
  }

  const statusLabel = status => STATUS_LABELS[status] ?? status
  const priorityLabel = priority => PRIORITY_LABELS[priority] ?? priority
  const statusColor = status => STATUS_COLORS[status] ?? 'secondary'
  const priorityColor = priority => PRIORITY_COLORS[priority] ?? 'secondary'

  const isOverdue = task =>
    task.status !== 'completed'
    && task.due_at
    && new Date(task.due_at) < new Date()

  const formatTimeSpent = task => {
    if (!task?.time_spent_minutes)
      return null

    const minutes = Number(task.time_spent_minutes)
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60

    if (hours > 0)
      return `${hours.toLocaleString('fa-IR')} ساعت و ${mins.toLocaleString('fa-IR')} دقیقه`

    return `${mins.toLocaleString('fa-IR')} دقیقه`
  }

  const formatWorkRange = task => {
    if (!task?.work_started_at || !task?.work_ended_at)
      return null

    const { formatDateTime } = useJalaliDate()
    const start = formatDateTime(task.work_started_at)
    const end = formatDateTime(task.work_ended_at)

    return `${start} تا ${end}`
  }

  return {
    isOwner,
    isManager,
    canViewTeam,
    canAssignTasks,
    currentUserId,
    tasks,
    total,
    loading,
    users,
    filter,
    statusFilter,
    overdueOnly,
    page,
    perPage,
    fetchUsers,
    fetchTasks,
    saveTask,
    deleteTask,
    fetchTask,
    statusLabel,
    priorityLabel,
    statusColor,
    priorityColor,
    isOverdue,
    formatTimeSpent,
    formatWorkRange,
    STATUS_LABELS,
    PRIORITY_LABELS,
  }
}
