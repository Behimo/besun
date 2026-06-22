<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  task: { type: Object, default: null },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const { moment, toApiDateTime } = useJalaliDate()

const form = ref({
  work_date: '',
  work_start_time: '09:00',
  work_end_time: '10:00',
  completion_note: '',
})

const durationLabel = computed(() => {
  if (!form.value.work_date || !form.value.work_start_time || !form.value.work_end_time)
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

const resetForm = () => {
  const now = moment()
  const start = now.clone().subtract(1, 'hour')

  form.value = {
    work_date: now.locale('en').format('YYYY-MM-DD'),
    work_start_time: start.locale('en').format('HH:mm'),
    work_end_time: now.locale('en').format('HH:mm'),
    completion_note: '',
  }
}

const submit = () => {
  if (!form.value.work_date)
    return

  emit('save', {
    status: 'completed',
    work_started_at: toApiDateTime(form.value.work_date, form.value.work_start_time),
    work_ended_at: toApiDateTime(form.value.work_date, form.value.work_end_time),
    completion_note: form.value.completion_note?.trim() || null,
  })
}

const close = () => {
  emit('update:modelValue', false)
}

watch(() => props.modelValue, open => {
  if (open)
    resetForm()
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="480"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard title="ثبت زمان انجام تسک">
      <VCardText class="task-complete-dialog__body">
        <p
          v-if="task"
          class="text-body-2 text-medium-emphasis mb-4"
        >
          {{ task.title }}
        </p>
        <VAlert
          v-if="task?.description"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <div class="text-caption font-weight-medium mb-1">
            توضیحات تسک از طرف مدیر
          </div>
          <div class="text-body-2">
            {{ task.description }}
          </div>
        </VAlert>

        <p class="text-body-2 mb-4">
          ساعت شروع و پایان کار را وارد کنید تا مدت زمان صرف‌شده محاسبه شود.
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
          v-if="durationLabel"
          type="info"
          variant="tonal"
          density="compact"
        >
          مدت زمان: {{ durationLabel }}
        </VAlert>
        <VAlert
          v-else-if="form.work_date"
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
          placeholder="کارهای انجام‌شده، نتیجه یا توضیحات برای مدیر..."
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="close">
          انصراف
        </VBtn>
        <VBtn
          color="success"
          :loading="saving"
          :disabled="!durationLabel"
          @click="submit"
        >
          تکمیل تسک
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.task-complete-dialog__body {
  max-block-size: min(70vh, 560px);
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
</style>
