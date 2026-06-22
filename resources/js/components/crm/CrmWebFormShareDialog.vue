<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const copied = ref('')

const publicUrl = computed(() => {
  if (!props.form?.public_token || typeof window === 'undefined')
    return ''

  return `${window.location.origin}/public/forms/${props.form.public_token}`
})

const iframeCode = computed(() => {
  if (!publicUrl.value)
    return ''

  return `<iframe src="${publicUrl.value}" width="100%" height="720" style="border:0;border-radius:16px;" loading="lazy"></iframe>`
})

const copyText = async (value, type) => {
  await navigator.clipboard.writeText(value)
  copied.value = type
  setTimeout(() => {
    copied.value = ''
  }, 1800)
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="720"
  >
    <VCard title="اشتراک‌گذاری فرم">
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          این لینک عمومی است و برای پر کردن فرم نیازی به ورود کاربر ندارد.
        </VAlert>

        <AppTextField
          :model-value="publicUrl"
          label="لینک عمومی"
          readonly
          class="mb-3"
        >
          <template #append-inner>
            <VBtn
              size="small"
              variant="text"
              @click="copyText(publicUrl, 'link')"
            >
              {{ copied === 'link' ? 'کپی شد' : 'کپی' }}
            </VBtn>
          </template>
        </AppTextField>

        <AppTextarea
          :model-value="iframeCode"
          label="کد iframe"
          rows="5"
          readonly
        >
          <template #append-inner>
            <VBtn
              size="small"
              variant="text"
              @click="copyText(iframeCode, 'iframe')"
            >
              {{ copied === 'iframe' ? 'کپی شد' : 'کپی' }}
            </VBtn>
          </template>
        </AppTextarea>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          variant="tonal"
          @click="isOpen = false"
        >
          بستن
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
