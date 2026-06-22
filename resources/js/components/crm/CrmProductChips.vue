<script setup>
const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  max: {
    type: Number,
    default: 2,
  },
})

const visible = computed(() => props.products.slice(0, props.max))
const extra = computed(() => Math.max(0, props.products.length - props.max))
</script>

<template>
  <div
    v-if="products.length"
    class="d-flex flex-wrap gap-1"
  >
    <VChip
      v-for="product in visible"
      :key="product.id"
      size="x-small"
      variant="tonal"
      color="secondary"
      label
    >
      {{ product.name }}
    </VChip>
    <VChip
      v-if="extra"
      size="x-small"
      variant="outlined"
      label
    >
      +{{ extra }}
    </VChip>
  </div>
</template>
