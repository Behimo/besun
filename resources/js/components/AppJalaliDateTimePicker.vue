<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  time: { type: String, default: '09:00' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '۱۴۰۳/۰۱/۰۱' },
  enableTime: { type: Boolean, default: true },
  hint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:time'])

const { formatDate, formatDateTime, moment } = useJalaliDate()

const menuOpen = ref(false)
const localTime = ref(props.time)

watch(() => props.time, val => {
  localTime.value = val || '09:00'
})

const displayText = computed(() => {
  if (!props.modelValue)
    return ''

  if (props.enableTime && localTime.value)
    return formatDateTime(`${props.modelValue}T${localTime.value}:00`)

  return formatDate(props.modelValue, 'jYYYY/jMM/jDD')
})

const onDateSelect = gregorian => {
  emit('update:modelValue', gregorian)
}

const onTimeChange = val => {
  localTime.value = val
  emit('update:time', val)
}

const applyAndClose = () => {
  menuOpen.value = false
}

const clearValue = () => {
  emit('update:modelValue', '')
  emit('update:time', '09:00')
  localTime.value = '09:00'
  menuOpen.value = false
}
</script>

<template>
  <VMenu
    v-model="menuOpen"
    :close-on-content-click="false"
    location="bottom"
    :z-index="2401"
  >
    <template #activator="{ props: menuProps }">
      <VTextField
        v-bind="menuProps"
        :model-value="displayText"
        :label="label"
        :placeholder="placeholder"
        :hint="hint || (enableTime ? 'برای انتخاب تاریخ و ساعت کلیک کنید' : 'برای انتخاب تاریخ کلیک کنید')"
        persistent-hint
        readonly
        dir="rtl"
        append-inner-icon="tabler-calendar"
        clearable
        @click:clear.stop="clearValue"
      />
    </template>

    <VCard
      min-width="300"
      class="jalali-datetime-picker__menu"
    >
      <VCardText class="pa-4">
        <AppJalaliMiniCalendar
          :model-value="modelValue"
          @update:model-value="onDateSelect"
        />

        <AppTextField
          v-if="enableTime"
          :model-value="localTime"
          label="ساعت"
          type="time"
          class="mt-3"
          hide-details
          @update:model-value="onTimeChange"
        />
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3">
        <VBtn
          variant="text"
          size="small"
          @click="menuOpen = false"
        >
          انصراف
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          size="small"
          :disabled="!modelValue"
          @click="applyAndClose"
        >
          تأیید
        </VBtn>
      </VCardActions>
    </VCard>
  </VMenu>
</template>

<style lang="scss" scoped>
.jalali-datetime-picker__menu {
  overflow: visible;
}
</style>
