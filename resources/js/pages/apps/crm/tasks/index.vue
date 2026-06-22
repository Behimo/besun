<script setup>
definePage({ meta: { action: 'read', subject: 'Tasks' } })

import TaskCalendarView from '@/views/crm/tasks/TaskCalendarView.vue'
import TaskCompleteDialog from '@/views/crm/tasks/TaskCompleteDialog.vue'
import TaskFormDrawer from '@/views/crm/tasks/TaskFormDrawer.vue'
import TaskListView from '@/views/crm/tasks/TaskListView.vue'

const activeTab = ref('list')
const drawerOpen = ref(false)
const saving = ref(false)
const editingTask = ref(null)
const completingTask = ref(null)
const completeDialog = ref(false)
const calendarRef = ref()
const feedback = ref('')
const feedbackType = ref('success')
const snackbar = ref(false)

const showFeedback = (message, type = 'success') => {
  feedback.value = message
  feedbackType.value = type
  snackbar.value = true
}

const translateValidation = message => {
  if (!message || typeof message !== 'string')
    return message

  if (message.startsWith('validation.date'))
    return 'فرمت تاریخ یا ساعت نامعتبر است.'

  return message
}

const extractError = error => {
  const fieldErrors = error?.data?.errors
    ? Object.values(error.data.errors).flat()
    : []

  for (const item of fieldErrors) {
    const translated = translateValidation(item)

    if (translated)
      return translated
  }

  return translateValidation(error?.data?.message) ?? 'خطا در ذخیره تسک'
}

const {
  canViewTeam,
  canAssignTasks,
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
} = useCrmTasks()

onMounted(async () => {
  await fetchUsers()

  if (!canViewTeam.value)
    filter.value = 'mine'

  await fetchTasks()
})

watch([filter, statusFilter, overdueOnly, page, perPage], () => {
  fetchTasks()
})

watch([filter, statusFilter, overdueOnly], () => {
  page.value = 1
})

const openCreate = () => {
  editingTask.value = null
  drawerOpen.value = true
}

const openEdit = async task => {
  editingTask.value = task
  drawerOpen.value = true
}

const openEditById = async id => {
  try {
    editingTask.value = await fetchTask(id)
    drawerOpen.value = true
  } catch (e) {
    console.error(e)
  }
}

const handleSave = async payload => {
  saving.value = true
  try {
    await saveTask(payload, editingTask.value?.id)
    drawerOpen.value = false
    editingTask.value = null
    await fetchTasks()
    calendarRef.value?.refresh()
    showFeedback(payload.status === 'completed' ? 'تسک با موفقیت تکمیل شد.' : 'تسک ذخیره شد.')
  } catch (error) {
    showFeedback(extractError(error), 'error')
  } finally {
    saving.value = false
  }
}

const openComplete = task => {
  completingTask.value = task
  completeDialog.value = true
}

const handleComplete = async payload => {
  const taskId = completingTask.value?.id

  if (!taskId) {
    showFeedback('تسک انتخاب‌شده معتبر نیست.', 'error')

    return
  }

  saving.value = true
  try {
    await saveTask(payload, taskId)
    completeDialog.value = false
    completingTask.value = null

    if (statusFilter.value && statusFilter.value !== 'completed')
      statusFilter.value = null

    await fetchTasks()
    calendarRef.value?.refresh()
    showFeedback('تسک با موفقیت تکمیل شد.')
  } catch (error) {
    showFeedback(extractError(error), 'error')
  } finally {
    saving.value = false
  }
}

const handleDelete = async () => {
  if (!editingTask.value?.id)
    return

  saving.value = true
  try {
    await deleteTask(editingTask.value.id)
    drawerOpen.value = false
    editingTask.value = null
    await fetchTasks()
    calendarRef.value?.refresh()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <VCard>
    <VCardText class="tasks-card-header">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="flex-grow-1">
          <h5 class="text-h5 mb-1">
            تسک‌ها
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-0">
            مدیریت کارها، واگذاری تیم و نمای تقویم
          </p>
        </div>

        <VTabs
          v-model="activeTab"
          density="compact"
          class="tasks-tabs"
        >
        <VTab value="list">
          <VIcon
            icon="tabler-list"
            start
          />
          لیست
        </VTab>
        <VTab value="calendar">
          <VIcon
            icon="tabler-calendar"
            start
          />
          تقویم
        </VTab>
      </VTabs>
      </div>
    </VCardText>

    <VDivider />

    <VWindow
      v-model="activeTab"
      :touch="false"
    >
      <VWindowItem value="list">
        <TaskListView
          :tasks="tasks"
          :loading="loading"
          :total="total"
          :page="page"
          :per-page="perPage"
          :can-view-team="canViewTeam"
          :can-assign-tasks="canAssignTasks"
          :filter="filter"
          :status-filter="statusFilter"
          :overdue-only="overdueOnly"
          @update:page="page = $event"
          @update:per-page="perPage = $event"
          @update:filter="filter = $event"
          @update:status-filter="statusFilter = $event"
          @update:overdue-only="overdueOnly = $event"
          @edit="openEdit"
          @create="openCreate"
          @complete="openComplete"
        />
      </VWindowItem>

      <VWindowItem value="calendar">
        <TaskCalendarView
          ref="calendarRef"
          @select-task="openEditById"
          @create-task="openCreate"
        />
      </VWindowItem>
    </VWindow>
  </VCard>

  <TaskFormDrawer
    v-model="drawerOpen"
    :task="editingTask"
    :users="users"
    :is-manager="canAssignTasks"
    :saving="saving"
    @save="handleSave"
    @delete="handleDelete"
  />

  <TaskCompleteDialog
    v-model="completeDialog"
    :task="completingTask"
    :saving="saving"
    @save="handleComplete"
  />

  <VSnackbar
    v-model="snackbar"
    :color="feedbackType === 'error' ? 'error' : 'success'"
    :timeout="4000"
    location="top"
  >
    {{ feedback }}
  </VSnackbar>
</template>

<style scoped>
.tasks-card-header {
  padding-block-end: 0.75rem !important;
}

.tasks-tabs {
  inline-size: 100%;
}

@media (min-width: 600px) {
  .tasks-tabs {
    inline-size: auto;
  }
}
</style>
