<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lead: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'success'])

const form = ref({
  pipeline_stage_id: null,
  deal_title: '',
  amount: null,
})

const success = ref(false)
const profileUrl = ref(null)
const firstSalesStage = ref(null)
const submitting = ref(false)

const loadSalesStages = async () => {
  try {
    const res = await $api('/pipeline-stages?type=sales')
    const stages = res.stages ?? []
    firstSalesStage.value = stages[0] ?? null
    form.value.pipeline_stage_id = firstSalesStage.value?.id ?? null
  } catch {
    firstSalesStage.value = null
  }
}

const reset = () => {
  success.value = false
  profileUrl.value = null
  form.value = {
    pipeline_stage_id: firstSalesStage.value?.id ?? null,
    deal_title: props.lead ? `معامله ${props.lead.name}` : '',
    amount: null,
  }
}

const submit = async () => {
  if (!props.lead?.id)
    return

  submitting.value = true
  try {
    const res = await $api(`/leads/${props.lead.id}/convert`, {
      method: 'POST',
      body: {
        pipeline_stage_id: form.value.pipeline_stage_id,
        deal_title: form.value.deal_title,
        amount: form.value.amount ?? undefined,
      },
    })
    success.value = true
    if (res.contact?.id) {
      profileUrl.value = { name: 'apps-crm-contacts-id', params: { id: res.contact.id } }
    }
    emit('success', res)
  } finally {
    submitting.value = false
  }
}

const close = () => {
  emit('update:modelValue', false)
}

watch(() => props.modelValue, async open => {
  if (open) {
    await loadSalesStages()
    reset()
  }
})

watch(() => props.lead, () => {
  if (props.modelValue)
    reset()
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="success ? 'ارجاع موفق' : 'ارجاع به قیف فروش'">
      <VCardText v-if="!success">
        <p class="text-body-2 text-medium-emphasis mb-4">
          مخاطب و معامله در قیف فروش ایجاد می‌شود.
        </p>
        <VAlert
          v-if="firstSalesStage"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          مرحله فروش: {{ firstSalesStage.name }}
        </VAlert>
        <AppTextField
          v-model="form.deal_title"
          label="عنوان معامله"
          class="mb-4"
        />
        <AppTextField
          v-model.number="form.amount"
          label="مبلغ (اختیاری)"
          type="number"
        />
      </VCardText>
      <VCardText v-else>
        <VAlert
          type="success"
          variant="tonal"
          class="mb-4"
        >
          لید «{{ lead?.name }}» به قیف فروش منتقل شد.
        </VAlert>
        <div class="d-flex flex-wrap gap-3">
          <VBtn
            v-if="profileUrl"
            color="primary"
            :to="profileUrl"
            prepend-icon="tabler-user-circle"
          >
            پروفایل مشتری
          </VBtn>
          <VBtn
            variant="tonal"
            :to="{ name: 'apps-crm-deals' }"
            prepend-icon="tabler-chart-funnel"
          >
            مشاهده قیف فروش
          </VBtn>
        </div>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="close">
          {{ success ? 'بستن' : 'انصراف' }}
        </VBtn>
        <VBtn
          v-if="!success"
          color="primary"
          :loading="submitting"
          @click="submit"
        >
          ارجاع به فروش
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
