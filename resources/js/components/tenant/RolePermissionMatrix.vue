<script setup>
const props = defineProps({
  permissions: { type: Array, default: () => [] },
  groupLabels: { type: Object, default: () => ({}) },
  modelValue: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const selected = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const grouped = computed(() => {
  const map = new Map()

  for (const perm of props.permissions) {
    const key = perm.group ?? 'other'
    if (!map.has(key))
      map.set(key, { key, label: props.groupLabels[key] ?? key, items: [] })

    map.get(key).items.push(perm)
  }

  return [...map.values()]
})

const toggle = (name, checked) => {
  if (props.readonly)
    return

  const set = new Set(selected.value)
  if (checked)
    set.add(name)
  else
    set.delete(name)

  selected.value = [...set]
}

const isChecked = name => selected.value.includes(name)
</script>

<template>
  <div class="permission-matrix">
    <div
      v-for="group in grouped"
      :key="group.key"
      class="mb-4"
    >
      <div class="text-subtitle-2 font-weight-medium mb-2">
        {{ group.label }}
      </div>
      <VRow dense>
        <VCol
          v-for="perm in group.items"
          :key="perm.name"
          cols="12"
          sm="6"
          md="4"
        >
          <VCheckbox
            :model-value="isChecked(perm.name)"
            :label="perm.label"
            :readonly="readonly"
            density="compact"
            hide-details
            @update:model-value="toggle(perm.name, $event)"
          />
        </VCol>
      </VRow>
    </div>
  </div>
</template>
