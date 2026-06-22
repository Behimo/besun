<script setup>
/* eslint-disable camelcase */
import CrmModulePlaceholder from '@/components/CrmModulePlaceholder.vue'
import CrmWebFormBuilderDialog from '@/components/crm/CrmWebFormBuilderDialog.vue'
import CrmWebFormShareDialog from '@/components/crm/CrmWebFormShareDialog.vue'
import { useCrmWebForms } from '@/composables/useCrmWebForms'

definePage({
  meta: {
    action: 'read',
    subject: 'WebForms',
  },
})

const { hasModule, hasCoreModule, userData } = useAppShell()
const { formatDateTime } = useJalaliDate()

const {
  loading,
  error,
  fetchDashboard,
  fetchForms,
  fetchSubmissions,
  fetchFormReport,
  saveForm,
  deleteForm,
} = useCrmWebForms()

const hasWebFormsModule = computed(() => hasModule('mod-web-forms'))

const canCreate = computed(() =>
  userData.value?.permissions?.includes('web_forms.create') || userData.value?.tenant?.isOwner,
)

const canManage = computed(() =>
  userData.value?.permissions?.includes('web_forms.manage') || userData.value?.tenant?.isOwner,
)

const tab = ref('settings')
const dashboard = ref(null)
const forms = ref([])
const submissions = ref({ data: [] })
const report = ref(null)
const selectedFormId = ref(null)
const builderDialog = ref(false)
const shareDialog = ref(false)
const submissionDialog = ref(false)
const editingForm = ref(null)
const sharingForm = ref(null)
const selectedSubmission = ref(null)
const settingsSaving = ref(false)
const campaigns = ref([])
const stages = ref([])

const defaultBranding = {
  brand_name: '',
  headline: '',
  subtitle: '',
  logo_url: '',
  primary_color: '#4A0E17',
  accent_color: '#E8C57D',
  background_color: '#FFF7F0',
  card_color: '#FFFFFF',
}

const settingsForm = ref({
  is_active: true,
  create_lead: true,
  campaign_id: null,
  marketing_stage_id: null,
  success_message: 'فرم با موفقیت ثبت شد.',
  redirect_url: '',
  multi_step: false,
  branding: { ...defaultBranding },
})

const formHeaders = [
  { title: 'نام فرم', key: 'name' },
  { title: 'وضعیت', key: 'is_active' },
  { title: 'پاسخ‌ها', key: 'submissions_count' },
  { title: 'آخرین پاسخ', key: 'last_submitted_at' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const submissionHeaders = [
  { title: 'زمان ثبت', key: 'submitted_at' },
  { title: 'اطلاعات', key: 'payload', sortable: false },
  { title: 'لید', key: 'lead', sortable: false },
  { title: 'وضعیت', key: 'status' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const selectedForm = computed(() => forms.value.find(form => form.id === selectedFormId.value))
const reportSummary = computed(() => report.value?.summary ?? {})
const inputFields = computed(() => (selectedForm.value?.schema?.fields ?? []).filter(field => !['heading', 'paragraph'].includes(field.type)))
const fieldMeta = computed(() => Object.fromEntries(inputFields.value.map(field => [field.key, field])))
const maxDailyCount = computed(() => Math.max(...(report.value?.daily_submissions ?? []).map(day => day.count), 1))

const leadConversionText = computed(() =>
  `${Number(reportSummary.value.conversion_percentage || 0).toLocaleString('fa-IR')}٪`,
)

const loadDashboard = async () => {
  try {
    dashboard.value = await fetchDashboard()
  } catch {
    dashboard.value = null
  }
}

const loadForms = async () => {
  forms.value = await fetchForms()

  if (!selectedFormId.value && forms.value.length)
    selectedFormId.value = forms.value[0].id
}

const loadSubmissions = async () => {
  if (!selectedFormId.value) {
    submissions.value = { data: [] }

    return
  }

  submissions.value = await fetchSubmissions(selectedFormId.value)
}

const loadReport = async () => {
  if (!selectedFormId.value) {
    report.value = null

    return
  }

  report.value = await fetchFormReport(selectedFormId.value)
}

const loadMeta = async () => {
  const [campaignRes, stageRes] = await Promise.all([
    $api('/campaigns').catch(() => ({ data: [] })),
    $api('/pipeline-stages?type=marketing').catch(() => ({ stages: [] })),
  ])

  campaigns.value = campaignRes.data ?? campaignRes ?? []
  stages.value = stageRes.stages ?? []
}

const refreshAll = async () => {
  await Promise.all([loadDashboard(), loadForms()])
  await Promise.all([loadSubmissions(), loadReport()])
}

const hydrateSettingsForm = () => {
  const form = selectedForm.value

  if (!form)
    return

  const settings = form.settings || {}

  settingsForm.value = {
    is_active: Boolean(form.is_active),
    create_lead: settings.create_lead ?? true,
    campaign_id: settings.campaign_id ?? null,
    marketing_stage_id: settings.marketing_stage_id ?? null,
    success_message: settings.success_message || 'فرم با موفقیت ثبت شد.',
    redirect_url: settings.redirect_url || '',
    multi_step: Boolean(settings.multi_step),
    branding: {
      ...defaultBranding,
      ...(settings.branding || {}),
    },
  }
}

const openCreate = () => {
  editingForm.value = null
  builderDialog.value = true
}

const openEdit = form => {
  editingForm.value = form
  builderDialog.value = true
}

const openShare = form => {
  sharingForm.value = form
  shareDialog.value = true
}

const toggleActive = async form => {
  await saveForm({ is_active: !form.is_active }, form.id)
  await refreshAll()
}

const saveSelectedSettings = async () => {
  if (!selectedForm.value)
    return

  settingsSaving.value = true
  try {
    const existingSettings = selectedForm.value.settings || {}

    await saveForm({
      is_active: settingsForm.value.is_active,
      settings: {
        ...existingSettings,
        create_lead: settingsForm.value.create_lead,
        campaign_id: settingsForm.value.campaign_id,
        marketing_stage_id: settingsForm.value.marketing_stage_id,
        success_message: settingsForm.value.success_message,
        redirect_url: settingsForm.value.redirect_url || null,
        multi_step: settingsForm.value.multi_step,
        branding: { ...settingsForm.value.branding },
        lead_mapping: existingSettings.lead_mapping,
      },
    }, selectedForm.value.id)

    await refreshAll()
    hydrateSettingsForm()
  } finally {
    settingsSaving.value = false
  }
}

const onDelete = async form => {
  if (!confirm(`فرم «${form.name}» حذف شود؟ پاسخ‌های ثبت‌شده هم حذف می‌شوند.`))
    return

  await deleteForm(form.id)
  if (selectedFormId.value === form.id)
    selectedFormId.value = null
  await refreshAll()
}

const payloadPreview = payload => Object.entries(payload || {})
  .slice(0, 4)
  .map(([key, value]) => `${fieldMeta.value[key]?.label || key}: ${displayValue(value, fieldMeta.value[key])}`)
  .join(' | ')

const displayValue = (value, field = null) => {
  if (Array.isArray(value))
    return value.map(item => optionTitle(item, field)).join('، ')

  if (typeof value === 'boolean')
    return value ? 'بله' : 'خیر'

  return optionTitle(value, field) || '—'
}

const optionTitle = (value, field = null) => {
  if (value === null || value === undefined || value === '')
    return ''

  const option = (field?.options || []).find(item => item.value === value || item.title === value)

  return option?.title || value
}

const submissionRows = submission => Object.entries(submission?.payload || {}).map(([key, value]) => ({
  key,
  label: fieldMeta.value[key]?.label || key,
  value: displayValue(value, fieldMeta.value[key]),
}))

const openSubmission = submission => {
  selectedSubmission.value = submission
  submissionDialog.value = true
}

watch(selectedFormId, async () => {
  hydrateSettingsForm()
  await Promise.all([loadSubmissions(), loadReport()])
})

onMounted(async () => {
  if (!hasWebFormsModule.value || !hasCoreModule.value)
    return

  await loadMeta()
  await refreshAll()
  hydrateSettingsForm()
})
</script>

<template>
  <div>
    <CrmModulePlaceholder
      v-if="!hasWebFormsModule"
      title="وب‌فرم"
      icon="tabler-forms"
      description="ایجاد فرم لید، لینک عمومی، iframe و تبدیل پاسخ‌ها به لید."
      module-slug="mod-web-forms"
    />

    <div v-else-if="!hasCoreModule">
      <VCard>
        <VCardText class="text-center pa-8">
          <h4 class="text-h4 mb-2">
            ماژول پایه فعال نیست
          </h4>
          <VBtn
            color="primary"
            :to="{ name: 'apps-tenant-modules' }"
          >
            وضعیت ماژول‌ها
          </VBtn>
        </VCardText>
      </VCard>
    </div>

    <div v-else>
      <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
        <div>
          <h4 class="text-h4 mb-1">
            وب‌فرم‌ها
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            فرم بسازید، لینک عمومی بدهید و پاسخ‌ها را به لید تبدیل کنید.
          </p>
        </div>
        <VBtn
          v-if="canCreate"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          فرم جدید
        </VBtn>
      </div>

      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ error }}
      </VAlert>

      <VRow class="mb-4">
        <VCol
          cols="12"
          md="4"
        >
          <VCard variant="tonal">
            <VCardText>
              <div class="text-body-2 text-medium-emphasis">
                کل فرم‌ها
              </div>
              <div class="text-h4">
                {{ Number(dashboard?.forms_count || 0).toLocaleString('fa-IR') }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <VCard variant="tonal">
            <VCardText>
              <div class="text-body-2 text-medium-emphasis">
                فرم‌های فعال
              </div>
              <div class="text-h4">
                {{ Number(dashboard?.active_forms_count || 0).toLocaleString('fa-IR') }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <VCard variant="tonal">
            <VCardText>
              <div class="text-body-2 text-medium-emphasis">
                پاسخ‌های ثبت‌شده
              </div>
              <div class="text-h4">
                {{ Number(dashboard?.submissions_count || 0).toLocaleString('fa-IR') }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol
          cols="12"
          lg="4"
        >
          <VCard :loading="loading">
            <VCardItem>
              <VCardTitle>فرم‌ها</VCardTitle>
              <VCardSubtitle>یک فرم را برای مدیریت انتخاب کنید.</VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VList class="py-0">
              <VListItem
                v-for="form in forms"
                :key="form.id"
                :active="form.id === selectedFormId"
                rounded="lg"
                class="ma-2 web-form-list-item"
                @click="selectedFormId = form.id"
              >
                <template #prepend>
                  <VAvatar
                    :color="form.is_active ? 'primary' : 'secondary'"
                    variant="tonal"
                    rounded
                  >
                    <VIcon icon="tabler-forms" />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-medium">
                  {{ form.name }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ Number(form.submissions_count || 0).toLocaleString('fa-IR') }} پاسخ
                  <span class="mx-1">•</span>
                  {{ form.is_active ? 'فعال' : 'غیرفعال' }}
                </VListItemSubtitle>

                <template #append>
                  <div class="d-flex gap-1">
                    <VBtn
                      icon="tabler-link"
                      size="x-small"
                      variant="text"
                      @click.stop="openShare(form)"
                    />
                    <VBtn
                      v-if="canManage"
                      icon="tabler-edit"
                      size="x-small"
                      variant="text"
                      @click.stop="openEdit(form)"
                    />
                  </div>
                </template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="8"
        >
          <VAlert
            v-if="!selectedForm"
            type="info"
            variant="tonal"
          >
            ابتدا یک فرم بسازید یا از لیست انتخاب کنید.
          </VAlert>

          <template v-else>
            <VCard class="mb-4 selected-form-hero">
              <VCardText>
                <div class="d-flex align-center justify-space-between flex-wrap gap-4">
                  <div>
                    <div class="d-flex align-center gap-2 mb-2">
                      <VChip
                        :color="selectedForm.is_active ? 'success' : 'secondary'"
                        size="small"
                        variant="tonal"
                      >
                        {{ selectedForm.is_active ? 'فعال' : 'غیرفعال' }}
                      </VChip>
                      <VChip
                        v-if="selectedForm.settings?.multi_step"
                        size="small"
                        variant="tonal"
                        color="info"
                      >
                        مرحله‌ای
                      </VChip>
                    </div>
                    <h5 class="text-h5 mb-1">
                      {{ selectedForm.name }}
                    </h5>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                      {{ selectedForm.description || 'برای این فرم توضیحی ثبت نشده است.' }}
                    </p>
                  </div>
                  <div class="d-flex gap-2">
                    <VBtn
                      variant="tonal"
                      prepend-icon="tabler-link"
                      @click="openShare(selectedForm)"
                    >
                      لینک فرم
                    </VBtn>
                    <VBtn
                      v-if="canManage"
                      variant="tonal"
                      prepend-icon="tabler-edit"
                      @click="openEdit(selectedForm)"
                    >
                      ویرایش فیلدها
                    </VBtn>
                  </div>
                </div>
              </VCardText>
            </VCard>

            <VCard :loading="loading">
              <VTabs v-model="tab">
                <VTab value="settings">
                  تنظیمات
                </VTab>
                <VTab value="report">
                  گزارش
                </VTab>
                <VTab value="submissions">
                  پاسخ‌ها و لیدها
                </VTab>
              </VTabs>

              <VDivider />

              <VCardText>
                <VWindow v-model="tab">
                  <VWindowItem value="settings">
                    <VRow>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <VCard variant="outlined">
                          <VCardText>
                            <div class="text-subtitle-1 font-weight-medium mb-4">
                              تنظیمات فرم
                            </div>
                            <VSwitch
                              v-model="settingsForm.is_active"
                              label="فرم فعال باشد"
                              color="primary"
                            />
                            <VSwitch
                              v-model="settingsForm.multi_step"
                              label="نمایش مرحله‌ای"
                              color="primary"
                            />
                            <VSwitch
                              v-model="settingsForm.create_lead"
                              label="بعد از ثبت، لید ساخته شود"
                              color="primary"
                            />
                            <AppSelect
                              v-model="settingsForm.campaign_id"
                              :items="campaigns"
                              item-title="name"
                              item-value="id"
                              label="کمپین"
                              clearable
                              class="mb-4"
                            />
                            <AppSelect
                              v-model="settingsForm.marketing_stage_id"
                              :items="stages"
                              item-title="name"
                              item-value="id"
                              label="مرحله قیف بازاریابی"
                              clearable
                              class="mb-4"
                            />
                            <AppTextField
                              v-model="settingsForm.success_message"
                              label="پیام موفقیت"
                              class="mb-4"
                            />
                            <AppTextField
                              v-model="settingsForm.redirect_url"
                              label="آدرس انتقال بعد از ثبت"
                              placeholder="https://example.com/thanks"
                            />
                          </VCardText>
                        </VCard>
                      </VCol>

                      <VCol
                        cols="12"
                        md="6"
                      >
                        <VCard variant="outlined">
                          <VCardText>
                            <div class="text-subtitle-1 font-weight-medium mb-4">
                              برندینگ صفحه عمومی
                            </div>
                            <AppTextField
                              v-model="settingsForm.branding.brand_name"
                              label="نام برند"
                              class="mb-4"
                            />
                            <AppTextField
                              v-model="settingsForm.branding.headline"
                              label="تیتر اصلی"
                              class="mb-4"
                            />
                            <AppTextarea
                              v-model="settingsForm.branding.subtitle"
                              label="متن معرفی"
                              rows="2"
                              class="mb-4"
                            />
                            <AppTextField
                              v-model="settingsForm.branding.logo_url"
                              label="آدرس لوگو"
                              placeholder="https://example.com/logo.png"
                              class="mb-4"
                            />
                            <VRow>
                              <VCol
                                cols="12"
                                sm="6"
                              >
                                <VTextField
                                  v-model="settingsForm.branding.primary_color"
                                  label="رنگ اصلی"
                                  type="color"
                                />
                              </VCol>
                              <VCol
                                cols="12"
                                sm="6"
                              >
                                <VTextField
                                  v-model="settingsForm.branding.accent_color"
                                  label="رنگ تأکیدی"
                                  type="color"
                                />
                              </VCol>
                              <VCol
                                cols="12"
                                sm="6"
                              >
                                <VTextField
                                  v-model="settingsForm.branding.background_color"
                                  label="رنگ پس‌زمینه"
                                  type="color"
                                />
                              </VCol>
                              <VCol
                                cols="12"
                                sm="6"
                              >
                                <VTextField
                                  v-model="settingsForm.branding.card_color"
                                  label="رنگ کارت"
                                  type="color"
                                />
                              </VCol>
                            </VRow>
                          </VCardText>
                        </VCard>
                      </VCol>
                    </VRow>

                    <div class="d-flex justify-end mt-4">
                      <VBtn
                        v-if="canManage"
                        color="primary"
                        :loading="settingsSaving"
                        prepend-icon="tabler-device-floppy"
                        @click="saveSelectedSettings"
                      >
                        ذخیره تنظیمات
                      </VBtn>
                    </div>
                  </VWindowItem>

                  <VWindowItem value="report">
                    <VRow class="mb-4">
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <VCard variant="tonal">
                          <VCardText>
                            <div class="text-body-2 text-medium-emphasis">
                              کل پاسخ‌ها
                            </div>
                            <div class="text-h4">
                              {{ Number(reportSummary.total_submissions || 0).toLocaleString('fa-IR') }}
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <VCard variant="tonal">
                          <VCardText>
                            <div class="text-body-2 text-medium-emphasis">
                              لید ساخته‌شده
                            </div>
                            <div class="text-h4">
                              {{ Number(reportSummary.lead_count || 0).toLocaleString('fa-IR') }}
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <VCard variant="tonal">
                          <VCardText>
                            <div class="text-body-2 text-medium-emphasis">
                              نرخ تبدیل به لید
                            </div>
                            <div class="text-h4">
                              {{ leadConversionText }}
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>
                    </VRow>

                    <VRow>
                      <VCol
                        cols="12"
                        md="7"
                      >
                        <VCard variant="outlined">
                          <VCardText>
                            <div class="text-subtitle-1 font-weight-medium mb-4">
                              درصد تکمیل فیلدها
                            </div>
                            <div
                              v-for="field in report?.field_stats || []"
                              :key="field.key"
                              class="mb-5"
                            >
                              <div class="d-flex justify-space-between mb-2">
                                <span>{{ field.label }}</span>
                                <span class="font-weight-medium">{{ Number(field.completion_percentage).toLocaleString('fa-IR') }}٪</span>
                              </div>
                              <VProgressLinear
                                :model-value="field.completion_percentage"
                                height="8"
                                rounded
                                color="primary"
                              />
                              <div class="text-caption text-medium-emphasis mt-1">
                                {{ Number(field.filled_count).toLocaleString('fa-IR') }} پر شده از {{ Number(reportSummary.total_submissions || 0).toLocaleString('fa-IR') }} پاسخ
                              </div>
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>

                      <VCol
                        cols="12"
                        md="5"
                      >
                        <VCard variant="outlined">
                          <VCardText>
                            <div class="text-subtitle-1 font-weight-medium mb-4">
                              روند ۳۰ روز اخیر
                            </div>
                            <div class="daily-bars">
                              <div
                                v-for="day in report?.daily_submissions || []"
                                :key="day.date"
                                class="daily-bar"
                                :title="`${day.date}: ${day.count}`"
                              >
                                <div
                                  class="daily-bar__fill"
                                  :style="{ height: `${Math.max(6, (day.count / maxDailyCount) * 100)}%` }"
                                />
                              </div>
                            </div>
                          </VCardText>
                        </VCard>
                      </VCol>
                    </VRow>

                    <VCard
                      v-if="(report?.field_stats || []).some(field => field.options?.length)"
                      variant="outlined"
                      class="mt-4"
                    >
                      <VCardText>
                        <div class="text-subtitle-1 font-weight-medium mb-4">
                          توزیع گزینه‌ها
                        </div>
                        <VRow>
                          <VCol
                            v-for="field in (report?.field_stats || []).filter(item => item.options?.length)"
                            :key="field.key"
                            cols="12"
                            md="6"
                          >
                            <div class="font-weight-medium mb-3">
                              {{ field.label }}
                            </div>
                            <div
                              v-for="option in field.options"
                              :key="option.value"
                              class="mb-3"
                            >
                              <div class="d-flex justify-space-between text-body-2 mb-1">
                                <span>{{ option.title }}</span>
                                <span>{{ Number(option.percentage).toLocaleString('fa-IR') }}٪</span>
                              </div>
                              <VProgressLinear
                                :model-value="option.percentage"
                                height="6"
                                rounded
                                color="info"
                              />
                            </div>
                          </VCol>
                        </VRow>
                      </VCardText>
                    </VCard>
                  </VWindowItem>

                  <VWindowItem value="submissions">
                    <div class="d-flex justify-end mb-4">
                      <VBtn
                        variant="tonal"
                        prepend-icon="tabler-refresh"
                        @click="loadSubmissions"
                      >
                        بروزرسانی پاسخ‌ها
                      </VBtn>
                    </div>

                    <VDataTable
                      :headers="submissionHeaders"
                      :items="submissions.data"
                      item-value="id"
                    >
                      <template #item.submitted_at="{ item }">
                        {{ formatDateTime(item.submitted_at) }}
                      </template>

                      <template #item.payload="{ item }">
                        <div class="text-body-2 submission-preview">
                          {{ payloadPreview(item.payload) }}
                        </div>
                      </template>

                      <template #item.lead="{ item }">
                        <RouterLink
                          v-if="item.lead"
                          :to="{ name: 'apps-crm-leads', query: { status: 'new' } }"
                        >
                          {{ item.lead.name }}
                        </RouterLink>
                        <span
                          v-else
                          class="text-medium-emphasis"
                        >لید ساخته نشده</span>
                      </template>

                      <template #item.status="{ item }">
                        <VChip
                          size="small"
                          variant="tonal"
                          :color="item.lead_id ? 'success' : 'secondary'"
                        >
                          {{ item.lead_id ? 'لید ساخته شد' : 'دریافت شد' }}
                        </VChip>
                      </template>

                      <template #item.actions="{ item }">
                        <VBtn
                          size="small"
                          variant="tonal"
                          prepend-icon="tabler-eye"
                          @click="openSubmission(item)"
                        >
                          مشاهده
                        </VBtn>
                      </template>
                    </VDataTable>
                  </VWindowItem>
                </VWindow>
              </VCardText>
            </VCard>
          </template>
        </VCol>
      </VRow>

      <CrmWebFormBuilderDialog
        v-model="builderDialog"
        :form="editingForm"
        @saved="refreshAll"
      />

      <CrmWebFormShareDialog
        v-model="shareDialog"
        :form="sharingForm"
      />

      <VDialog
        v-model="submissionDialog"
        max-width="760"
        scrollable
      >
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>جزئیات پاسخ</span>
            <VBtn
              icon="tabler-x"
              variant="text"
              @click="submissionDialog = false"
            />
          </VCardTitle>
          <VDivider />
          <VCardText v-if="selectedSubmission">
            <VRow class="mb-4">
              <VCol
                cols="12"
                md="4"
              >
                <div class="text-caption text-medium-emphasis">
                  زمان ثبت
                </div>
                <div class="font-weight-medium">
                  {{ formatDateTime(selectedSubmission.submitted_at) }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <div class="text-caption text-medium-emphasis">
                  وضعیت
                </div>
                <VChip
                  size="small"
                  variant="tonal"
                  :color="selectedSubmission.lead_id ? 'success' : 'secondary'"
                >
                  {{ selectedSubmission.lead_id ? 'لید ساخته شد' : 'دریافت شد' }}
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <div class="text-caption text-medium-emphasis">
                  IP
                </div>
                <div class="font-weight-medium">
                  {{ selectedSubmission.ip_address || '—' }}
                </div>
              </VCol>
            </VRow>

            <VCard
              v-if="selectedSubmission.lead"
              variant="tonal"
              class="mb-4"
            >
              <VCardText>
                <div class="d-flex justify-space-between flex-wrap gap-3">
                  <div>
                    <div class="text-subtitle-2 mb-1">
                      لید ساخته‌شده
                    </div>
                    <div class="font-weight-medium">
                      {{ selectedSubmission.lead.name }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis">
                      {{ selectedSubmission.lead.phone || 'بدون موبایل' }}
                      <span v-if="selectedSubmission.lead.email"> • {{ selectedSubmission.lead.email }}</span>
                      <span v-if="selectedSubmission.lead.company"> • {{ selectedSubmission.lead.company }}</span>
                    </div>
                  </div>
                  <VBtn
                    variant="tonal"
                    :to="{ name: 'apps-crm-leads', query: { status: 'new' } }"
                  >
                    مشاهده لیدها
                  </VBtn>
                </div>
              </VCardText>
            </VCard>

            <div class="text-subtitle-2 mb-3">
              پاسخ فیلدها
            </div>
            <VTable density="comfortable">
              <tbody>
                <tr
                  v-for="row in submissionRows(selectedSubmission)"
                  :key="row.key"
                >
                  <td class="text-medium-emphasis">
                    {{ row.label }}
                  </td>
                  <td class="font-weight-medium">
                    {{ row.value }}
                  </td>
                </tr>
              </tbody>
            </VTable>

            <div
              v-if="selectedSubmission.user_agent"
              class="text-caption text-medium-emphasis mt-4"
            >
              {{ selectedSubmission.user_agent }}
            </div>
          </VCardText>
        </VCard>
      </VDialog>
    </div>
  </div>
</template>

<style scoped>
.web-form-list-item {
  cursor: pointer;
}

.selected-form-hero {
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  background:
    radial-gradient(circle at top left, rgba(var(--v-theme-primary), 0.12), transparent 34%),
    rgb(var(--v-theme-surface));
}

.submission-preview {
  max-width: 360px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.daily-bars {
  display: flex;
  align-items: end;
  min-height: 180px;
  padding-block-start: 12px;
  gap: 4px;
}

.daily-bar {
  display: flex;
  align-items: end;
  flex: 1;
  height: 160px;
  border-radius: 999px;
  background: rgba(var(--v-theme-primary), 0.08);
  overflow: hidden;
}

.daily-bar__fill {
  width: 100%;
  border-radius: 999px;
  background: rgb(var(--v-theme-primary));
}
</style>
