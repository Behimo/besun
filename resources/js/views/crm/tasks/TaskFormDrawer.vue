<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  task: { type: Object, default: null },
  users: { type: Array, default: () => [] },
  isManager: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'delete'])

const { moment, toApiDateTime, toApiDate } = useJalaliDate()

const reminderModeItems = [
  { title: 'هم‌زمان موعد', value: 'at_due' },
  { title: '۱۵ دقیقه قبل', value: '15m' },
  { title: '۱ ساعت قبل', value: '1h' },
  { title: '۱ روز قبل', value: '1d' },
  { title: 'زمان سفارشی', value: 'custom' },
]

const emptyForm = () => ({
  title: '',
  description: '',
  due_at: '',
  due_time: '09:00',
  reminder_enabled: false,
  reminder_mode: '1h',
  reminder_at: '',
  reminder_time: '09:00',
  status: 'pending',
  priority: 'medium',
  assignee_id: null,
  work_date: '',
  work_start_time: '09:00',
  work_end_time: '10:00',
  completion_note: '',
  effort_points: 3,
})

const form = ref(emptyForm())

const statusItems = [
  { title: 'در انتظار', value: 'pending' },
  { title: 'در حال انجام', value: 'in_progress' },
  { title: 'انجام‌شده', value: 'completed' },
]

const priorityItems = [
  { title: 'کم', value: 'low' },
  { title: 'متوسط', value: 'medium' },
  { title: 'بالا', value: 'high' },
]

const isEdit = computed(() => Boolean(props.task?.id))

const computeReminderIso = (dueIso, mode) => {
  if (!dueIso)
    return null

  const m = moment(dueIso)

  if (mode === 'at_due')
    return m.format('YYYY-MM-DDTHH:mm:ss')

  if (mode === '15m')
    return m.clone().subtract(15, 'minutes').format('YYYY-MM-DDTHH:mm:ss')

  if (mode === '1h')
    return m.clone().subtract(1, 'hours').format('YYYY-MM-DDTHH:mm:ss')

  if (mode === '1d')
    return m.clone().subtract(1, 'days').format('YYYY-MM-DDTHH:mm:ss')

  return null
}

watch(() => form.value.status, status => {
  if (status !== 'completed' || form.value.work_date)
    return

  const now = moment()
  const start = now.clone().subtract(1, 'hour')

  form.value.work_date = now.locale('en').format('YYYY-MM-DD')
  form.value.work_start_time = start.locale('en').format('HH:mm')
  form.value.work_end_time = now.locale('en').format('HH:mm')
})

watch(() => props.modelValue, open => {
  if (!open)
    return

  if (props.task?.id) {
    const due = props.task.due_at ? moment(props.task.due_at) : null
    const reminder = props.task.reminder_at ? moment(props.task.reminder_at) : null
    const workStart = props.task.work_started_at ? moment(props.task.work_started_at) : null
    const workEnd = props.task.work_ended_at ? moment(props.task.work_ended_at) : null

    form.value = {
      title: props.task.title ?? '',
      description: props.task.description ?? '',
      due_at: due?.isValid() ? toApiDate(due) : '',
      due_time: due?.isValid() ? due.format('HH:mm') : '09:00',
      reminder_enabled: Boolean(reminder?.isValid()),
      reminder_mode: 'custom',
      reminder_at: reminder?.isValid() ? toApiDate(reminder) : '',
      reminder_time: reminder?.isValid() ? reminder.format('HH:mm') : '09:00',
      status: props.task.status ?? 'pending',
      priority: props.task.priority ?? 'medium',
      assignee_id: props.task.assignee_id ?? props.task.assignee?.id ?? null,
      work_date: workStart?.isValid() ? toApiDate(workStart) : '',
      work_start_time: workStart?.isValid() ? workStart.format('HH:mm') : '09:00',
      work_end_time: workEnd?.isValid() ? workEnd.format('HH:mm') : '10:00',
      completion_note: props.task.completion_note ?? '',
      effort_points: props.task.effort_points ?? 3,
    }
  } else {
    form.value = emptyForm()
  }
})

const workDurationLabel = computed(() => {
  if (form.value.status !== 'completed' || !form.value.work_date)
    return null

  const start = moment(`${form.value.work_date}T${form.value.work_start_time}`)
  const end = moment(`${form.value.work_date}T${form.value.work_end_time}`)

  if (!start.isValid() || !end.isValid() || !end.isAfter(start))
    return null

  const minutes = end.diff(start, 'minutes')
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60

  if (hours > 0)
    return `${hours.toLocaleString('fa-IR')} ساعت و ${mins.toLocaleString('fa-IR')} دقیقه`

  return `${mins.toLocaleString('fa-IR')} دقیقه`
})

const buildPayload = () => {
  const payload = {
    title: form.value.title,
    description: form.value.description || null,
    status: form.value.status,
    priority: form.value.priority,
  }

  payload.due_at = form.value.due_at
    ? toApiDateTime(form.value.due_at, form.value.due_time || '09:00')
    : null

  if (!form.value.reminder_enabled) {
    payload.reminder_at = null
  } else if (form.value.reminder_mode === 'custom' && form.value.reminder_at) {
    payload.reminder_at = toApiDateTime(form.value.reminder_at, form.value.reminder_time || '09:00')
  } else if (payload.due_at) {
    payload.reminder_at = computeReminderIso(payload.due_at, form.value.reminder_mode)
  } else {
    payload.reminder_at = null
  }

  if (props.isManager)
    payload.assignee_id = form.value.assignee_id

  if (form.value.status === 'completed' && form.value.work_date) {
    payload.work_started_at = toApiDateTime(form.value.work_date, form.value.work_start_time)
    payload.work_ended_at = toApiDateTime(form.value.work_date, form.value.work_end_time)
    payload.completion_note = form.value.completion_note?.trim() || null
  }

  if (props.isManager)
    payload.effort_points = form.value.effort_points

  return payload
}

const submit = () => {
  emit('save', buildPayload())
}

const close = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <VNavigationDrawer
    :model-value="modelValue"
    location="end"
    temporary
    width="420"
    class="task-form-drawer"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="task-form-drawer__header pa-5 d-flex align-center justify-space-between">
      <h5 class="text-h5">
        {{ isEdit ? 'ویرایش تسک' : 'تسک جدید' }}
      </h5>
      <IconBtn @click="close">
        <VIcon icon="tabler-x" />
      </IconBtn>
    </div>

    <VDivider />

    <div class="task-form-drawer__body pa-5">
      <AppTextField
        v-model="form.title"
        label="عنوان *"
        class="mb-4"
      />

      <AppTextarea
        v-model="form.description"
        label="توضیحات"
        rows="3"
        class="mb-4"
      />

      <AppJalaliDateTimePicker
        v-model="form.due_at"
        v-model:time="form.due_time"
        label="موعد"
        class="mb-4"
      />

      <VSwitch
        v-model="form.reminder_enabled"
        label="یادآوری"
        color="primary"
        class="mb-2"
        hide-details
      />

      <template v-if="form.reminder_enabled">
        <VSelect
          v-model="form.reminder_mode"
          :items="reminderModeItems"
          item-title="title"
          item-value="value"
          label="زمان یادآوری"
          class="mb-4"
          hide-details
        />

        <AppJalaliDateTimePicker
          v-if="form.reminder_mode === 'custom'"
          v-model="form.reminder_at"
          v-model:time="form.reminder_time"
          label="زمان یادآوری سفارشی"
          class="mb-4"
        />
      </template>

      <VSelect
        v-model="form.status"
        :items="statusItems"
        item-title="title"
        item-value="value"
        label="وضعیت"
        class="mb-4"
      />

      <template v-if="form.status === 'completed'">
        <p class="text-body-2 font-weight-medium mb-2">
          زمان انجام کار
        </p>
        <AppJalaliDateTimePicker
          v-model="form.work_date"
          v-model:time="form.work_start_time"
          label="شروع کار"
          class="mb-4"
        />
        <AppJalaliDateTimePicker
          v-model="form.work_date"
          v-model:time="form.work_end_time"
          label="پایان کار"
          class="mb-2"
        />
        <VAlert
          v-if="workDurationLabel"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          مدت زمان: {{ workDurationLabel }}
        </VAlert>
        <VAlert
          v-else
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          ساعت پایان باید بعد از ساعت شروع باشد.
        </VAlert>

        <AppTextarea
          v-model="form.completion_note"
          label="توضیحات انجام کار"
          rows="3"
          class="mb-4"
        />
      </template>

      <VAlert
        v-if="isEdit && task?.completion_note && form.status === 'completed'"
        type="success"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <div class="text-caption font-weight-medium mb-1">
          توضیحات تکمیل (برای مدیر)
        </div>
        {{ task.completion_note }}
      </VAlert>

      <VSelect
        v-model="form.priority"
        :items="priorityItems"
        item-title="title"
        item-value="value"
        label="اولویت"
        class="mb-4"
      />

      <VSelect
        v-if="isManager"
        v-model="form.effort_points"
        :items="[
          { title: '۱ — کم', value: 1 },
          { title: '۲', value: 2 },
          { title: '۳ — متوسط', value: 3 },
          { title: '۴', value: 4 },
          { title: '۵ — زیاد', value: 5 },
        ]"
        item-title="title"
        item-value="value"
        label="امتیاز سختی تسک (۱–۵)"
        class="mb-4"
        hint="در محاسبه عملکرد HR وزن بیشتری دارد"
        persistent-hint
      />

      <VSelect
        v-if="isManager"
        v-model="form.assignee_id"
        :items="users"
        item-title="name"
        item-value="id"
        label="مسئول انجام"
        clearable
        placeholder="انتخاب کاربر"
        class="mb-4"
      />

      <div
        v-else
        class="text-body-2 text-medium-emphasis mb-4"
      >
        این تسک به نام شما ثبت می‌شود.
      </div>

      <div class="d-flex gap-3 flex-wrap">
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="!form.title.trim() || (form.status === 'completed' && !workDurationLabel)"
          @click="submit"
        >
          {{ isEdit ? 'ذخیره' : 'ایجاد' }}
        </VBtn>
        <VBtn
          variant="tonal"
          @click="close"
        >
          انصراف
        </VBtn>
        <VSpacer />
        <VBtn
          v-if="isEdit"
          color="error"
          variant="tonal"
          @click="emit('delete')"
        >
          حذف
        </VBtn>
      </div>
    </div>
  </VNavigationDrawer>
</template>

<style scoped>
.task-form-drawer :deep(.v-navigation-drawer__content) {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.task-form-drawer__header {
  flex-shrink: 0;
}

.task-form-drawer__body {
  flex: 1;
  min-block-size: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
</style>
