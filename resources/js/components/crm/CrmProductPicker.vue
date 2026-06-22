<script setup>
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: 'محصولات',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const products = ref([])
const loading = ref(false)

const selectedIds = computed({
  get: () => props.modelValue.map(item => item.product_id ?? item.id),
  set: ids => {
    const mapped = ids.map((id, index) => {
      const existing = props.modelValue.find(item => (item.product_id ?? item.id) === id)
      const product = products.value.find(p => p.id === id)

      return {
        product_id: id,
        id,
        name: product?.name ?? existing?.name,
        sku: product?.sku ?? existing?.sku,
        quantity: existing?.quantity ?? 1,
        notes: existing?.notes ?? '',
        sort_order: index,
      }
    })

    emit('update:modelValue', mapped)
  },
})

const fetchProducts = async () => {
  loading.value = true
  try {
    const res = await $api('/products?active_only=1&per_page=200')
    products.value = res.data ?? res
  } finally {
    loading.value = false
  }
}

onMounted(fetchProducts)
</script>

<template>
  <div>
    <AppSelect
      v-if="!readonly"
      v-model="selectedIds"
      :items="products.map(p => ({ title: p.sku ? `${p.name} (${p.sku})` : p.name, value: p.id }))"
      :label="label"
      :loading="loading"
      multiple
      chips
      closable-chips
      clearable
    />

    <div
      v-else-if="modelValue.length"
      class="d-flex flex-wrap gap-2"
    >
      <VChip
        v-for="item in modelValue"
        :key="item.product_id ?? item.id"
        size="small"
        variant="tonal"
        color="primary"
      >
        {{ item.name }}
        <span
          v-if="item.quantity > 1"
          class="ms-1 opacity-70"
        >×{{ item.quantity }}</span>
      </VChip>
    </div>

    <span
      v-else
      class="text-caption text-medium-emphasis"
    >محصولی انتخاب نشده</span>
  </div>
</template>
