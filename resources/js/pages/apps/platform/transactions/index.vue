<script setup>
import { exportTableCsv, formatRial } from '@/composables/usePlatformAdmin'

definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const { formatDateTime } = useJalaliDate()

const loading = ref(false)
const transactions = ref([])
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const totalPages = ref(1)
const searchQuery = ref('')
const statusFilter = ref(null)

const statusItems = [
  { title: 'همه', value: null },
  { title: 'موفق', value: 'paid' },
  { title: 'ناموفق', value: 'failed' },
]

const headers = [
  { title: '#', key: 'id', width: 70 },
  { title: 'مجموعه', key: 'tenant_name' },
  { title: 'کاربر', key: 'user_name' },
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
    const res = await $api('/platform/transactions', {
      query: {
        q: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })

    transactions.value = res.transactions ?? []
    total.value = res.total ?? 0
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const exportCsv = async () => {
  try {
    const res = await $api('/platform/transactions/export')
    const rows = res.rows ?? []

    exportTableCsv('platform-transactions.csv', [
      { key: 'id', label: 'شناسه' },
      { key: 'tenant', label: 'مجموعه' },
      { key: 'user', label: 'کاربر' },
      { key: 'amount', label: 'مبلغ' },
      { key: 'status', label: 'وضعیت' },
      { key: 'created_at', label: 'تاریخ' },
    ], rows.map(r => ({
      id: r.id,
      tenant: r.tenant,
      user: r.user,
      amount: r.amount,
      status: r.status,
      created_at: r.created_at,
    })))
  } catch (e) {
    console.error(e)
  }
}

watch([page, perPage, statusFilter], fetchTransactions)

let searchTimer
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchTransactions()
  }, 400)
})

onMounted(fetchTransactions)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            تراکنش‌های پلتفرم
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            همه پرداخت‌های subscription در تمام مجموعه‌ها
          </p>
        </div>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          @click="exportCsv"
        >
          خروجی CSV
        </VBtn>
      </div>

      <VCard>
        <VCardText class="d-flex flex-wrap align-center gap-4">
          <AppTextField
            v-model="searchQuery"
            placeholder="جستجو مجموعه..."
            prepend-inner-icon="tabler-search"
            style="max-inline-size: 260px;"
            clearable
            hide-details
            density="compact"
          />
          <AppSelect
            v-model="statusFilter"
            :items="statusItems"
            label="وضعیت"
            density="compact"
            hide-details
            style="max-inline-size: 160px;"
          />
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
            {{ formatRial(item.amount) }}
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
            {{ formatDateTime(item.created_at) }}
          </template>
        </VDataTable>
        <VDivider />
        <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3">
          <span class="text-body-2">{{ total.toLocaleString('fa-IR') }} تراکنش</span>
          <VPagination
            v-model="page"
            :length="Math.max(totalPages, 1)"
            :total-visible="5"
            density="comfortable"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
