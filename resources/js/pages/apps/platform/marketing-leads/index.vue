<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformAdmin',
  },
})

const { formatDateTime } = useJalaliDate()

const loading = ref(false)
const leads = ref([])
const page = ref(1)
const totalPages = ref(1)
const searchQuery = ref('')

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'موبایل', key: 'phone' },
  { title: 'ایمیل', key: 'email' },
  { title: 'منبع', key: 'source' },
  { title: 'پیام', key: 'message', sortable: false },
  { title: 'تاریخ', key: 'created_at' },
]

const fetchLeads = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/marketing-leads', {
      query: {
        q: searchQuery.value || undefined,
        page: page.value,
      },
    })

    leads.value = res.leads ?? []
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch(page, fetchLeads)

let searchTimer
watch(searchQuery, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchLeads()
  }, 400)
})

onMounted(fetchLeads)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          لیدهای سایت
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          درخواست‌های تماس از صفحات عمومی
        </p>
      </div>

      <VCard>
        <VCardText>
          <AppTextField
            v-model="searchQuery"
            placeholder="جستجو..."
            prepend-inner-icon="tabler-search"
            style="max-inline-size: 280px;"
            clearable
            hide-details
            density="compact"
          />
        </VCardText>
        <VDivider />
        <VDataTable
          :headers="headers"
          :items="leads"
          :loading="loading"
          hide-default-footer
        >
          <template #item.message="{ item }">
            <span class="text-truncate d-inline-block" style="max-width: 200px;">{{ item.message }}</span>
          </template>
          <template #item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
          </template>
        </VDataTable>
        <VDivider />
        <VCardText class="d-flex justify-end">
          <VPagination
            v-model="page"
            :length="Math.max(totalPages, 1)"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
