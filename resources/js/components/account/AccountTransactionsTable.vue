<script setup>
import { formatRial } from '@/composables/useTenantSubscription'

defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const { formatDateTime } = useJalaliDate()
const page = ref(1)
const perPage = ref(10)
const loading = ref(false)
const transactions = ref([])
const total = ref(0)
const totalPages = ref(1)

const headers = [
  { title: '#', key: 'id', width: 80 },
  { title: 'مجموعه', key: 'tenant_name' },
  { title: 'خلاصه', key: 'summary', sortable: false },
  { title: 'مبلغ', key: 'amount' },
  { title: 'وضعیت', key: 'status', sortable: false },
  { title: 'تاریخ', key: 'created_at' },
]

const statusMap = {
  paid: { label: 'موفق', color: 'success' },
  failed: { label: 'ناموفق', color: 'error' },
}

const fetchTransactions = async () => {
  loading.value = true

  try {
    const res = await $api('/billing/transactions', {
      query: {
        q: searchQuery.value || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })

    transactions.value = res.transactions ?? []
    total.value = res.total ?? 0
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
    transactions.value = []
  } finally {
    loading.value = false
  }
}

watch([page, perPage], fetchTransactions)

let searchTimer
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchTransactions()
  }, 400)
})

onMounted(fetchTransactions)

defineExpose({ refresh: fetchTransactions })
</script>

<template>
  <VCard :title="embedded ? undefined : 'تراکنش‌های اخیر'">
    <VCardText class="d-flex flex-wrap align-center gap-4">
      <AppTextField
        v-model="searchQuery"
        placeholder="جستجو بر اساس نام مجموعه..."
        prepend-inner-icon="tabler-search"
        style="max-inline-size: 280px;"
        clearable
        hide-details
        density="compact"
      />
      <VSpacer />
      <div class="d-flex align-center gap-2">
        <span class="text-body-2">نمایش</span>
        <AppSelect
          v-model="perPage"
          :items="[5, 10, 20]"
          density="compact"
          hide-details
          style="max-inline-size: 90px;"
        />
      </div>
    </VCardText>

    <VDivider />

    <VDataTable
      :headers="headers"
      :items="transactions"
      :loading="loading"
      :items-per-page="perPage"
      hide-default-footer
      class="text-no-wrap"
    >
      <template #item.amount="{ item }">
        <span class="font-weight-medium">{{ formatRial(item.amount) }}</span>
      </template>

      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="statusMap[item.status]?.color || 'secondary'"
          variant="tonal"
        >
          {{ statusMap[item.status]?.label || item.status }}
        </VChip>
      </template>

      <template #item.created_at="{ item }">
        {{ formatDateTime(item.paid_at || item.created_at) }}
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex align-center justify-space-between flex-wrap gap-3 pa-4">
          <span class="text-body-2 text-medium-emphasis">
            {{ total }} تراکنش
          </span>
          <VPagination
            v-model="page"
            :length="totalPages"
            :total-visible="5"
            density="comfortable"
          />
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
