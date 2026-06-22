<script setup>
import AuthCoverLayout from '@/components/auth/AuthCoverLayout.vue'
import { resolvePlatformPostLoginRoute } from '@/composables/usePlatformAdmin'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps({
  portal: {
    type: String,
    required: true,
    validator: v => ['admin', 'support'].includes(v),
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()

const sessionExpiredMessage = ref('')

onMounted(() => {
  if (route.query.reason === 'session_expired')
    sessionExpiredMessage.value = 'نشست شما منقضی شده است. لطفاً دوباره وارد شوید.'
})

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const generalError = ref('')
const refForm = ref()
const isPasswordVisible = ref(false)

const submit = async () => {
  const { valid } = await refForm.value?.validate()
  if (! valid)
    return

  isLoading.value = true
  generalError.value = ''

  try {
    const res = await $api('/auth/platform/login', {
      method: 'POST',
      body: {
        email: email.value.trim(),
        password: password.value,
        portal: props.portal,
      },
      onResponseError({ response }) {
        generalError.value = response._data?.errors?.email?.[0]
          ?? response._data?.message
          ?? 'ورود ناموفق بود'
      },
    })

    setAuthSession({
      accessToken: res.accessToken,
      userData: res.userData,
      userAbilityRules: res.userAbilityRules,
    }, ability)

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : resolvePlatformPostLoginRoute(res.userData))
    })
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <AuthCoverLayout
    :title="title"
    :subtitle="subtitle || 'ورود با ایمیل و رمز عبور'"
  >
    <VForm
      ref="refForm"
      @submit.prevent="submit"
    >
      <VAlert
        v-if="sessionExpiredMessage"
        type="warning"
        variant="tonal"
        class="mb-4"
      >
        {{ sessionExpiredMessage }}
      </VAlert>

      <VAlert
        v-if="generalError"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ generalError }}
      </VAlert>

      <AppTextField
        v-model="email"
        label="ایمیل"
        type="email"
        autocomplete="username"
        class="mb-4"
        :rules="[v => !!v || 'ایمیل الزامی است']"
      />

      <AppTextField
        v-model="password"
        label="رمز عبور"
        :type="isPasswordVisible ? 'text' : 'password'"
        autocomplete="current-password"
        class="mb-6"
        :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
        :rules="[v => !!v || 'رمز عبور الزامی است']"
        @click:append-inner="isPasswordVisible = !isPasswordVisible"
      />

      <VBtn
        block
        type="submit"
        :loading="isLoading"
      >
        ورود
      </VBtn>

      <div class="text-center mt-4 d-flex flex-column gap-2">
        <RouterLink
          v-if="props.portal === 'admin'"
          class="text-primary text-body-2"
          :to="{ name: 'support-login' }"
        >
          ورود پشتیبانی
        </RouterLink>
        <RouterLink
          v-else
          class="text-primary text-body-2"
          :to="{ name: 'admin-login' }"
        >
          ورود مدیریت
        </RouterLink>
        <RouterLink
          class="text-medium-emphasis text-body-2"
          :to="{ name: 'login' }"
        >
          ورود مشتریان (OTP)
        </RouterLink>
      </div>
    </VForm>
  </AuthCoverLayout>
</template>
