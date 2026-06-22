<script setup>
/* eslint-disable camelcase */
import CrmWebFormFieldPalette from '@/components/crm/CrmWebFormFieldPalette.vue'
import { useCrmWebForms } from '@/composables/useCrmWebForms'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { saveForm } = useCrmWebForms()

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const saving = ref(false)
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

const formState = ref({
  name: '',
  description: '',
  is_active: true,
  schema: { fields: [] },
  settings: {
    create_lead: true,
    campaign_id: null,
    marketing_stage_id: null,
    success_message: 'فرم با موفقیت ثبت شد.',
    redirect_url: '',
    multi_step: false,
    branding: { ...defaultBranding },
    lead_mapping: {
      name: 'name',
      email: 'email',
      phone: 'phone',
      company: 'company',
      notes: 'message',
    },
  },
})

const fieldTypeLabel = {
  text: 'متن کوتاه',
  textarea: 'متن بلند',
  phone: 'موبایل',
  email: 'ایمیل',
  number: 'عدد',
  select: 'انتخابی',
  multi_select: 'چندانتخابی',
  date: 'تاریخ',
  checkbox: 'چک‌باکس',
  heading: 'عنوان',
  paragraph: 'توضیح',
}

const fieldOptionsText = ref({})

const fieldItems = computed(() =>
  formState.value.schema.fields
    .filter(field => !['heading', 'paragraph'].includes(field.type))
    .map(field => ({ title: field.label || field.key, value: field.key })),
)

const resetState = () => {
  const source = props.form
    ? JSON.parse(JSON.stringify(props.form))
    : {
      name: '',
      description: '',
      is_active: true,
      schema: {
        fields: [
          { id: crypto.randomUUID(), key: 'name', type: 'text', label: 'نام و نام خانوادگی', required: true, options: [] },
          { id: crypto.randomUUID(), key: 'phone', type: 'phone', label: 'شماره موبایل', required: true, options: [] },
          { id: crypto.randomUUID(), key: 'message', type: 'textarea', label: 'توضیحات', required: false, options: [] },
        ],
      },
      settings: formState.value.settings,
    }

  formState.value = {
    ...formState.value,
    ...source,
    schema: source.schema || { fields: [] },
    settings: {
      ...formState.value.settings,
      ...(source.settings || {}),
      branding: {
        ...defaultBranding,
        ...(source.settings?.branding || {}),
      },
      lead_mapping: {
        ...formState.value.settings.lead_mapping,
        ...(source.settings?.lead_mapping || {}),
      },
    },
  }

  fieldOptionsText.value = {}
  formState.value.schema.fields.forEach(field => {
    fieldOptionsText.value[field.id] = (field.options || []).map(option => option.title || option.value).join('\n')
  })
}

const loadMeta = async () => {
  const [campaignRes, stageRes] = await Promise.all([
    $api('/campaigns').catch(() => ({ data: [] })),
    $api('/pipeline-stages?type=marketing').catch(() => ({ stages: [] })),
  ])

  campaigns.value = campaignRes.data ?? campaignRes ?? []
  stages.value = stageRes.stages ?? []
}

const addField = type => {
  const count = formState.value.schema.fields.length + 1
  const key = `${type}_${count}`.replace(/\W/g, '_')

  const field = {
    id: crypto.randomUUID(),
    key,
    type,
    label: fieldTypeLabel[type] || 'فیلد جدید',
    placeholder: '',
    required: !['heading', 'paragraph', 'checkbox'].includes(type),
    options: ['select', 'multi_select'].includes(type)
      ? [{ title: 'گزینه ۱', value: 'option_1' }, { title: 'گزینه ۲', value: 'option_2' }]
      : [],
    help_text: '',
  }

  formState.value.schema.fields.push(field)
  fieldOptionsText.value[field.id] = field.options.map(option => option.title).join('\n')
}

const removeField = index => {
  formState.value.schema.fields.splice(index, 1)
}

const moveField = (index, direction) => {
  const targetIndex = index + direction

  if (targetIndex < 0 || targetIndex >= formState.value.schema.fields.length)
    return

  const fields = formState.value.schema.fields
  const [field] = fields.splice(index, 1)

  fields.splice(targetIndex, 0, field)
}

const syncOptions = field => {
  field.options = (fieldOptionsText.value[field.id] || '')
    .split('\n')
    .map(option => option.trim())
    .filter(Boolean)
    .map((option, index) => ({
      title: option,
      value: option.replace(/\s+/g, '_') || `option_${index + 1}`,
    }))
}

const submit = async () => {
  saving.value = true
  try {
    formState.value.schema.fields.forEach(field => {
      if (['select', 'multi_select'].includes(field.type))
        syncOptions(field)
    })

    const payload = JSON.parse(JSON.stringify(formState.value))

    if (!payload.settings.redirect_url)
      payload.settings.redirect_url = null

    await saveForm(payload, props.form?.id ?? null)
    emit('saved')
    isOpen.value = false
  } finally {
    saving.value = false
  }
}

watch(
  () => props.modelValue,
  async value => {
    if (!value)
      return

    resetState()

    if (!campaigns.value.length && !stages.value.length)
      await loadMeta()
  },
)
</script>

<template>
  <VDialog
    v-model="isOpen"
    fullscreen
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ props.form ? 'ویرایش وب‌فرم' : 'ساخت وب‌فرم جدید' }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          @click="isOpen = false"
        />
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            lg="4"
          >
            <VCard variant="outlined">
              <VCardText>
                <AppTextField
                  v-model="formState.name"
                  label="نام فرم"
                  class="mb-4"
                />
                <AppTextarea
                  v-model="formState.description"
                  label="توضیح کوتاه"
                  rows="3"
                  class="mb-4"
                />
                <VSwitch
                  v-model="formState.is_active"
                  label="فرم فعال باشد"
                  color="primary"
                />
                <VSwitch
                  v-model="formState.settings.multi_step"
                  label="نمایش مرحله‌ای (هر فیلد یک صفحه)"
                  color="primary"
                  class="mt-2"
                />
              </VCardText>
            </VCard>

            <CrmWebFormFieldPalette
              class="mt-4"
              @add="addField"
            />

            <VCard
              variant="outlined"
              class="mt-4"
            >
              <VCardText>
                <div class="text-subtitle-2 mb-3">
                  تبدیل به لید
                </div>
                <VSwitch
                  v-model="formState.settings.create_lead"
                  label="بعد از ثبت فرم، لید ساخته شود"
                  color="primary"
                />
                <AppSelect
                  v-model="formState.settings.campaign_id"
                  :items="campaigns"
                  item-title="name"
                  item-value="id"
                  label="کمپین"
                  clearable
                  class="mb-4"
                />
                <AppSelect
                  v-model="formState.settings.marketing_stage_id"
                  :items="stages"
                  item-title="name"
                  item-value="id"
                  label="مرحله قیف بازاریابی"
                  clearable
                  class="mb-4"
                />
                <AppTextField
                  v-model="formState.settings.success_message"
                  label="پیام موفقیت"
                  class="mb-4"
                />
                <AppTextField
                  v-model="formState.settings.redirect_url"
                  label="آدرس انتقال بعد از ثبت"
                  placeholder="https://example.com/thanks"
                />
              </VCardText>
            </VCard>

            <VCard
              variant="outlined"
              class="mt-4"
            >
              <VCardText>
                <div class="text-subtitle-2 mb-3">
                  برندینگ صفحه عمومی
                </div>
                <AppTextField
                  v-model="formState.settings.branding.brand_name"
                  label="نام برند"
                  placeholder="نام مجموعه یا برند"
                  class="mb-4"
                />
                <AppTextField
                  v-model="formState.settings.branding.headline"
                  label="تیتر اصلی"
                  placeholder="مثلاً: درخواست مشاوره رایگان"
                  class="mb-4"
                />
                <AppTextarea
                  v-model="formState.settings.branding.subtitle"
                  label="متن معرفی"
                  rows="2"
                  class="mb-4"
                />
                <AppTextField
                  v-model="formState.settings.branding.logo_url"
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
                      v-model="formState.settings.branding.primary_color"
                      label="رنگ اصلی"
                      type="color"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VTextField
                      v-model="formState.settings.branding.accent_color"
                      label="رنگ تأکیدی"
                      type="color"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VTextField
                      v-model="formState.settings.branding.background_color"
                      label="رنگ پس‌زمینه"
                      type="color"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VTextField
                      v-model="formState.settings.branding.card_color"
                      label="رنگ کارت فرم"
                      type="color"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            lg="8"
          >
            <VAlert
              type="info"
              variant="tonal"
              class="mb-4"
            >
              کلید فیلدها برای mapping لید استفاده می‌شود. برای فیلدهای اصلی از کلیدهای
              <strong>name</strong>،
              <strong>phone</strong>،
              <strong>email</strong>،
              <strong>company</strong>
              و
              <strong>message</strong>
              استفاده کنید.
            </VAlert>

            <VExpansionPanels multiple>
              <VExpansionPanel
                v-for="(field, index) in formState.schema.fields"
                :key="field.id"
              >
                <VExpansionPanelTitle>
                  <div class="d-flex align-center justify-space-between w-100 pe-4">
                    <div>
                      <span class="font-weight-medium">{{ field.label }}</span>
                      <VChip
                        size="x-small"
                        variant="tonal"
                        class="ms-2"
                      >
                        {{ fieldTypeLabel[field.type] }}
                      </VChip>
                    </div>
                    <div class="d-flex gap-1">
                      <VBtn
                        icon="tabler-arrow-up"
                        size="x-small"
                        variant="text"
                        @click.stop="moveField(index, -1)"
                      />
                      <VBtn
                        icon="tabler-arrow-down"
                        size="x-small"
                        variant="text"
                        @click.stop="moveField(index, 1)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        variant="text"
                        color="error"
                        @click.stop="removeField(index)"
                      />
                    </div>
                  </div>
                </VExpansionPanelTitle>

                <VExpansionPanelText>
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="field.label"
                        label="عنوان فیلد"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="field.key"
                        label="کلید فیلد"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="field.placeholder"
                        label="Placeholder"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="field.help_text"
                        label="متن راهنما"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VSwitch
                        v-if="!['heading', 'paragraph'].includes(field.type)"
                        v-model="field.required"
                        label="پاسخ به این فیلد اجباری است"
                        color="primary"
                      />
                    </VCol>
                    <VCol
                      v-if="['select', 'multi_select'].includes(field.type)"
                      cols="12"
                    >
                      <AppTextarea
                        v-model="fieldOptionsText[field.id]"
                        label="گزینه‌ها"
                        hint="هر گزینه در یک خط"
                        rows="4"
                        persistent-hint
                        @blur="syncOptions(field)"
                      />
                    </VCol>
                  </VRow>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>

            <VCard
              variant="outlined"
              class="mt-4"
            >
              <VCardText>
                <div class="text-subtitle-2 mb-3">
                  Mapping لید
                </div>
                <VRow>
                  <VCol
                    v-for="key in ['name', 'phone', 'email', 'company', 'notes']"
                    :key="key"
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="formState.settings.lead_mapping[key]"
                      :items="fieldItems"
                      :label="`فیلد ${key}`"
                      clearable
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isOpen = false"
        >
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="submit"
        >
          ذخیره فرم
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
