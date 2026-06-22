<script setup>
import CrmConvertLeadDialog from '@/components/crm/CrmConvertLeadDialog.vue'
import CrmEntityProductsDialog from '@/components/crm/CrmEntityProductsDialog.vue'
import CrmProductChips from '@/components/crm/CrmProductChips.vue'
import CrmProductPicker from '@/components/crm/CrmProductPicker.vue'
import CrmSmsSendDialog from '@/components/crm/CrmSmsSendDialog.vue'

const { hasModule, userData } = useAppShell()
const canSendSms = computed(() => hasModule('mod-sms') && (userData.value?.permissions?.includes('sms.send') || userData.value?.tenant?.isOwner))
const smsDialog = ref(false)
const smsTarget = ref({ phone: '', leadId: null })

definePage({ meta: { action: 'read', subject: 'Leads' } })

const router = useRouter()

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'شرکت', key: 'company' },
  { title: 'محصولات', key: 'products', sortable: false },
  { title: 'کمپین', key: 'campaign' },
  { title: 'مرحله', key: 'marketing_stage' },
  { title: 'امتیاز', key: 'score' },
  { title: 'وضعیت', key: 'status' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const leads = ref([])
const campaigns = ref([])
const pipelineStages = ref([])
const loading = ref(true)
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const dialog = ref(false)
const convertDialog = ref(false)
const convertingLead = ref(null)
const statusFilter = ref(null)
const productsDialog = ref(false)
const editingLead = ref(null)

const form = ref({
  name: '',
  email: '',
  phone: '',
  company: '',
  job_title: '',
  city: '',
  score: null,
  source: '',
  campaign_id: null,
  marketing_stage_id: null,
  notes: '',
  status: 'new',
  next_follow_up_date: '',
  next_follow_up_time: '09:00',
  products: [],
})

const { mergeDatetime } = useFollowUpDatetime()

const statusItems = [
  { title: 'همه', value: null },
  { title: 'جدید', value: 'new' },
  { title: 'تبدیل‌شده', value: 'converted' },
]

const sourceItems = [
  { title: 'وب‌سایت', value: 'website' },
  { title: 'شبکه اجتماعی', value: 'social' },
  { title: 'معرفی', value: 'referral' },
  { title: 'تماس ورودی', value: 'inbound' },
  { title: 'کمپین', value: 'campaign' },
  { title: 'سایر', value: 'other' },
]

const statusLabel = status => ({
  new: 'جدید',
  converted: 'تبدیل‌شده',
}[status] ?? status)

const openSmsDialog = item => {
  smsTarget.value = { phone: item.phone, leadId: item.id }
  smsDialog.value = true
}

const openLeadProfile = item => {
  const contactId = item?.contact_id ?? item?.contact?.id

  if (contactId) {
    router.push({ name: 'apps-crm-contacts-id', params: { id: contactId } })
  }
}

const fetchLeads = async () => {
  loading.value = true
  try {
    const res = await $api('/leads', {
      query: {
        status: statusFilter.value || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })
    leads.value = res.data ?? []
    total.value = res.total ?? leads.value.length
  } finally {
    loading.value = false
  }
}

const fetchMeta = async () => {
  const [campaignRes, stageRes] = await Promise.all([
    $api('/campaigns').catch(() => ({ data: [] })),
    $api('/pipeline-stages?type=marketing').catch(() => ({ stages: [] })),
  ])
  campaigns.value = campaignRes.data ?? campaignRes ?? []
  pipelineStages.value = stageRes.stages ?? []
  if (pipelineStages.value.length && !form.value.marketing_stage_id)
    form.value.marketing_stage_id = pipelineStages.value[0].id
}

const saveLead = async () => {
  const body = {
    ...form.value,
    next_follow_up_at: mergeDatetime(form.value.next_follow_up_date, form.value.next_follow_up_time),
  }

  delete body.next_follow_up_date
  delete body.next_follow_up_time

  const { products, ...leadBody } = form.value
  const res = await $api('/leads', { method: 'POST', body: leadBody })

  if (products?.length && res.lead?.id) {
    await $api(`/leads/${res.lead.id}/products`, {
      method: 'PUT',
      body: { products },
    })
  }

  dialog.value = false
  const contactId = res.lead?.contact_id ?? res.lead?.contact?.id

  if (contactId) {
    await router.push({ name: 'apps-crm-contacts-id', params: { id: contactId } })

    return
  }
  form.value = {
    name: '',
    email: '',
    phone: '',
    company: '',
    job_title: '',
    city: '',
    score: null,
    source: '',
    campaign_id: null,
    marketing_stage_id: pipelineStages.value[0]?.id ?? null,
    notes: '',
    status: 'new',
    next_follow_up_date: '',
    next_follow_up_time: '09:00',
    products: [],
  }
  await fetchLeads()
}

const openProductsDialog = lead => {
  editingLead.value = lead
  productsDialog.value = true
}

const openConvertDialog = lead => {
  convertingLead.value = lead
  convertDialog.value = true
}

const onProductsSaved = () => fetchLeads()

const onConvertSuccess = () => fetchLeads()

watch(statusFilter, () => {
  page.value = 1
  fetchLeads()
})

watch([page, perPage], fetchLeads)

onMounted(async () => {
  await fetchMeta()
  await fetchLeads()
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h5 class="text-h5 mb-1">
          لیدها
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          ثبت و مدیریت سرنخ‌های بازاریابی
        </p>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <AppSelect
          v-model="statusFilter"
          :items="statusItems"
          label="وضعیت"
          density="compact"
          style="min-width: 160px;"
          hide-details
        />
        <VBtn
          variant="tonal"
          :to="{ name: 'apps-crm-marketing-funnel' }"
          prepend-icon="tabler-layout-kanban"
        >
          قیف بازاریابی
        </VBtn>
        <VBtn
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          لید جدید
        </VBtn>
      </div>
    </VCardText>

    <VDataTableServer
      :headers="headers"
      :items="leads"
      :items-length="total"
      :loading="loading"
      v-model:page="page"
      v-model:items-per-page="perPage"
      class="cursor-pointer"
      @click:row="(_, { item }) => openLeadProfile(item)"
    >
      <template #item.name="{ item }">
        <span
          class="text-primary font-weight-medium"
          @click.stop="openLeadProfile(item)"
        >
          {{ item.name }}
        </span>
      </template>
      <template #item.products="{ item }">
        <CrmProductChips :products="item.products ?? []" />
      </template>
      <template #item.campaign="{ item }">
        {{ item.campaign?.name ?? '—' }}
      </template>
      <template #item.marketing_stage="{ item }">
        {{ item.marketing_stage?.name ?? '—' }}
      </template>
      <template #item.score="{ item }">
        {{ item.score ?? '—' }}
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="item.status === 'converted' ? 'success' : 'primary'"
          variant="tonal"
        >
          {{ statusLabel(item.status) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn
          v-if="item.contact_id || item.contact?.id"
          @click.stop="openLeadProfile(item)"
        >
          <VIcon icon="tabler-user-circle" />
        </IconBtn>
        <VBtn
          v-if="item.status !== 'converted'"
          size="small"
          variant="text"
          @click.stop="openProductsDialog(item)"
        >
          محصولات
        </VBtn>
        <VBtn
          v-if="item.status !== 'converted'"
          size="small"
          variant="tonal"
          @click.stop="openConvertDialog(item)"
        >
          تبدیل به فروش
        </VBtn>
        <IconBtn
          v-if="canSendSms && item.phone"
          @click.stop="openSmsDialog(item)"
        >
          <VIcon icon="tabler-message" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="640"
  >
    <VCard title="لید جدید">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.name"
              label="نام و نام خانوادگی *"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.email"
              label="ایمیل"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.phone"
              label="تلفن"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.company"
              label="شرکت"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.job_title"
              label="سمت"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.city"
              label="شهر"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model.number="form.score"
              label="امتیاز (۰-۱۰۰)"
              type="number"
              min="0"
              max="100"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.source"
              :items="sourceItems"
              label="منبع"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.campaign_id"
              :items="campaigns.map(c => ({ title: c.name, value: c.id }))"
              label="کمپین"
              clearable
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.marketing_stage_id"
              :items="pipelineStages.map(s => ({ title: s.name, value: s.id }))"
              label="مرحله قیف بازاریابی"
            />
          </VCol>
          <VCol cols="12">
            <AppJalaliDateTimePicker
              v-model="form.next_follow_up_date"
              v-model:time="form.next_follow_up_time"
              label="پیگیری بعدی"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.notes"
              label="یادداشت"
              rows="3"
            />
          </VCol>
          <VCol cols="12">
            <CrmProductPicker v-model="form.products" />
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
          @click="saveLead"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <CrmConvertLeadDialog
    v-model="convertDialog"
    :lead="convertingLead"
    @success="onConvertSuccess"
  />

  <CrmEntityProductsDialog
    v-model="productsDialog"
    entity-type="lead"
    :entity="editingLead"
    @success="onProductsSaved"
  />

  <CrmSmsSendDialog
    v-model="smsDialog"
    :phone="smsTarget.phone"
    :lead-id="smsTarget.leadId"
    related-type="lead"
    :related-id="smsTarget.leadId"
  />
</template>
