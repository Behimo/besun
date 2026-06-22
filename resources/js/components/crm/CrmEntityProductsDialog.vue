<script setup>
import CrmProductPicker from '@/components/crm/CrmProductPicker.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  entityType: {
    type: String,
    required: true,
    validator: v => ['lead', 'deal'].includes(v),
  },
  entity: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'success'])

const products = ref([])
const loading = ref(false)
const saving = ref(false)

const entityLabel = computed(() => {
  if (!props.entity)
    return ''

  return props.entity.name ?? props.entity.title ?? ''
})

const dialogTitle = computed(() => props.entityType === 'lead' ? 'محصولات لید' : 'محصولات معامله')

const productsEndpoint = computed(() => {
  if (!props.entity?.id)
    return null

  return `/${props.entityType === 'lead' ? 'leads' : 'deals'}/${props.entity.id}/products`
})

const loadProducts = async () => {
  if (!productsEndpoint.value)
    return

  loading.value = true
  try {
    const res = await $api(productsEndpoint.value)
    products.value = (res.products ?? []).map(p => ({
      product_id: p.id,
      id: p.id,
      name: p.name,
      sku: p.sku,
      quantity: p.quantity ?? 1,
      notes: p.notes ?? '',
    }))
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (!productsEndpoint.value)
    return

  saving.value = true
  try {
    await $api(productsEndpoint.value, {
      method: 'PUT',
      body: { products: products.value },
    })
    emit('update:modelValue', false)
    emit('success')
  } finally {
    saving.value = false
  }
}

const close = () => {
  emit('update:modelValue', false)
}

watch(() => props.modelValue, open => {
  if (open && props.entity?.id)
    loadProducts()
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="dialogTitle">
      <VCardText>
        <p
          v-if="entityLabel"
          class="text-body-2 text-medium-emphasis mb-4"
        >
          {{ entityLabel }}
        </p>
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-4"
        />
        <CrmProductPicker
          v-else
          v-model="products"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="close">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="loading"
          @click="save"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
