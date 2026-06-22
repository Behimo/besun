<script setup>
import { useCrmSms } from '@/composables/useCrmSms'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'ارسال پیامک' },
  filters: { type: Object, default: () => ({}) },
  phone: { type: String, default: '' },
  leadId: { type: [Number, String], default: null },
  contactId: { type: [Number, String], default: null },
  relatedType: { type: String, default: null },
  relatedId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:modelValue', 'sent'])

const { previewAudience, sendSms, fetchNumbers } = useCrmSms()

const open = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const message = ref('')
const fromNumber = ref('')
const numbers = ref([])
const preview = ref(null)
const sending = ref(false)
const previewLoading = ref(false)
const error = ref('')

const loadNumbers = async () => {
  try {
    const res = await fetchNumbers()
    numbers.value = res.numbers ?? []
    if (!fromNumber.value)
      fromNumber.value = res.default_from_number || numbers.value[0] || ''
  } catch (e) {
    console.error(e)
  }
}

const runPreview = async () => {
  if (props.phone)
    return

  previewLoading.value = true
  error.value = ''
  try {
    preview.value = await previewAudience(props.filters)
  } catch (e) {
    error.value = e?.data?.message || 'خطا در پیش‌نمایش گیرندگان'
  } finally {
    previewLoading.value = false
  }
}

const submit = async () => {
  if (!message.value.trim()) {
    error.value = 'متن پیام الزامی است.'

    return
  }

  sending.value = true
  error.value = ''

  try {
    const payload = {
      message: message.value,
      from_number: fromNumber.value || undefined,
      related_type: props.relatedType || undefined,
      related_id: props.relatedId || undefined,
      ...props.filters,
    }

    if (props.phone) {
      payload.phone = props.phone
      payload.lead_id = props.leadId || undefined
      payload.contact_id = props.contactId || undefined
    }

    await sendSms(payload)
    emit('sent')
    open.value = false
    message.value = ''
  } catch (e) {
    error.value = e?.data?.message || 'خطا در ارسال پیامک'
  } finally {
    sending.value = false
  }
}

watch(open, async val => {
  if (val) {
    error.value = ''
    preview.value = null
    await loadNumbers()
    if (!props.phone)
      await runPreview()
  }
})
</script>

<template>
  <VDialog
    v-model="open"
    max-width="560"
  >
    <VCard :title="title">
      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <p
          v-if="phone"
          class="text-body-2 mb-4"
        >
          گیرنده: {{ phone }}
        </p>

        <p
          v-else-if="preview"
          class="text-body-2 mb-4"
        >
          {{ preview.valid_count }} گیرنده معتبر از {{ preview.total_records }} رکورد
        </p>

        <VSelect
          v-if="numbers.length"
          v-model="fromNumber"
          :items="numbers"
          label="خط ارسال"
          class="mb-4"
        />

        <VTextarea
          v-model="message"
          label="متن پیام"
          rows="4"
          auto-grow
          class="mb-2"
        />
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="open = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="sending"
          @click="submit"
        >
          ارسال
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
