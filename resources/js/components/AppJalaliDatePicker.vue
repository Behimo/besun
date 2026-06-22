<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '۱۴۰۳/۰۱/۰۱' },
  hint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <AppJalaliDateTimePicker
    :model-value="props.modelValue"
    :label="label"
    :placeholder="placeholder"
    :hint="hint"
    :enable-time="false"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
