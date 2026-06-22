<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformSuperAdmin',
  },
})

const { formatDateTime } = useJalaliDate()

const loading = ref(false)
const logs = ref([])
const page = ref(1)
const totalPages = ref(1)

const actionLabels = {
  'tenant.status_updated': 'تغییر وضعیت مجموعه',
  'sms.approved': 'تأیید SMS',
  'sms.rejected': 'رد SMS',
  'ticket.created': 'ایجاد تیکت',
  'ticket.updated': 'به‌روزرسانی تیکت',
}

const fetchLogs = async () => {
  loading.value = true
  try {
    const res = await $api('/platform/audit-logs', { query: { page: page.value } })
    logs.value = res.logs ?? []
    totalPages.value = res.total_pages ?? 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch(page, fetchLogs)
onMounted(fetchLogs)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="mb-4">
        <h4 class="text-h4 mb-1">
          لاگ عملیات
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          تاریخچه اقدامات مدیران و پشتیبانان پلتفرم
        </p>
      </div>

      <VCard>
        <VDataTable
          :headers="[
            { title: 'عملیات', key: 'action' },
            { title: 'کاربر', key: 'user' },
            { title: 'موضوع', key: 'subject' },
            { title: 'جزئیات', key: 'meta' },
            { title: 'زمان', key: 'created_at' },
          ]"
          :items="logs"
          :loading="loading"
          hide-default-footer
        >
          <template #item.action="{ item }">
            {{ actionLabels[item.action] ?? item.action }}
          </template>
          <template #item.user="{ item }">
            {{ item.platform_staff?.name ?? item.user?.name ?? '—' }}
          </template>
          <template #item.subject="{ item }">
            {{ item.subject_type }} #{{ item.subject_id }}
          </template>
          <template #item.meta="{ item }">
            <code class="text-caption">{{ JSON.stringify(item.meta) }}</code>
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
