<script setup>
const props = defineProps({
  tasks: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  perPage: { type: Number, default: 15 },
  canViewTeam: { type: Boolean, default: false },
  canAssignTasks: { type: Boolean, default: false },
  filter: { type: String, default: 'all' },
  statusFilter: { type: [String, null], default: null },
  overdueOnly: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:page',
  'update:perPage',
  'update:filter',
  'update:statusFilter',
  'update:overdueOnly',
  'edit',
  'create',
  'complete',
])

const { formatDateTime } = useJalaliDate()
const {
  statusLabel,
  priorityLabel,
  statusColor,
  priorityColor,
  isOverdue,
  formatTimeSpent,
  formatWorkRange,
  currentUserId,
} = useCrmTasks()

const canComplete = task =>
  task.status !== 'completed'
  && Number(task.assignee?.id ?? task.assignee_id) === Number(currentUserId.value)

const headers = computed(() => {
  const base = [
    { title: 'عنوان', key: 'title' },
    { title: 'مسئول', key: 'assignee' },
    { title: 'ایجادکننده', key: 'creator' },
    { title: 'وضعیت', key: 'status' },
    { title: 'اولویت', key: 'priority' },
    { title: 'موعد', key: 'due_at' },
    { title: 'زمان کار', key: 'work_time', sortable: false },
    { title: 'عملیات', key: 'actions', sortable: false },
  ]

  return base
})

const showFilters = computed(() => props.canViewTeam || props.canAssignTasks)

const filterItems = computed(() => {
  const items = [{ title: 'تسک‌های من', value: 'mine' }]

  if (props.canViewTeam)
    items.unshift({ title: 'تیم واحد من', value: 'all' })

  if (props.canAssignTasks || props.canViewTeam)
    items.push({ title: 'واگذارشده توسط من', value: 'assigned_by_me' })

  return items
})

const statusItems = [
  { title: 'همه وضعیت‌ها', value: null },
  { title: 'در انتظار', value: 'pending' },
  { title: 'در حال انجام', value: 'in_progress' },
  { title: 'انجام‌شده', value: 'completed' },
]
</script>

<template>
  <div>
    <VCardText class="d-flex flex-wrap align-center gap-3 pb-2">
      <VChipGroup
        v-if="showFilters"
        :model-value="filter"
        mandatory
        @update:model-value="emit('update:filter', $event)"
      >
        <VChip
          v-for="item in filterItems"
          :key="item.value"
          :value="item.value"
          filter
          variant="tonal"
        >
          {{ item.title }}
        </VChip>
      </VChipGroup>

      <VSelect
        :model-value="statusFilter"
        :items="statusItems"
        item-title="title"
        item-value="value"
        label="وضعیت"
        density="compact"
        style="max-inline-size: 180px;"
        hide-details
        @update:model-value="emit('update:statusFilter', $event)"
      />

      <VCheckbox
        :model-value="overdueOnly"
        label="فقط سررسید گذشته"
        density="compact"
        hide-details
        @update:model-value="emit('update:overdueOnly', $event)"
      />

      <VSpacer />

      <VBtn
        prepend-icon="tabler-plus"
        @click="emit('create')"
      >
        تسک جدید
      </VBtn>
    </VCardText>

    <div class="task-list-table-wrap">
    <VDataTableServer
      :headers="headers"
      :items="tasks"
      item-value="id"
      :items-length="total"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      @update:page="emit('update:page', $event)"
      @update:items-per-page="emit('update:perPage', $event)"
    >
      <template #item.title="{ item }">
        <div
          class="font-weight-medium cursor-pointer"
          @click="emit('edit', item)"
        >
          {{ item.title }}
        </div>
        <div
          v-if="item.description"
          class="text-caption text-medium-emphasis text-truncate"
          style="max-inline-size: 280px;"
        >
          {{ item.description }}
        </div>
        <div
          v-if="item.status === 'completed' && item.completion_note"
          class="text-caption text-success mt-1 text-truncate"
          style="max-inline-size: 280px;"
        >
          <VIcon
            icon="tabler-message-report"
            size="14"
            class="me-1"
          />
          {{ item.completion_note }}
        </div>
      </template>

      <template #item.assignee="{ item }">
        {{ item.assignee?.name ?? '—' }}
      </template>

      <template #item.creator="{ item }">
        {{ item.creator?.name ?? '—' }}
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

      <template #item.priority="{ item }">
        <VChip
          size="small"
          :color="priorityColor(item.priority)"
          variant="tonal"
        >
          {{ priorityLabel(item.priority) }}
        </VChip>
      </template>

      <template #item.due_at="{ item }">
        <span :class="isOverdue(item) ? 'text-error font-weight-medium' : ''">
          {{ item.due_at ? formatDateTime(item.due_at) : '—' }}
        </span>
      </template>

      <template #item.work_time="{ item }">
        <div v-if="item.status === 'completed' && (formatTimeSpent(item) || formatWorkRange(item))">
          <div
            v-if="formatTimeSpent(item)"
            class="text-body-2 font-weight-medium"
          >
            {{ formatTimeSpent(item) }}
          </div>
          <div
            v-if="formatWorkRange(item)"
            class="text-caption text-medium-emphasis"
          >
            {{ formatWorkRange(item) }}
          </div>
        </div>
        <span
          v-else
          class="text-medium-emphasis"
        >—</span>
      </template>

      <template #item.actions="{ item }">
        <IconBtn
          v-if="canComplete(item)"
          color="success"
          @click="emit('complete', item)"
        >
          <VIcon icon="tabler-circle-check" />
          <VTooltip
            activator="parent"
            location="top"
          >
            تکمیل و ثبت زمان
          </VTooltip>
        </IconBtn>
        <IconBtn @click="emit('edit', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTableServer>
    </div>
  </div>
</template>

<style scoped>
.task-list-table-wrap {
  max-inline-size: 100%;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
}

.task-list-table-wrap :deep(.v-table) {
  min-inline-size: 720px;
}
</style>
