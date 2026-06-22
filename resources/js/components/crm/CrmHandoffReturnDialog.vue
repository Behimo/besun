<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  handoff: { type: Object, default: null },
  stages: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'success'])

const saving = ref(false)
const form = ref({ note: '', to_stage_id: null })

const stageItems = computed(() => props.stages.map(s => ({
  title: s.name,
  value: s.id,
})))

watch(() => props.modelValue, open => {
  if (open && props.handoff) {
    form.value = {
      note: '',
      to_stage_id: props.handoff.from_stage?.id ?? null,
    }
  }
})

const submit = async () => {
  if (!props.handoff?.id)
    return

  saving.value = true
  try {
    await $api(`/handoffs/${props.handoff.id}/return`, {
      method: 'POST',
      body: {
        note: form.value.note || undefined,
        to_stage_id: form.value.to_stage_id || undefined,
      },
    })
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
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard title="بازگرداندن به فروش">
      <VCardText>
        <p class="text-body-2 text-medium-emphasis mb-4">
          پرونده به فرستنده قبلی بازگردانده می‌شود.
        </p>
        <AppSelect
          v-model="form.to_stage_id"
          :items="stageItems"
          label="مرحله بازگشت"
          clearable
          class="mb-4"
        />
        <AppTextarea
          v-model="form.note"
          label="یادداشت بازگشت"
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="emit('update:modelValue', false)">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="submit"
        >
          بازگرداندن
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
