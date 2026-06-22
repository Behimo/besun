<script setup>
import UserPlatformProfileView from '@/components/user/UserPlatformProfileView.vue'

definePage({
  meta: {},
})

const route = useRoute('apps-profile-id')
const router = useRouter()
const userData = useCookie('userData')
const { isInTenantShell } = useAppShell()

const loading = ref(true)
const error = ref('')
const profileData = ref(null)
const reviewForm = ref({ rating: 5, comment: '', is_public: true })
const reviewLoading = ref(false)
const reviewError = ref('')

const inviteDialog = ref(false)
const inviteForm = ref({ phone: '', role: 'employee' })
const inviteLoading = ref(false)
const inviteError = ref('')

const userId = computed(() => Number(route.params.id))
const isSelf = computed(() => userData.value?.id === userId.value)

const fetchProfile = async () => {
  if (isSelf.value) {
    await router.replace({ name: 'apps-profile' })

    return
  }

  loading.value = true
  error.value = ''
  try {
    if (! isInTenantShell.value) {
      error.value = 'برای مشاهده پروفایل دیگران باید وارد مجموعه شوید.'

      return
    }

    const res = await $api(`/platform/users/${userId.value}`)
    profileData.value = res
    reviewForm.value = {
      rating: res.viewer_context?.existing_review?.rating ?? 5,
      comment: res.viewer_context?.existing_review?.comment ?? '',
      is_public: res.viewer_context?.existing_review?.is_public ?? true,
    }
  } catch (e) {
    error.value = e?.data?.message || 'خطا در بارگذاری پروفایل'
  } finally {
    loading.value = false
  }
}

const submitReview = async () => {
  reviewError.value = ''
  reviewLoading.value = true
  try {
    const res = await $api(`/platform/users/${userId.value}/reviews`, {
      method: 'POST',
      body: reviewForm.value,
    })
    profileData.value = res.profile
    reviewForm.value = {
      rating: res.profile?.viewer_context?.existing_review?.rating ?? 5,
      comment: res.profile?.viewer_context?.existing_review?.comment ?? '',
      is_public: res.profile?.viewer_context?.existing_review?.is_public ?? true,
    }
  } catch (e) {
    reviewError.value = e?.data?.message || 'خطا در ثبت نظر'
  } finally {
    reviewLoading.value = false
  }
}

const sendInvite = async () => {
  inviteError.value = ''
  const tenantId = userData.value?.tenant?.id
  if (! tenantId) {
    inviteError.value = 'مجموعه‌ای انتخاب نشده است.'

    return
  }

  inviteLoading.value = true
  try {
    await $api(`/tenants/${tenantId}/invitations`, {
      method: 'POST',
      body: { phone: profileData.value?.user?.phone, role: inviteForm.value.role },
    })
    inviteDialog.value = false
    await fetchProfile()
  } catch (e) {
    inviteError.value = e?.data?.message || 'خطا در ارسال دعوت'
  } finally {
    inviteLoading.value = false
  }
}

watch(() => route.params.id, fetchProfile, { immediate: true })
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center gap-3 flex-wrap">
      <VBtn
        variant="text"
        prepend-icon="tabler-arrow-right"
        :to="{ name: 'apps-crm-users' }"
      >
        بازگشت
      </VBtn>
      <h5 class="text-h5">
        پروفایل کاربر
      </h5>
    </VCardText>

    <VDivider />

    <VCardText>
      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
      >
        {{ error }}
      </VAlert>

      <div
        v-else-if="loading"
        class="text-center py-12"
      >
        <VProgressCircular indeterminate />
      </div>

      <UserPlatformProfileView
        v-else-if="profileData"
        v-model:review-form="reviewForm"
        v-model:review-loading="reviewLoading"
        v-model:review-error="reviewError"
        :data="profileData"
        @submit-review="submitReview"
      />

      <div
        v-if="profileData?.viewer_context?.can_invite"
        class="mt-4"
      >
        <VBtn
          color="primary"
          prepend-icon="tabler-user-plus"
          @click="inviteDialog = true"
        >
          ارسال درخواست عضویت
        </VBtn>
      </div>
    </VCardText>
  </VCard>

  <VDialog
    v-model="inviteDialog"
    max-width="440"
  >
    <VCard title="ارسال درخواست عضویت">
      <VCardText>
        <VAlert
          v-if="inviteError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ inviteError }}
        </VAlert>
        <AppSelect
          v-model="inviteForm.role"
          :items="[
            { title: 'کارمند', value: 'employee' },
            { title: 'مدیر', value: 'admin' },
          ]"
          label="نقش"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="inviteDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="inviteLoading"
          @click="sendInvite"
        >
          ارسال
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
