<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  title: { type: String, default: 'مخاطبین با بیشترین ارزش' },
})

const formatMoney = val => Number(val ?? 0).toLocaleString('fa-IR')

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'شرکت', key: 'company' },
  { title: 'معاملات', key: 'deals_count' },
  { title: 'ارزش طول عمر', key: 'ltv' },
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
      <template #item.ltv="{ item }">
        {{ formatMoney(item.ltv) }}
      </template>
      <template #item.company="{ item }">
        {{ item.company || '—' }}
      </template>
      <template #no-data>
        <div class="text-medium-emphasis text-center py-6">
          داده‌ای برای نمایش وجود ندارد.
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
