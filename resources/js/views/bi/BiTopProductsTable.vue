<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  title: { type: String, default: 'محصولات پرتقاضا' },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const headers = [
  { title: 'محصول', key: 'name' },
  { title: 'درآمد پیش‌فاکتور', key: 'quote_revenue' },
  { title: 'تعداد پیش‌فاکتور', key: 'quote_quantity' },
  { title: 'اتصال به موجودیت', key: 'linked_quantity' },
]
</script>

<template>
  <VCard :title="title">
    <VDataTable
      :headers="headers"
      :items="items"
      :items-per-page="5"
      density="comfortable"
      hide-default-footer
    >
      <template #item.quote_revenue="{ item }">
        {{ formatMoney(item.quote_revenue) }}
      </template>
      <template #no-data>
        <div class="text-medium-emphasis text-center py-6">
          داده‌ای برای نمایش وجود ندارد.
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
