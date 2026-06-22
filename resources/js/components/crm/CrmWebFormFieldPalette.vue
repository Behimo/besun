<script setup>
const emit = defineEmits(['add'])

const fieldTypes = [
  { type: 'text', title: 'متن کوتاه', icon: 'tabler-text-size' },
  { type: 'textarea', title: 'متن بلند', icon: 'tabler-message-2' },
  { type: 'phone', title: 'موبایل', icon: 'tabler-device-mobile' },
  { type: 'email', title: 'ایمیل', icon: 'tabler-mail' },
  { type: 'number', title: 'عدد', icon: 'tabler-number' },
  { type: 'select', title: 'انتخابی', icon: 'tabler-list' },
  { type: 'multi_select', title: 'چندانتخابی', icon: 'tabler-list-check' },
  { type: 'date', title: 'تاریخ', icon: 'tabler-calendar' },
  { type: 'checkbox', title: 'چک‌باکس', icon: 'tabler-square-check' },
  { type: 'heading', title: 'عنوان', icon: 'tabler-heading' },
  { type: 'paragraph', title: 'توضیح', icon: 'tabler-align-left' },
]
</script>

<template>
  <VCard variant="tonal">
    <VCardText>
      <div class="text-subtitle-2 mb-3">
        فیلدهای آماده
      </div>
      <div class="d-flex flex-wrap gap-2">
        <VBtn
          v-for="field in fieldTypes"
          :key="field.type"
          size="small"
          variant="outlined"
          :prepend-icon="field.icon"
          @click="emit('add', field.type)"
        >
          {{ field.title }}
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
