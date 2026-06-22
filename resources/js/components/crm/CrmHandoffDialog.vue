<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  entityType: { type: String, default: 'deal' },
  entity: { type: Object, default: null },
  stages: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  preset: { type: String, default: 'assign' },
})

const emit = defineEmits(['update:modelValue', 'success'])

const saving = ref(false)

const form = ref({
  handoff_type: 'assign',
  to_user_id: null,
  to_stage_id: null,
  note: '',
  create_task: true,
  task_title: '',
  task_due_date: '',
  task_due_time: '09:00',
})

const { mergeDatetime } = useFollowUpDatetime()

const financeStage = computed(() => props.stages.find(s => s.name === 'مالی'))

const presetItems = computed(() => {
  const items = [
    { title: 'واگذاری به همکار', value: 'assign', icon: 'tabler-user-share' },
  ]

  if (props.entityType === 'deal') {
    items.push({ title: 'ارسال به مالی', value: 'finance', icon: 'tabler-building-bank' })
  }

  return items
})

const stageItems = computed(() => props.stages.map(s => ({
  title: s.name,
  value: s.id,
})))

const userItems = computed(() => props.users.map(u => ({
  title: u.name,
  value: u.id,
})))

const entityLabel = computed(() => {
  if (!props.entity)
    return '—'

  return props.entity.title ?? props.entity.name ?? '—'
})

const currentStageId = computed(() =>
  props.entityType === 'deal'
    ? props.entity?.pipeline_stage_id ?? props.entity?.stage?.id
    : props.entity?.marketing_stage_id ?? props.entity?.marketing_stage?.id,
)

const resetForm = () => {
  const type = props.preset || 'assign'

  form.value = {
    handoff_type: type,
    to_user_id: null,
    to_stage_id: type === 'finance' ? (financeStage.value?.id ?? null) : (currentStageId.value ?? null),
    note: '',
    create_task: true,
    task_title: '',
    task_due_date: '',
    task_due_time: '09:00',
  }
}

watch(() => props.modelValue, open => {
  if (open)
    resetForm()
})

watch(() => form.value.handoff_type, type => {
  if (type === 'finance' && financeStage.value)
    form.value.to_stage_id = financeStage.value.id
})

const submit = async () => {
  if (!props.entity?.id || !form.value.to_user_id)
    return

  saving.value = true
  try {
    const body = {
      entity_type: props.entityType,
      entity_id: props.entity.id,
      to_user_id: form.value.to_user_id,
      to_stage_id: form.value.to_stage_id,
      handoff_type: form.value.handoff_type,
      note: form.value.note || undefined,
      create_task: form.value.create_task,
      task_title: form.value.task_title || undefined,
      task_due_at: form.value.create_task && form.value.task_due_date
        ? mergeDatetime(form.value.task_due_date, form.value.task_due_time)
        : undefined,
    }

    await $api('/handoffs', { method: 'POST', body })
    emit('success')
    emit('update:modelValue', false)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard title="واگذاری پرونده">
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ entityLabel }}
        </VAlert>

        <AppSelect
          v-model="form.handoff_type"
          :items="presetItems"
          label="نوع واگذاری"
          class="mb-4"
        />

        <AppSelect
          v-model="form.to_user_id"
          :items="userItems"
          label="مسئول پیگیری *"
          class="mb-4"
        />

        <AppSelect
          v-model="form.to_stage_id"
          :items="stageItems"
          label="مرحله مقصد"
          class="mb-4"
        />

        <AppTextarea
          v-model="form.note"
          label="توضیحات / دستورالعمل"
          rows="3"
          class="mb-4"
        />

        <VCheckbox
          v-model="form.create_task"
          label="ایجاد تسک پیگیری برای مسئول"
          hide-details
          class="mb-2"
        />

        <template v-if="form.create_task">
          <AppTextField
            v-model="form.task_title"
            label="عنوان تسک (اختیاری)"
            class="mb-4 mt-2"
          />
          <AppJalaliDateTimePicker
            v-model="form.task_due_date"
            v-model:time="form.task_due_time"
            label="موعد تسک"
          />
        </template>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="emit('update:modelValue', false)">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="!form.to_user_id"
          @click="submit"
        >
          واگذاری
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
