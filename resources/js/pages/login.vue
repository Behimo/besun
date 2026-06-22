<script setup>
import AuthCoverLayout from '@/components/auth/AuthCoverLayout.vue'
import { resolveCustomerPostLoginRoute } from '@/composables/usePlatformAdmin'
import { VForm } from 'vuetify/components/VForm'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()

const { toLatinDigits } = useJalaliDate()

const step = ref('phone')
const phone = ref('')
const normalizedPhone = ref('')
const otp = ref('')
const displayPhone = ref('')
const isLoading = ref(false)
const resendCooldown = ref(0)
const otpExpiresIn = ref(0)
const errors = ref({})
const generalError = ref('')
const debugCode = ref('')
const refPhoneForm = ref()
const refOtpForm = ref()

let resendTimer = null
let expireTimer = null

const normalizePhone = value => {
  const digits = toLatinDigits(value).replace(/\D/g, '')

  if (digits.startsWith('98') && digits.length === 12)
    return `0${digits.slice(2)}`

  if (digits.length === 10 && digits.startsWith('9'))
    return `0${digits}`

  return digits
}

const normalizeOtp = value => {
  if (Array.isArray(value))
    return toLatinDigits(value.join('')).replace(/\D/g, '').slice(0, 6)

  return toLatinDigits(String(value ?? '')).replace(/\D/g, '').slice(0, 6)
}

const formatPhoneDisplay = value => {
  const digits = String(value).replace(/\D/g, '')

  if (digits.length === 11 && digits.startsWith('09')) {
    return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`
  }

  return value
}

const startResendCooldown = seconds => {
  resendCooldown.value = seconds
  clearInterval(resendTimer)
  resendTimer = setInterval(() => {
    if (resendCooldown.value > 0)
      resendCooldown.value -= 1
    else
      clearInterval(resendTimer)
  }, 1000)
}

const startOtpExpiry = seconds => {
  otpExpiresIn.value = seconds
  clearInterval(expireTimer)
  expireTimer = setInterval(() => {
    if (otpExpiresIn.value > 0)
      otpExpiresIn.value -= 1
    else
      clearInterval(expireTimer)
  }, 1000)
}

const sendOtp = async () => {
  const { valid } = await refPhoneForm.value.validate()
  if (!valid)
    return

  isLoading.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const phoneForApi = normalizePhone(phone.value)

    const res = await $api('/auth/otp/send', {
      method: 'POST',
      body: { phone: phoneForApi },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
        generalError.value = response._data?.errors?.phone?.[0] ?? response._data?.message ?? 'خطا در ارسال کد تأیید'
      },
    })

    normalizedPhone.value = phoneForApi
    displayPhone.value = res.phone ?? phoneForApi
    step.value = 'otp'
    otp.value = ''
    debugCode.value = ''
    startResendCooldown(60)
    startOtpExpiry(res.expires_in ?? 300)

    if (res.debug_code)
      debugCode.value = res.debug_code
  } catch (err) {
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

const verifyOtp = async () => {
  const code = normalizeOtp(otp.value)

  if (code.length !== 6)
    return

  isLoading.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const res = await $api('/auth/otp/verify', {
      method: 'POST',
      body: {
        phone: normalizedPhone.value || normalizePhone(phone.value),
        code,
      },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
        generalError.value = response._data?.errors?.code?.[0]
          ?? response._data?.errors?.phone?.[0]
          ?? response._data?.message
          ?? 'کد تأیید نامعتبر است'
      },
    })

    if (! res?.accessToken)
      throw new Error('پاسخ ورود نامعتبر است')

    const { accessToken, userData, userAbilityRules } = res

    setAuthSession({ accessToken, userData, userAbilityRules }, ability)

    await nextTick(() => {
      if (route.query.to) {
        router.replace(String(route.query.to))

        return
      }

      router.replace(resolveCustomerPostLoginRoute())
    })
  } catch (err) {
    console.error(err)
    if (! generalError.value)
      generalError.value = 'خطا در ورود. دوباره تلاش کنید.'
  } finally {
    isLoading.value = false
  }
}

const resendOtp = async () => {
  if (resendCooldown.value > 0)
    return

  await sendOtp()
}

const goBackToPhone = () => {
  step.value = 'phone'
  otp.value = ''
  normalizedPhone.value = ''
  errors.value = {}
  debugCode.value = ''
  otpExpiresIn.value = 0
  clearInterval(expireTimer)
}

watch(otp, val => {
  const normalized = normalizeOtp(val)

  if (normalized !== val)
    otp.value = normalized
})

onMounted(() => {
  if (route.query.logout === '1' || route.query.reset === '1')
    clearAuthSession(ability)

  if (route.query.reason === 'session_expired')
    generalError.value = 'نشست شما منقضی شده است. لطفاً دوباره وارد شوید.'
})

onUnmounted(() => {
  clearInterval(resendTimer)
  clearInterval(expireTimer)
})
</script>

<template>
  <AuthCoverLayout>
    <div v-if="step === 'phone'">
      <h4 class="text-h4 mb-2">
        ورود با موبایل
      </h4>
      <p class="text-body-1 text-medium-emphasis mb-8">
        شماره موبایل خود را وارد کنید تا کد تأیید برایتان ارسال شود.
      </p>

      <VForm
        ref="refPhoneForm"
        @submit.prevent="sendOtp"
      >
        <AppTextField
          v-model="phone"
          label="شماره موبایل"
          placeholder="۰۹۱۲۳۴۵۶۷۸۹"
          type="tel"
          inputmode="numeric"
          autofocus
          dir="ltr"
          class="text-start"
          :rules="[requiredValidator, iranianMobileValidator]"
          :error-messages="errors.phone"
        />

        <VAlert
          v-if="generalError && !errors.phone?.length"
          type="error"
          variant="tonal"
          class="mt-4"
        >
          {{ generalError }}
        </VAlert>

        <VBtn
          block
          type="submit"
          class="mt-6"
          size="large"
          :loading="isLoading"
        >
          دریافت کد تأیید
        </VBtn>
      </VForm>
    </div>

    <div v-else>
      <h4 class="text-h4 mb-2">
        کد تأیید
      </h4>
      <p class="text-body-1 text-medium-emphasis mb-8">
        کد ۶ رقمی ارسال‌شده به
        <strong dir="ltr">{{ formatPhoneDisplay(displayPhone) }}</strong>
        را وارد کنید.
      </p>

      <VAlert
        v-if="debugCode"
        type="info"
        variant="tonal"
        class="mb-4"
      >
        کد تست (محیط توسعه): <strong dir="ltr">{{ debugCode }}</strong>
      </VAlert>

      <VForm
        ref="refOtpForm"
        @submit.prevent="verifyOtp"
      >
        <p class="text-body-2 mb-3">
          رمز یکبار مصرف
        </p>

        <div
          class="otp-input-wrapper mb-2"
          dir="ltr"
        >
          <VOtpInput
            v-model="otp"
            type="text"
            inputmode="numeric"
            length="6"
            class="pa-0 otp-input-ltr"
            :disabled="isLoading"
            :error="!!errors.code"
            @finish="verifyOtp"
          />
        </div>

        <p
          v-if="otpExpiresIn > 0"
          class="text-body-2 text-medium-emphasis mb-4"
        >
          اعتبار کد: {{ Math.floor(otpExpiresIn / 60) }}:{{ String(otpExpiresIn % 60).padStart(2, '0') }}
        </p>
        <p
          v-else-if="step === 'otp'"
          class="text-warning text-body-2 mb-4"
        >
          اعتبار کد تمام شده — «ارسال مجدد کد» را بزنید.
        </p>

        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <p
          v-else-if="errors.code"
          class="text-error text-caption mb-4"
        >
          {{ Array.isArray(errors.code) ? errors.code[0] : errors.code }}
        </p>

        <div class="d-flex justify-space-between align-center mb-6">
          <VBtn
            variant="text"
            size="small"
            :disabled="isLoading"
            @click="goBackToPhone"
          >
            تغییر شماره
          </VBtn>

          <VBtn
            variant="text"
            size="small"
            color="primary"
            :disabled="resendCooldown > 0 || isLoading"
            @click="resendOtp"
          >
            <span v-if="resendCooldown > 0">
              ارسال مجدد ({{ resendCooldown }})
            </span>
            <span v-else>
              ارسال مجدد کد
            </span>
          </VBtn>
        </div>

        <VBtn
          block
          type="submit"
          size="large"
          :loading="isLoading"
          :disabled="normalizeOtp(otp).length !== 6"
        >
          ورود
        </VBtn>
      </VForm>
    </div>
  </AuthCoverLayout>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";

.layout-blank .otp-input-wrapper {
  direction: ltr;
  display: flex;
  justify-content: center;
}

.layout-blank .v-otp-input.otp-input-ltr {
  direction: ltr;

  .v-otp-input__content {
    direction: ltr;
    flex-direction: row;
    justify-content: center;
    gap: 0.5rem;
  }

  .v-field__input {
    direction: ltr;
    text-align: center;
    unicode-bidi: plaintext;
  }
}

.layout-blank .v-otp-input .v-field {
  border-radius: 0.5rem;
}
</style>
