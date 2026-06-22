<script setup>
import UserPlatformProfileView from '@/components/user/UserPlatformProfileView.vue'

definePage({
  meta: {},
})

const userData = useCookie('userData')

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const profileData = ref(null)
const editMode = ref(false)

const form = ref({
  name: '',
  job_title: '',
  city: '',
  bio: '',
  skills: [],
  visible_to_owners: true,
})

const skillInput = ref('')

const fetchProfile = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await $api('/profile/me')
    profileData.value = res
    form.value = {
      name: res.user?.name ?? '',
      job_title: res.profile?.job_title ?? '',
      city: res.profile?.city ?? '',
      bio: res.profile?.bio ?? '',
      skills: [...(res.profile?.skills ?? [])],
      visible_to_owners: res.profile?.visible_to_owners ?? true,
    }
  } catch (e) {
    error.value = e?.data?.message || 'خطا در بارگذاری پروفایل'
  } finally {
    loading.value = false
  }
}

const addSkill = () => {
  const skill = skillInput.value.trim()
  if (! skill || form.value.skills.includes(skill)) {
    return
  }
  form.value.skills.push(skill)
  skillInput.value = ''
}

const removeSkill = skill => {
  form.value.skills = form.value.skills.filter(s => s !== skill)
}

const saveProfile = async () => {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const res = await $api('/profile/me', {
      method: 'PATCH',
      body: form.value,
    })
    profileData.value = res
    editMode.value = false
    success.value = 'پروفایل با موفقیت ذخیره شد.'
    if (userData.value) {
      userData.value = {
        ...userData.value,
        fullName: res.user?.name,
        username: res.user?.name,
      }
    }
  } catch (e) {
    error.value = e?.data?.message || 'خطا در ذخیره پروفایل'
  } finally {
    saving.value = false
  }
}

onMounted(fetchProfile)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              پروفایل من
            </h4>
            <p class="text-body-2 text-medium-emphasis mb-0">
              اطلاعات حرفه‌ای خود را تکمیل کنید تا کارفرماها بتوانند رزومه شما را در سیستم ببینند
            </p>
          </div>
          <VBtn
            v-if="!editMode && profileData"
            color="primary"
            prepend-icon="tabler-edit"
            @click="editMode = true"
          >
            ویرایش پروفایل
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ error }}
      </VAlert>
      <VAlert
        v-if="success"
        type="success"
        variant="tonal"
        class="mb-4"
      >
        {{ success }}
      </VAlert>

      <div
        v-if="loading"
        class="text-center py-12"
      >
        <VProgressCircular indeterminate />
      </div>

      <VRow v-else-if="profileData">
        <VCol
          cols="12"
          md="5"
        >
          <VCard>
            <VCardText>
              <div class="text-center mb-6">
                <VAvatar
                  size="96"
                  color="primary"
                  variant="tonal"
                  class="mb-4"
                >
                  <VImg
                    v-if="profileData.user?.avatar"
                    :src="profileData.user.avatar"
                  />
                  <span v-else class="text-h4">{{ profileData.user?.name?.charAt(0) }}</span>
                </VAvatar>
                <h5 class="text-h5">
                  {{ profileData.user?.name }}
                </h5>
                <div
                  class="text-body-2"
                  dir="ltr"
                >
                  {{ profileData.user?.phone }}
                </div>
              </div>

              <VProgressLinear
                :model-value="profileData.stats?.completion_percent ?? 0"
                color="primary"
                height="8"
                rounded
                class="mb-2"
              />
              <div class="text-caption text-medium-emphasis mb-4">
                {{ profileData.stats?.completion_percent ?? 0 }}٪ تکمیل شده
              </div>

              <VList density="compact">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-building" />
                  </template>
                  <VListItemTitle>{{ profileData.stats?.tenant_count ?? 0 }} مجموعه</VListItemTitle>
                </VListItem>
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-star" />
                  </template>
                  <VListItemTitle>
                    {{ profileData.stats?.average_rating ?? '—' }} میانگین امتیاز
                  </VListItemTitle>
                </VListItem>
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-calendar" />
                  </template>
                  <VListItemTitle>عضو از {{ profileData.user?.member_since }}</VListItemTitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="7"
        >
          <VCard v-if="editMode">
            <VCardTitle>ویرایش پروفایل</VCardTitle>
            <VCardText>
              <AppTextField
                v-model="form.name"
                label="نام و نام خانوادگی"
                class="mb-4"
              />
              <AppTextField
                v-model="form.job_title"
                label="عنوان شغلی"
                placeholder="مثال: کارشناس فروش"
                class="mb-4"
              />
              <AppTextField
                v-model="form.city"
                label="شهر"
                placeholder="مثال: تهران"
                class="mb-4"
              />
              <AppTextarea
                v-model="form.bio"
                label="درباره من"
                rows="4"
                placeholder="خلاصه‌ای از تجربه و مهارت‌های شما..."
                class="mb-4"
              />

              <div class="mb-2 text-body-2">
                مهارت‌ها
              </div>
              <div class="d-flex gap-2 mb-3">
                <AppTextField
                  v-model="skillInput"
                  placeholder="مهارت جدید"
                  hide-details
                  @keyup.enter="addSkill"
                />
                <VBtn @click="addSkill">
                  افزودن
                </VBtn>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip
                  v-for="skill in form.skills"
                  :key="skill"
                  closable
                  @click:close="removeSkill(skill)"
                >
                  {{ skill }}
                </VChip>
              </div>

              <VCheckbox
                v-model="form.visible_to_owners"
                label="نمایش پروفایل برای کارفرماها و مالکین مجموعه‌ها"
              />
            </VCardText>
            <VCardActions>
              <VSpacer />
              <VBtn @click="editMode = false">
                انصراف
              </VBtn>
              <VBtn
                color="primary"
                :loading="saving"
                @click="saveProfile"
              >
                ذخیره
              </VBtn>
            </VCardActions>
          </VCard>

          <VCard v-else>
            <VCardText>
              <UserPlatformProfileView
                :data="profileData"
                :show-stats="false"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCol>
  </VRow>
</template>
