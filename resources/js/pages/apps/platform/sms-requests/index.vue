<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'PlatformSmsQueue',
  },
})

const { formatDateTime } = useJalaliDate()

const requests = ref([])
const loading = ref(true)
const error = ref('')
const actionLoading = ref(false)
const rejectDialog = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')

const fetchRequests = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await $api('/platform/sms/requests')
    requests.value = res.requests ?? []
  } catch (e) {
    error.value = e?.data?.message || 'دسترسی مدیریت پلتفرم پیامک ندارید.'
  } finally {
    loading.value = false
  }
}

const approve = async tenantId => {
  actionLoading.value = true
  try {
    await $api(`/platform/sms/tenants/${tenantId}/approve`, { method: 'POST', body: {} })
    await fetchRequests()
  } catch (e) {
    error.value = e?.data?.message || 'خطا در تأیید'
  } finally {
    actionLoading.value = false
  }
}

const openReject = tenantId => {
  rejectTarget.value = tenantId
  rejectReason.value = ''
  rejectDialog.value = true
}

const reject = async () => {
  if (!rejectTarget.value || !rejectReason.value.trim())
    return

  actionLoading.value = true
  try {
    await $api(`/platform/sms/tenants/${rejectTarget.value}/reject`, {
      method: 'POST',
      body: { reason: rejectReason.value },
    })
    rejectDialog.value = false
    await fetchRequests()
  } catch (e) {
    error.value = e?.data?.message || 'خطا در رد درخواست'
  } finally {
    actionLoading.value = false
  }
}

onMounted(fetchRequests)
</script>

<template>
  <VCard :loading="loading">
    <VCardText>
      <h4 class="text-h4 mb-1">
        مدیریت درخواست‌های پنل پیامک
      </h4>
      <p class="text-body-2 text-medium-emphasis mb-4">
        تأیید و فعال‌سازی پنل IPPanel برای مجموعه‌ها
      </p>

      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ error }}
      </VAlert>

      <VTable v-if="requests.length">
        <thead>
          <tr>
            <th>مجموعه</th>
            <th>نام</th>
            <th>موبایل</th>
            <th>کدملی</th>
            <th>تاریخ درخواست</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in requests"
            :key="row.tenant.id"
          >
            <td>{{ row.tenant.name }}</td>
            <td>{{ row.request.name_family }}</td>
            <td>{{ row.request.mobile_number }}</td>
            <td>{{ row.request.national_code }}</td>
            <td>{{ formatDateTime(row.request.created_at) }}</td>
            <td class="d-flex gap-2">
              <VBtn
                size="small"
                color="success"
                :loading="actionLoading"
                @click="approve(row.tenant.id)"
              >
                تأیید
              </VBtn>
              <VBtn
                size="small"
                color="error"
                variant="tonal"
                @click="openReject(row.tenant.id)"
              >
                رد
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>

      <p
        v-else-if="!loading"
        class="text-medium-emphasis"
      >
        درخواست در انتظاری وجود ندارد.
      </p>
    </VCardText>
  </VCard>

  <VDialog
    v-model="rejectDialog"
    max-width="420"
  >
    <VCard title="رد درخواست">
      <VCardText>
        <VTextarea
          v-model="rejectReason"
          label="دلیل رد"
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="error"
          :loading="actionLoading"
          @click="reject"
        >
          رد درخواست
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
