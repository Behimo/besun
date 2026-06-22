<script setup>
import { useCrmAutomation } from '@/composables/useCrmAutomation'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  rule: { type: Object, default: null },
  meta: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { saveRule } = useCrmAutomation()

const saving = ref(false)
const formError = ref('')

const emptyForm = () => ({
  name: '',
  description: '',
  trigger_event: '',
  is_active: true,
  priority: 100,
  conditions: [],
  actions: [],
})

const form = ref(emptyForm())

const triggerOptions = computed(() => {
  const triggers = props.meta?.triggers ?? {}

  return Object.entries(triggers).map(([value, item]) => ({
    value,
    title: item.label,
    entity: item.entity,
  }))
})

const selectedTrigger = computed(() =>
  triggerOptions.value.find(t => t.value === form.value.trigger_event),
)

const conditionFields = computed(() => {
  const entity = selectedTrigger.value?.entity
  const fields = props.meta?.condition_fields?.[entity] ?? {}

  return Object.entries(fields).map(([value, item]) => ({
    value,
    title: item.label,
    type: item.type,
  }))
})

const operatorOptions = computed(() => {
  const ops = props.meta?.operators ?? {}

  return Object.entries(ops).map(([value, title]) => ({ value, title }))
})

const actionOptions = computed(() => {
  const actions = props.meta?.actions ?? {}

  return Object.entries(actions).map(([value, item]) => ({
    value,
    title: item.label,
    params: item.params ?? {},
    requiresModule: item.requires_module,
  }))
})

const userOptions = computed(() =>
  (props.meta?.users ?? []).map(u => ({ value: u.id, title: u.name })),
)

const stageOptions = computed(() => {
  const entity = selectedTrigger.value?.entity
  const type = entity === 'lead' ? 'marketing' : 'sales'
  const stages = props.meta?.stages?.[type] ?? []

  return stages.map(s => ({ value: s.id, title: s.name }))
})

const hasSmsModule = computed(() => props.meta?.has_sms_module ?? false)

watch(() => props.modelValue, open => {
  if (!open)
    return

  formError.value = ''

  if (props.rule) {
    form.value = {
      name: props.rule.name,
      description: props.rule.description ?? '',
      trigger_event: props.rule.trigger_event,
      is_active: props.rule.is_active,
      priority: props.rule.priority ?? 100,
      conditions: JSON.parse(JSON.stringify(props.rule.conditions ?? [])),
      actions: JSON.parse(JSON.stringify(props.rule.actions ?? [])),
    }
  } else {
    form.value = emptyForm()
  }
})

const addCondition = () => {
  form.value.conditions.push({ field: '', operator: 'equals', value: '' })
}

const removeCondition = index => {
  form.value.conditions.splice(index, 1)
}

const addAction = () => {
  form.value.actions.push({ type: '', params: {} })
}

const removeAction = index => {
  form.value.actions.splice(index, 1)
}

const getActionDef = type => actionOptions.value.find(a => a.value === type)

const submit = async () => {
  formError.value = ''

  if (!form.value.name || !form.value.trigger_event || form.value.actions.length === 0) {
    formError.value = 'نام، رویداد و حداقل یک اقدام الزامی است.'

    return
  }

  saving.value = true
  try {
    await saveRule(form.value, props.rule?.id ?? null)
    emit('saved')
    emit('update:modelValue', false)
  } catch (e) {
    formError.value = e?.data?.message || 'خطا در ذخیره قانون'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="720"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ rule ? 'ویرایش قانون' : 'قانون جدید' }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>

      <VCardText>
        <VAlert
          v-if="!meta"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          در حال بارگذاری اطلاعات فرم...
        </VAlert>

        <VAlert
          v-if="formError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ formError }}
        </VAlert>

        <template v-if="meta">
        <VRow>
          <VCol cols="12" md="8">
            <VTextField
              v-model="form.name"
              label="نام قانون"
              required
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="form.priority"
              label="اولویت"
              type="number"
              hint="عدد کمتر = اولویت بالاتر"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="توضیح"
              rows="2"
            />
          </VCol>
          <VCol cols="12">
            <VSelect
              v-model="form.trigger_event"
              :items="triggerOptions"
              item-title="title"
              item-value="value"
              label="رویداد (تریگر)"
            />
          </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              label="فعال"
              color="primary"
            />
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <div class="d-flex align-center justify-space-between mb-3">
          <h6 class="text-h6">
            شرط‌ها
          </h6>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addCondition"
          >
            افزودن شرط
          </VBtn>
        </div>

        <p
          v-if="form.conditions.length === 0"
          class="text-body-2 text-medium-emphasis mb-4"
        >
          بدون شرط — قانون برای همه رویدادها اجرا می‌شود.
        </p>

        <VCard
          v-for="(cond, idx) in form.conditions"
          :key="idx"
          variant="outlined"
          class="mb-3"
        >
          <VCardText>
            <VRow>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="cond.field"
                  :items="conditionFields"
                  item-title="title"
                  item-value="value"
                  label="فیلد"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="cond.operator"
                  :items="operatorOptions"
                  item-title="title"
                  item-value="value"
                  label="عملگر"
                />
              </VCol>
              <VCol
                v-if="cond.field !== 'assignee_is_empty'"
                cols="12"
                md="3"
              >
                <VSelect
                  v-if="conditionFields.find(f => f.value === cond.field)?.type === 'stage'"
                  v-model="cond.value"
                  :items="stageOptions"
                  item-title="title"
                  item-value="value"
                  label="مقدار"
                />
                <VSelect
                  v-else-if="conditionFields.find(f => f.value === cond.field)?.type === 'user'"
                  v-model="cond.value"
                  :items="userOptions"
                  item-title="title"
                  item-value="value"
                  label="مقدار"
                />
                <VTextField
                  v-else
                  v-model="cond.value"
                  label="مقدار"
                />
              </VCol>
              <VCol cols="12" md="1" class="d-flex align-center">
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  size="small"
                  @click="removeCondition(idx)"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VDivider class="my-4" />

        <div class="d-flex align-center justify-space-between mb-3">
          <h6 class="text-h6">
            اقدام‌ها
          </h6>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addAction"
          >
            افزودن اقدام
          </VBtn>
        </div>

        <VCard
          v-for="(action, idx) in form.actions"
          :key="idx"
          variant="outlined"
          class="mb-3"
        >
          <VCardText>
            <VRow>
              <VCol cols="12" md="5">
                <VSelect
                  v-model="action.type"
                  :items="actionOptions"
                  item-title="title"
                  item-value="value"
                  label="نوع اقدام"
                />
              </VCol>
              <VCol cols="12" md="6">
                <template v-if="action.type === 'assign_user'">
                  <VSelect
                    v-model="action.params.user_id"
                    :items="userOptions"
                    item-title="title"
                    item-value="value"
                    label="کاربر"
                  />
                </template>
                <template v-else-if="action.type === 'assign_round_robin'">
                  <VSelect
                    v-model="action.params.user_ids"
                    :items="userOptions"
                    item-title="title"
                    item-value="value"
                    label="کاربران (چرخشی)"
                    multiple
                    chips
                  />
                </template>
                <template v-else-if="action.type === 'set_follow_up_reminder'">
                  <VTextField
                    v-model.number="action.params.offset_days"
                    label="بعد از (روز)"
                    type="number"
                  />
                  <VTextField
                    v-model.number="action.params.offset_hours"
                    label="بعد از (ساعت)"
                    type="number"
                    class="mt-2"
                  />
                </template>
                <template v-else-if="action.type === 'create_task'">
                  <VTextField
                    v-model="action.params.title"
                    label="عنوان تسک"
                  />
                  <VTextarea
                    v-model="action.params.description"
                    label="توضیح"
                    rows="2"
                    class="mt-2"
                  />
                  <VSelect
                    v-model="action.params.assignee_id"
                    :items="userOptions"
                    item-title="title"
                    item-value="value"
                    label="مسئول (اختیاری)"
                    clearable
                    class="mt-2"
                  />
                  <VTextField
                    v-model.number="action.params.due_offset_days"
                    label="مهلت (روز)"
                    type="number"
                    class="mt-2"
                  />
                </template>
                <template v-else-if="action.type === 'send_notification'">
                  <VTextField
                    v-model="action.params.title"
                    label="عنوان اعلان"
                  />
                  <VTextField
                    v-model="action.params.subtitle"
                    label="زیرعنوان"
                    class="mt-2"
                  />
                  <VSelect
                    v-model="action.params.notify"
                    :items="[
                      { value: 'assignee', title: 'مسئول' },
                      { value: 'actor', title: 'کاربر انجام‌دهنده' },
                    ]"
                    item-title="title"
                    item-value="value"
                    label="گیرنده"
                    class="mt-2"
                  />
                </template>
                <template v-else-if="action.type === 'send_sms'">
                  <VAlert
                    v-if="!hasSmsModule"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mb-2"
                  >
                    ماژول پیامک فعال نیست.
                  </VAlert>
                  <VTextarea
                    v-model="action.params.message"
                    label="متن پیامک"
                    rows="3"
                    hint="{{name}}، {{phone}}، {{company}}، {{stage}}"
                  />
                </template>
              </VCol>
              <VCol cols="12" md="1" class="d-flex align-center">
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  size="small"
                  @click="removeAction(idx)"
                />
              </VCol>
            </VRow>
            <VAlert
              v-if="getActionDef(action.type)?.requiresModule === 'mod-sms' && !hasSmsModule"
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-2"
            >
              این اقدام نیاز به ماژول پیامک دارد.
            </VAlert>
          </VCardText>
        </VCard>
        </template>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="emit('update:modelValue', false)"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="!meta"
          @click="submit"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
