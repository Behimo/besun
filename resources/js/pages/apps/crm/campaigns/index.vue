<script setup>
import CrmSmsSendDialog from '@/components/crm/CrmSmsSendDialog.vue'

definePage({ meta: { action: 'read', subject: 'Campaigns' } })

const { hasModule, userData } = useAppShell()
const canSendSms = computed(() => hasModule('mod-sms') && (userData.value?.permissions?.includes('sms.send') || userData.value?.tenant?.isOwner))
const smsDialog = ref(false)
const smsCampaign = ref(null)

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'کانال', key: 'channel' },
  { title: 'وضعیت', key: 'status' },
  { title: 'لیدها', key: 'leads_count' },
  { title: 'بودجه', key: 'budget' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const campaigns = ref([])
const loading = ref(true)
const dialog = ref(false)
const form = ref({
  name: '',
  description: '',
  channel: '',
  status: 'draft',
  budget: null,
  starts_at: '',
  ends_at: '',
})

const statusLabel = {
  draft: 'پیش‌نویس',
  active: 'فعال',
  paused: 'متوقف',
  completed: 'پایان‌یافته',
}

const statusColor = {
  draft: 'secondary',
  active: 'success',
  paused: 'warning',
  completed: 'info',
}

const channelItems = [
  { title: 'شبکه‌های اجتماعی', value: 'social' },
  { title: 'پیامک', value: 'sms' },
  { title: 'ایمیل', value: 'email' },
  { title: 'وب‌سایت', value: 'web' },
  { title: 'تبلیغات', value: 'ads' },
  { title: 'سایر', value: 'other' },
]

const fetchCampaigns = async () => {
  loading.value = true
  try {
    const res = await $api('/campaigns')
    campaigns.value = res.data ?? res
  } finally {
    loading.value = false
  }
}

const saveCampaign = async () => {
  await $api('/campaigns', { method: 'POST', body: form.value })
  dialog.value = false
  form.value = {
    name: '',
    description: '',
    channel: '',
    status: 'draft',
    budget: null,
    starts_at: '',
    ends_at: '',
  }
  await fetchCampaigns()
}

onMounted(fetchCampaigns)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h5 class="text-h5 mb-1">
          کمپین‌ها
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          کمپین‌های بازاریابی و منبع جذب لید
        </p>
      </div>
      <VBtn
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        کمپین جدید
      </VBtn>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="campaigns"
      :loading="loading"
    >
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="statusColor[item.status] ?? 'secondary'"
          variant="tonal"
        >
          {{ statusLabel[item.status] ?? item.status }}
        </VChip>
      </template>
      <template #item.budget="{ item }">
        {{ item.budget ? Number(item.budget).toLocaleString('fa-IR') : '—' }}
      </template>
      <template #item.actions="{ item }">
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'apps-crm-leads' }"
        >
          لیدها
        </VBtn>
        <VBtn
          v-if="canSendSms && item.channel === 'sms'"
          size="small"
          variant="tonal"
          @click="smsCampaign = item; smsDialog = true"
        >
          اجرای پیامک
        </VBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="560"
  >
    <VCard title="کمپین جدید">
      <VCardText>
        <AppTextField
          v-model="form.name"
          label="نام کمپین *"
          class="mb-4"
        />
        <AppTextarea
          v-model="form.description"
          label="توضیحات"
          rows="2"
          class="mb-4"
        />
        <AppSelect
          v-model="form.channel"
          :items="channelItems"
          label="کانال"
          class="mb-4"
        />
        <AppSelect
          v-model="form.status"
          :items="Object.entries(statusLabel).map(([value, title]) => ({ title, value }))"
          label="وضعیت"
          class="mb-4"
        />
        <AppTextField
          v-model.number="form.budget"
          label="بودجه"
          type="number"
          class="mb-4"
        />
        <VRow>
          <VCol cols="6">
            <AppJalaliDatePicker
              v-model="form.starts_at"
              label="شروع"
            />
          </VCol>
          <VCol cols="6">
            <AppJalaliDatePicker
              v-model="form.ends_at"
              label="پایان"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="saveCampaign"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <CrmSmsSendDialog
    v-if="smsCampaign"
    v-model="smsDialog"
    :title="`کمپین پیامکی: ${smsCampaign.name}`"
    :filters="{ audience: 'leads', campaign_id: smsCampaign.id }"
    related-type="campaign"
    :related-id="smsCampaign.id"
  />
</template>
