<script setup>
import { emailValidator, iranianMobileValidator, requiredValidator } from '@/@core/utils/validators'
import { useCrmWebForms } from '@/composables/useCrmWebForms'

definePage({
  meta: {
    public: true,
    layout: 'blank',
  },
})

const route = useRoute()
const { fetchPublicForm, submitPublicForm } = useCrmWebForms()

const formRef = ref()
const loading = ref(true)
const submitting = ref(false)
const form = ref(null)
const payload = ref({})
const error = ref('')
const success = ref('')
const currentStep = ref(0)

const token = computed(() => route.params.token)
const fields = computed(() => form.value?.schema?.fields ?? [])
const isMultiStep = computed(() => Boolean(form.value?.settings?.multi_step))

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

const branding = computed(() => ({
  ...defaultBranding,
  ...(form.value?.settings?.branding || {}),
}))

const pageStyle = computed(() => ({
  '--public-primary': branding.value.primary_color,
  '--public-accent': branding.value.accent_color,
  '--public-background': branding.value.background_color,
  '--public-card': branding.value.card_color,
}))

const brandName = computed(() => branding.value.brand_name || form.value?.name || 'فرم آنلاین')
const heroHeadline = computed(() => branding.value.headline || form.value?.name || 'فرم را تکمیل کنید')
const heroSubtitle = computed(() => branding.value.subtitle || form.value?.description || 'اطلاعات شما با امنیت ثبت می‌شود و تیم ما در اولین فرصت پیگیری می‌کند.')

const isInputField = field => !['heading', 'paragraph'].includes(field.type)

const steps = computed(() => {
  if (!isMultiStep.value)
    return []

  const result = []
  let intro = []

  fields.value.forEach(field => {
    if (!isInputField(field)) {
      intro.push(field)

      return
    }

    result.push({
      id: field.id,
      field,
      intro: [...intro],
    })
    intro = []
  })

  return result
})

const totalSteps = computed(() => (isMultiStep.value ? steps.value.length : 1))
const currentStepData = computed(() => steps.value[currentStep.value] ?? null)
const progressPercent = computed(() => {
  if (!totalSteps.value)
    return 0

  return Math.round(((currentStep.value + 1) / totalSteps.value) * 100)
})

const fieldRules = field => {
  const rules = []

  if (field.required)
    rules.push(requiredValidator)

  if (field.type === 'email')
    rules.push(emailValidator)

  if (field.type === 'phone')
    rules.push(iranianMobileValidator)

  return rules
}

const fieldItems = field => (field.options || []).map(option => ({
  title: option.title || option.value,
  value: option.value || option.title,
}))

const initializePayload = () => {
  fields.value.forEach(field => {
    if (field.type === 'checkbox')
      payload.value[field.key] = false
    else if (field.type === 'multi_select')
      payload.value[field.key] = []
    else if (isInputField(field))
      payload.value[field.key] = ''
  })
}

const loadForm = async () => {
  loading.value = true
  error.value = ''
  currentStep.value = 0
  try {
    form.value = await fetchPublicForm(token.value)
    initializePayload()
  } catch (e) {
    error.value = e?.data?.message || 'فرم مورد نظر پیدا نشد یا غیرفعال است.'
  } finally {
    loading.value = false
  }
}

const validateCurrentStep = async () => {
  if (!formRef.value?.validate)
    return { valid: true }

  const { valid } = await formRef.value.validate()

  return { valid: !!valid }
}

const goNext = async () => {
  const { valid } = await validateCurrentStep()

  if (!valid) {
    error.value = 'لطفاً این مرحله را تکمیل کنید.'

    return
  }

  error.value = ''

  if (currentStep.value < totalSteps.value - 1) {
    currentStep.value += 1
    await nextTick()
    formRef.value?.resetValidation()
  }
}

const goBack = async () => {
  if (currentStep.value > 0) {
    error.value = ''
    currentStep.value -= 1
    await nextTick()
    formRef.value?.resetValidation()
  }
}

const submit = async () => {
  const { valid } = await validateCurrentStep()

  if (!valid) {
    error.value = 'لطفاً این مرحله را تکمیل کنید.'

    return
  }

  if (isMultiStep.value && currentStep.value < totalSteps.value - 1) {
    error.value = ''
    currentStep.value += 1
    await nextTick()
    formRef.value?.resetValidation()

    return
  }

  submitting.value = true
  error.value = ''
  success.value = ''

  try {
    const res = await submitPublicForm(token.value, payload.value)

    success.value = res.message || 'فرم با موفقیت ثبت شد.'

    if (res.redirect_url) {
      window.location.href = res.redirect_url

      return
    }

    payload.value = {}
    initializePayload()
    currentStep.value = 0
    formRef.value?.resetValidation()
  } catch (e) {
    error.value = e?.data?.message || 'ثبت فرم با خطا مواجه شد.'
  } finally {
    submitting.value = false
  }
}

const startNewSubmission = async () => {
  success.value = ''
  error.value = ''
  payload.value = {}
  initializePayload()
  currentStep.value = 0
  await nextTick()
  formRef.value?.resetValidation()
}

onMounted(loadForm)
</script>

<template>
  <div
    class="public-web-form-page"
    :style="pageStyle"
  >
    <VContainer class="public-web-form-container">
      <VRow
        justify="center"
        align="center"
      >
        <VCol
          v-if="!loading && form"
          cols="12"
          lg="5"
        >
          <div class="public-web-form-hero">
            <div class="brand-pill mb-6">
              <VAvatar
                v-if="branding.logo_url"
                :image="branding.logo_url"
                size="42"
              />
              <VAvatar
                v-else
                size="42"
                class="brand-avatar"
              >
                <VIcon icon="tabler-sparkles" />
              </VAvatar>
              <span>{{ brandName }}</span>
            </div>

            <h1 class="hero-title">
              {{ heroHeadline }}
            </h1>
            <p class="hero-subtitle">
              {{ heroSubtitle }}
            </p>

            <div class="hero-highlights">
              <div class="hero-highlight">
                <VIcon icon="tabler-shield-check" />
                <span>ثبت امن اطلاعات</span>
              </div>
              <div class="hero-highlight">
                <VIcon icon="tabler-clock" />
                <span>پیگیری سریع توسط تیم شما</span>
              </div>
            </div>
          </div>
        </VCol>

        <VCol
          cols="12"
          md="9"
          lg="6"
        >
          <VCard
            class="public-web-form-card"
            :loading="loading"
          >
            <VCardText class="pa-sm-10">
              <div
                v-if="loading"
                class="text-center py-10"
              >
                در حال بارگذاری فرم...
              </div>

              <VAlert
                v-else-if="error && !form"
                type="error"
                variant="tonal"
              >
                {{ error }}
              </VAlert>

              <template v-else>
                <div class="form-card-header mb-8">
                  <VAvatar
                    v-if="branding.logo_url"
                    :image="branding.logo_url"
                    rounded
                    size="56"
                    class="mb-4"
                  />
                  <VAvatar
                    v-else
                    rounded
                    size="56"
                    class="form-icon mb-4"
                  >
                    <VIcon
                      icon="tabler-forms"
                      size="30"
                    />
                  </VAvatar>
                  <div class="text-overline form-brand mb-1">
                    {{ brandName }}
                  </div>
                  <h2 class="text-h4 mb-2">
                    {{ form.name }}
                  </h2>
                  <p class="text-body-1 text-medium-emphasis mb-0">
                    {{ form.description || 'لطفاً اطلاعات زیر را تکمیل کنید.' }}
                  </p>
                </div>

                <div
                  v-if="isMultiStep && totalSteps > 0"
                  class="mb-6"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-body-2 text-medium-emphasis">
                      مرحله {{ currentStep + 1 }} از {{ totalSteps }}
                    </span>
                    <span class="text-body-2 font-weight-medium">
                      {{ progressPercent }}٪
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="progressPercent"
                    :color="branding.primary_color"
                    height="8"
                    rounded
                  />
                </div>

                <div
                  v-if="success"
                  class="success-state"
                >
                  <VAvatar
                    size="72"
                    class="success-state__icon mb-4"
                  >
                    <VIcon
                      icon="tabler-check"
                      size="38"
                    />
                  </VAvatar>
                  <h3 class="text-h4 mb-2">
                    اطلاعات شما ثبت شد
                  </h3>
                  <p class="text-body-1 text-medium-emphasis mb-6">
                    {{ success }}
                  </p>
                  <VBtn
                    variant="tonal"
                    :color="branding.primary_color"
                    @click="startNewSubmission"
                  >
                    ثبت پاسخ جدید
                  </VBtn>
                </div>

                <VAlert
                  v-if="error && form"
                  type="error"
                  variant="tonal"
                  class="mb-4"
                >
                  {{ error }}
                </VAlert>

                <VForm
                  v-if="!success"
                  ref="formRef"
                  @submit.prevent="submit"
                >
                  <!-- حالت تک‌صفحه‌ای -->
                  <template v-if="!isMultiStep">
                    <div
                      v-for="field in fields"
                      :key="field.id"
                      class="mb-4"
                    >
                      <h3
                        v-if="field.type === 'heading'"
                        class="text-h5 mb-2"
                      >
                        {{ field.label }}
                      </h3>

                      <p
                        v-else-if="field.type === 'paragraph'"
                        class="text-body-2 text-medium-emphasis"
                      >
                        {{ field.label }}
                      </p>

                      <AppTextarea
                        v-else-if="field.type === 'textarea'"
                        v-model="payload[field.key]"
                        :label="field.label"
                        :placeholder="field.placeholder"
                        :hint="field.help_text"
                        :rules="fieldRules(field)"
                        rows="4"
                        persistent-hint
                      />

                      <AppSelect
                        v-else-if="field.type === 'select'"
                        v-model="payload[field.key]"
                        :items="fieldItems(field)"
                        :label="field.label"
                        :hint="field.help_text"
                        :rules="fieldRules(field)"
                        persistent-hint
                      />

                      <AppSelect
                        v-else-if="field.type === 'multi_select'"
                        v-model="payload[field.key]"
                        :items="fieldItems(field)"
                        :label="field.label"
                        :hint="field.help_text"
                        :rules="fieldRules(field)"
                        multiple
                        chips
                        persistent-hint
                      />

                      <VCheckbox
                        v-else-if="field.type === 'checkbox'"
                        v-model="payload[field.key]"
                        :label="field.label"
                        :rules="fieldRules(field)"
                      />

                      <AppTextField
                        v-else
                        v-model="payload[field.key]"
                        :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'"
                        :label="field.label"
                        :placeholder="field.placeholder"
                        :hint="field.help_text"
                        :rules="fieldRules(field)"
                        persistent-hint
                      />
                    </div>

                    <VBtn
                      type="submit"
                      :color="branding.primary_color"
                      size="large"
                      block
                      :loading="submitting"
                    >
                      ثبت فرم
                    </VBtn>
                  </template>

                  <!-- حالت مرحله‌ای: هر فیلد یک صفحه -->
                  <template v-else-if="currentStepData">
                    <div
                      v-for="introField in currentStepData.intro"
                      :key="introField.id"
                      class="mb-4"
                    >
                      <h3
                        v-if="introField.type === 'heading'"
                        class="text-h5 mb-2"
                      >
                        {{ introField.label }}
                      </h3>
                      <p
                        v-else
                        class="text-body-2 text-medium-emphasis"
                      >
                        {{ introField.label }}
                      </p>
                    </div>

                    <div class="mb-6">
                      <AppTextarea
                        v-if="currentStepData.field.type === 'textarea'"
                        v-model="payload[currentStepData.field.key]"
                        :label="currentStepData.field.label"
                        :placeholder="currentStepData.field.placeholder"
                        :hint="currentStepData.field.help_text"
                        :rules="fieldRules(currentStepData.field)"
                        rows="4"
                        persistent-hint
                        autofocus
                      />

                      <AppSelect
                        v-else-if="currentStepData.field.type === 'select'"
                        v-model="payload[currentStepData.field.key]"
                        :items="fieldItems(currentStepData.field)"
                        :label="currentStepData.field.label"
                        :hint="currentStepData.field.help_text"
                        :rules="fieldRules(currentStepData.field)"
                        persistent-hint
                        autofocus
                      />

                      <AppSelect
                        v-else-if="currentStepData.field.type === 'multi_select'"
                        v-model="payload[currentStepData.field.key]"
                        :items="fieldItems(currentStepData.field)"
                        :label="currentStepData.field.label"
                        :hint="currentStepData.field.help_text"
                        :rules="fieldRules(currentStepData.field)"
                        multiple
                        chips
                        persistent-hint
                      />

                      <VCheckbox
                        v-else-if="currentStepData.field.type === 'checkbox'"
                        v-model="payload[currentStepData.field.key]"
                        :label="currentStepData.field.label"
                        :rules="fieldRules(currentStepData.field)"
                      />

                      <AppTextField
                        v-else
                        v-model="payload[currentStepData.field.key]"
                        :type="currentStepData.field.type === 'number' ? 'number' : currentStepData.field.type === 'date' ? 'date' : 'text'"
                        :label="currentStepData.field.label"
                        :placeholder="currentStepData.field.placeholder"
                        :hint="currentStepData.field.help_text"
                        :rules="fieldRules(currentStepData.field)"
                        persistent-hint
                        autofocus
                      />
                    </div>

                    <div class="d-flex gap-3">
                      <VBtn
                        v-if="currentStep > 0"
                        type="button"
                        variant="tonal"
                        size="large"
                        class="flex-grow-1"
                        @click.prevent="goBack"
                      >
                        قبلی
                      </VBtn>

                      <VBtn
                        v-if="currentStep < totalSteps - 1"
                        type="button"
                        :color="branding.primary_color"
                        size="large"
                        class="flex-grow-1"
                        @click.prevent="goNext"
                      >
                        بعدی
                      </VBtn>

                      <VBtn
                        v-else
                        type="submit"
                        :color="branding.primary_color"
                        size="large"
                        class="flex-grow-1"
                        :loading="submitting"
                      >
                        ثبت فرم
                      </VBtn>
                    </div>
                  </template>

                  <VAlert
                    v-else-if="isMultiStep"
                    type="warning"
                    variant="tonal"
                  >
                    این فرم فیلد ورودی ندارد؛ حالت مرحله‌ای قابل نمایش نیست.
                  </VAlert>
                </VForm>
              </template>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VContainer>
  </div>
</template>

<style scoped>
.public-web-form-page {
  min-height: 100vh;
  padding-block: 48px;
  color: #1f1720;
  background:
    radial-gradient(circle at 15% 20%, color-mix(in srgb, var(--public-accent) 38%, transparent), transparent 30%),
    radial-gradient(circle at 82% 12%, color-mix(in srgb, var(--public-primary) 24%, transparent), transparent 34%),
    linear-gradient(135deg, color-mix(in srgb, var(--public-background) 92%, white), var(--public-background));
}

.public-web-form-container {
  min-height: calc(100vh - 96px);
  display: flex;
  align-items: center;
}

.public-web-form-hero {
  position: relative;
  overflow: hidden;
  padding: 42px;
  border: 1px solid color-mix(in srgb, var(--public-primary) 18%, transparent);
  border-radius: 32px;
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--public-primary) 94%, #000), color-mix(in srgb, var(--public-primary) 72%, #fff)),
    var(--public-primary);
  box-shadow: 0 24px 80px color-mix(in srgb, var(--public-primary) 24%, transparent);
  color: white;
}

.public-web-form-hero::after {
  position: absolute;
  inset-block-start: -80px;
  inset-inline-start: -60px;
  width: 220px;
  height: 220px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--public-accent) 36%, transparent);
  content: "";
  filter: blur(4px);
}

.brand-pill {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  padding: 8px 14px 8px 8px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(16px);
  gap: 10px;
}

.brand-avatar,
.form-icon {
  background: color-mix(in srgb, var(--public-accent) 78%, white);
  color: var(--public-primary);
}

.hero-title {
  position: relative;
  z-index: 1;
  max-width: 520px;
  margin-block: 0 18px;
  font-size: clamp(2.2rem, 5vw, 4.4rem);
  font-weight: 900;
  line-height: 1.18;
}

.hero-subtitle {
  position: relative;
  z-index: 1;
  max-width: 520px;
  margin-block-end: 30px;
  color: rgba(255, 255, 255, 0.82);
  font-size: 1.08rem;
  line-height: 2;
}

.hero-highlights {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 12px;
}

.hero-highlight {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.1);
  gap: 10px;
}

.public-web-form-card {
  border: 1px solid color-mix(in srgb, var(--public-primary) 14%, transparent);
  border-radius: 30px;
  background: color-mix(in srgb, var(--public-card) 94%, white);
  box-shadow: 0 18px 70px color-mix(in srgb, var(--public-primary) 18%, transparent);
}

.form-card-header {
  text-align: center;
}

.form-brand {
  color: var(--public-primary);
  letter-spacing: 0;
}

.success-state {
  padding: 32px 18px;
  text-align: center;
}

.success-state__icon {
  background: color-mix(in srgb, var(--public-accent) 72%, white);
  color: var(--public-primary);
}

.public-web-form-card :deep(.v-field--focused .v-field__outline),
.public-web-form-card :deep(.v-field--focused .v-label.v-field-label) {
  color: var(--public-primary);
}

@media (max-width: 959px) {
  .public-web-form-page {
    padding-block: 24px;
  }

  .public-web-form-container {
    min-height: auto;
  }

  .public-web-form-hero {
    padding: 24px;
    border-radius: 24px;
  }

  .hero-title {
    margin-block-end: 10px;
    font-size: 2rem;
  }

  .hero-subtitle {
    margin-block-end: 18px;
    font-size: 0.96rem;
    line-height: 1.8;
  }

  .hero-highlights {
    grid-template-columns: 1fr;
  }

  .public-web-form-card {
    border-radius: 24px;
  }
}

@media (max-width: 1279px) {
  .public-web-form-hero {
    padding: 30px;
  }

  .hero-title {
    font-size: clamp(2rem, 7vw, 3.2rem);
  }
}

@media (max-width: 959px) {
  .public-web-form-hero {
    padding: 24px;
  }
}
</style>
